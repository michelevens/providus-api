<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\AgencyConfig;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\License;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    // ── Platform Stats ─────────────────────────────────────────

    public function stats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'agencies' => Agency::count(),
                'agencies_active' => Agency::where('is_active', true)->count(),
                'users' => User::count(),
                'users_active' => User::where('is_active', true)->count(),
                'providers' => Provider::count(),
                'licenses' => License::count(),
                'applications' => Application::count(),
                'applications_by_status' => Application::selectRaw('status, count(*) as count')
                    ->groupBy('status')->pluck('count', 'status'),
                'agencies_by_plan' => Agency::selectRaw('plan_tier, count(*) as count')
                    ->groupBy('plan_tier')->pluck('count', 'plan_tier'),
            ],
        ]);
    }

    // ── Agencies ───────────────────────────────────────────────

    public function agencies(Request $request): JsonResponse
    {
        $query = Agency::withCount(['users', 'organizations', 'providers', 'applications', 'licenses'])
            ->with('config:id,agency_id,notification_email');

        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }
        if ($request->has('plan')) {
            $query->where('plan_tier', $request->plan);
        }
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%")
                  ->orWhere('slug', 'ilike', "%{$search}%");
            });
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderBy('name')->get(),
        ]);
    }

    // Operator-driven tenant creation.
    //
    // Mirrors the public AuthController::register flow but with three
    // differences for operator use:
    //   1. The operator picks the initial owner's email + name; the
    //      owner receives an invite link (NOT a generated password)
    //      and sets their own password by accepting the invite.
    //   2. The operator can optionally set plan_tier on creation —
    //      defaults to 'starter' / trialing same as self-signup.
    //   3. Audit log captures who created the tenant (Eloquent
    //      `creating` observer auto-stamps created_by/agency_id).
    public function createAgency(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'owner_email'   => 'required|email|unique:users,email',
            'owner_first_name' => 'required|string|max:100',
            'owner_last_name'  => 'required|string|max:100',
            'plan_tier'     => 'sometimes|nullable|string|in:free,basic,professional,enterprise',
            'email'         => 'sometimes|nullable|email|max:255',
            'phone'         => 'sometimes|nullable|string|max:30',
            'npi'           => 'sometimes|nullable|string|max:20',
            'tax_id'        => 'sometimes|nullable|string|max:30',
        ]);

        // Slug from name with collision-avoidance, same as register().
        $slug = Str::slug($data['name']);
        $baseSlug = $slug;
        $counter = 1;
        while (Agency::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter++;
        }

        // Create everything in a transaction so a half-failed mint
        // doesn't leave a dangling agency without an owner.
        $result = DB::transaction(function () use ($data, $slug) {
            $agency = Agency::create([
                'name'              => $data['name'],
                'slug'              => $slug,
                'email'             => $data['email'] ?? $data['owner_email'],
                'phone'             => $data['phone'] ?? null,
                'npi'               => $data['npi'] ?? null,
                'tax_id'            => $data['tax_id'] ?? null,
                'plan_tier'         => $data['plan_tier'] ?? 'starter',
                'subscription_status' => 'trialing',
                'is_active'         => true,
            ]);

            AgencyConfig::create(['agency_id' => $agency->id]);

            // Owner user, NOT yet active. Invite token generated; user
            // sets password by accepting the invite.
            $inviteToken = Str::random(64);
            $user = User::create([
                'agency_id'      => $agency->id,
                'email'          => $data['owner_email'],
                'password'       => Str::random(32),   // throwaway; replaced on accept
                'first_name'     => $data['owner_first_name'],
                'last_name'      => $data['owner_last_name'],
                'role'           => 'owner',
                'is_active'      => false,
                'invite_token'   => hash('sha256', $inviteToken),
                'invite_expires' => now()->addDays(7),
            ]);

            return ['agency' => $agency, 'user' => $user, 'inviteToken' => $inviteToken];
        });

        // Send invite email — best-effort; tenant creation already
        // succeeded. UserInvite Mailable is the same one used by
        // AgencyController::inviteUser.
        $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'https://app.credentik.com'));
        $inviteUrl = "{$frontendUrl}/#invite/{$result['inviteToken']}";
        try {
            Mail::to($result['user']->email)->send(
                new \App\Mail\UserInvite($result['user'], $result['agency'], $inviteUrl)
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('createAgency: invite email failed', [
                'agency_id' => $result['agency']->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'agency' => $result['agency'],
                'owner' => $result['user'],
                'invite_url' => $inviteUrl,
            ],
        ], 201);
    }

    public function agencyShow(int $id): JsonResponse
    {
        $agency = Agency::withCount([
            'users', 'organizations', 'providers',
            'applications', 'licenses', 'tasks', 'followups',
        ])
        ->with([
            'config',
            'users:id,agency_id,first_name,last_name,email,role,is_active,last_login_at,created_at',
        ])
        ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $agency]);
    }

    public function agencyUpdate(Request $request, int $id): JsonResponse
    {
        $agency = Agency::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'plan_tier' => 'sometimes|string|in:free,basic,professional,enterprise',
            'is_active' => 'sometimes|boolean',
            'primary_color' => 'sometimes|nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'accent_color' => 'sometimes|nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);
        $agency->update($request->only([
            'name', 'plan_tier', 'is_active',
            'primary_color', 'accent_color',
        ]));

        return response()->json(['success' => true, 'data' => $agency]);
    }

    // ── Agency Users (cross-agency) ───────────────────────────

    // Subscription detail for one tenant. Aggregates the Stripe fields
    // on the Agency model + recent stripe_event_log entries that match
    // the agency's customer or subscription id. Operator drill-in for
    // "what does Stripe think about this tenant?"

    public function agencySubscription(int $id): JsonResponse
    {
        $agency = Agency::findOrFail($id);

        // Recent Stripe events touching this tenant. We don't store
        // agency_id on stripe_event_log (it's a Stripe-side mirror), so
        // we filter by joining on the JSON payload metadata if needed.
        // Simpler v1: just list ALL recent webhook events globally and
        // tag them by event_type. Operator can correlate by timestamp.
        $recentEvents = collect();
        try {
            $recentEvents = \Illuminate\Support\Facades\DB::table('stripe_event_log')
                ->select('event_id', 'event_type', 'processed_at')
                ->orderByDesc('processed_at')
                ->limit(20)
                ->get();
        } catch (\Throwable $e) {}

        return response()->json([
            'success' => true,
            'data' => [
                'plan_tier' => $agency->plan_tier,
                'subscription_status' => $agency->subscription_status,
                'trial_ends_at' => $agency->trial_ends_at,
                'subscription_ends_at' => $agency->subscription_ends_at,
                'stripe_customer_id' => $agency->stripe_customer_id,
                'stripe_subscription_id' => $agency->stripe_subscription_id,
                'stripe_price_id' => $agency->stripe_price_id,
                // "Recent Stripe activity" — the operator scans for
                // anomalies (e.g. lots of subscription.updated in a row).
                'recent_stripe_events' => $recentEvents,
            ],
        ]);
    }

    // Compliance summary for one tenant. Aggregates signals from
    // existing models (License, DeaRegistration, Application,
    // ClaimDenial, AuditLog) into a single "where does this tenant
    // need attention?" snapshot. No new tables — everything below
    // already exists; we just join it.

    public function agencyCompliance(int $id): JsonResponse
    {
        $now = now();
        $thirtyDaysOut = $now->copy()->addDays(30);
        $sixtyDaysOut = $now->copy()->addDays(60);
        $ninetyDaysAgo = $now->copy()->subDays(90);
        $sevenDaysAgo = $now->copy()->subDays(7);

        // Licenses expiring in 30 days.
        $expiringLicenses30 = \App\Models\License::withoutGlobalScopes()
            ->where('agency_id', $id)
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '<=', $thirtyDaysOut)
            ->where('expiration_date', '>=', $now)
            ->orderBy('expiration_date')
            ->limit(20)
            ->get(['id', 'provider_id', 'state', 'license_number', 'expiration_date']);

        $expiringLicenses60 = \App\Models\License::withoutGlobalScopes()
            ->where('agency_id', $id)
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '>', $thirtyDaysOut)
            ->where('expiration_date', '<=', $sixtyDaysOut)
            ->count();

        // Already-expired licenses (red flag).
        $expiredLicenses = \App\Models\License::withoutGlobalScopes()
            ->where('agency_id', $id)
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '<', $now)
            ->count();

        // DEAs expiring soon.
        $expiringDeas = 0;
        try {
            $expiringDeas = \App\Models\DeaRegistration::withoutGlobalScopes()
                ->where('agency_id', $id)
                ->whereNotNull('expiration_date')
                ->where('expiration_date', '<=', $thirtyDaysOut)
                ->where('expiration_date', '>=', $now)
                ->count();
        } catch (\Throwable $e) {}

        // Stale applications: in 'in_review' or 'pending_info' for > 90 days
        // since submitted. Same definition Command Center uses.
        $staleApplications = \App\Models\Application::withoutGlobalScopes()
            ->where('agency_id', $id)
            ->whereIn('status', ['in_review', 'pending_info'])
            ->whereNotNull('submitted_date')
            ->where('submitted_date', '<', $ninetyDaysAgo)
            ->count();

        // Denial volume in last 7 days.
        $recentDenials = 0;
        try {
            $recentDenials = \App\Models\ClaimDenial::withoutGlobalScopes()
                ->where('agency_id', $id)
                ->where('created_at', '>=', $sevenDaysAgo)
                ->count();
        } catch (\Throwable $e) {}

        // Audit activity in last 7 days — proxy for "how active is this
        // tenant?" Healthy tenants generate audit events; quiet ones
        // are worth a check-in.
        $auditActivity7d = AuditLog::where('agency_id', $id)
            ->where('created_at', '>=', $sevenDaysAgo)
            ->count();

        // Impersonation activity in last 30 days. Tells the operator
        // if anyone (themselves or another superadmin) has been doing
        // support work on this tenant.
        $impersonationActivity30d = AuditLog::where('agency_id', $id)
            ->whereNotNull('impersonator_user_id')
            ->where('created_at', '>=', $now->copy()->subDays(30))
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'licenses' => [
                    'expired' => $expiredLicenses,
                    'expiring_30d' => $expiringLicenses30->count(),
                    'expiring_60d' => $expiringLicenses60,
                    'list_30d' => $expiringLicenses30,
                ],
                'deas' => [
                    'expiring_30d' => $expiringDeas,
                ],
                'applications' => [
                    'stale' => $staleApplications,
                ],
                'denials' => [
                    'last_7_days' => $recentDenials,
                ],
                'activity' => [
                    'audit_events_7d' => $auditActivity7d,
                    'impersonation_events_30d' => $impersonationActivity30d,
                ],
            ],
        ]);
    }

    // Notes (operator-only CRM) ──────────────────────────────────────
    //
    // List, create, update, delete operator-only notes about a tenant.
    // NOT visible to the tenant; just for the platform team to remember
    // context across sessions ("considering downgrading", "renewal
    // call next Tuesday", "billing dispute").

    public function agencyNotes(int $id): JsonResponse
    {
        $notes = \App\Models\AgencyNote::where('agency_id', $id)
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->get();
        return response()->json(['success' => true, 'data' => $notes]);
    }

    public function createAgencyNote(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'body' => 'required|string|max:5000',
            'tag' => 'nullable|string|max:40',
            'is_pinned' => 'sometimes|boolean',
        ]);
        \App\Models\Agency::findOrFail($id); // 404 if agency missing
        $note = \App\Models\AgencyNote::create([
            'agency_id' => $id,
            'author_user_id' => $request->user()->id,
            'author_email' => $request->user()->email,
            'body' => $data['body'],
            'tag' => $data['tag'] ?? null,
            'is_pinned' => $data['is_pinned'] ?? false,
        ]);
        return response()->json(['success' => true, 'data' => $note], 201);
    }

    public function updateAgencyNote(Request $request, int $id, int $noteId): JsonResponse
    {
        $note = \App\Models\AgencyNote::where('agency_id', $id)->where('id', $noteId)->firstOrFail();
        $data = $request->validate([
            'body' => 'sometimes|string|max:5000',
            'tag' => 'sometimes|nullable|string|max:40',
            'is_pinned' => 'sometimes|boolean',
        ]);
        $note->update($data);
        return response()->json(['success' => true, 'data' => $note]);
    }

    public function destroyAgencyNote(int $id, int $noteId): JsonResponse
    {
        $note = \App\Models\AgencyNote::where('agency_id', $id)->where('id', $noteId)->firstOrFail();
        $note->delete();
        return response()->json(['success' => true]);
    }

    // Webhooks for a given tenant + recent delivery stats. Used by the
    // operator-console Agency Detail → Webhooks tab. Aggregates the
    // existing per-webhook view (operator-facing, cross-tenant) so the
    // operator can debug a tenant's integrations without impersonating.
    public function agencyWebhooks(int $id): JsonResponse
    {
        $webhooks = \App\Models\Webhook::withoutGlobalScopes()
            ->where('agency_id', $id)
            ->orderByDesc('created_at')
            ->get();

        // Per-webhook stats: count of deliveries in the last 7 days, last failure, last success.
        $sevenDaysAgo = now()->subDays(7);
        $webhooks->transform(function ($w) use ($sevenDaysAgo) {
            $deliveries = \App\Models\WebhookDelivery::where('webhook_id', $w->id)
                ->where('created_at', '>=', $sevenDaysAgo);
            $lastSuccess = \App\Models\WebhookDelivery::where('webhook_id', $w->id)
                ->where('status', 'success')
                ->orderByDesc('created_at')
                ->first();
            $lastFailure = \App\Models\WebhookDelivery::where('webhook_id', $w->id)
                ->where('status', '!=', 'success')
                ->orderByDesc('created_at')
                ->first();
            // Strip secret entirely from operator-console responses —
            // operators don't need it; just expose a fingerprint.
            $w->secret_preview = $w->secret ? substr($w->secret, 0, 8) . '...' : null;
            unset($w->secret);
            $w->deliveries_7d = $deliveries->count();
            $w->failures_7d = (clone $deliveries)->where('status', '!=', 'success')->count();
            $w->last_success_at = $lastSuccess?->created_at;
            $w->last_failure_at = $lastFailure?->created_at;
            $w->last_failure_status = $lastFailure?->status;
            return $w;
        });

        return response()->json(['success' => true, 'data' => $webhooks]);
    }

    // Recent deliveries for ONE webhook. Operator drill-down — see the
    // last N attempts with their HTTP status, latency, and response
    // body excerpt for triage. Per-tenant filter via parent agency_id
    // not strictly needed since webhook_id is enough but checked
    // defensively.
    public function webhookDeliveries(Request $request, int $agencyId, int $webhookId): JsonResponse
    {
        $webhook = \App\Models\Webhook::withoutGlobalScopes()
            ->where('agency_id', $agencyId)
            ->where('id', $webhookId)
            ->firstOrFail();
        $deliveries = \App\Models\WebhookDelivery::where('webhook_id', $webhook->id)
            ->orderByDesc('created_at')
            ->limit((int) min($request->input('limit', 50), 200))
            ->get();
        return response()->json(['success' => true, 'data' => $deliveries]);
    }

    public function agencyUsers(int $id): JsonResponse
    {
        $users = User::where('agency_id', $id)
            ->orderByRaw("CASE role WHEN 'owner' THEN 1 WHEN 'agency' THEN 2 WHEN 'organization' THEN 3 WHEN 'provider' THEN 4 ELSE 5 END")
            ->get(['id', 'agency_id', 'first_name', 'last_name', 'email', 'role', 'is_active', 'last_login_at', 'created_at']);

        return response()->json(['success' => true, 'data' => $users]);
    }

    // ── Impersonate ───────────────────────────────────────────
    //
    // Mints a short-lived (2 hour) Sanctum token with a single ability
    // `impersonate:<agencyId>`. The V2 client swaps its cookie for the
    // new token; backend's User::effectiveAgencyId() reads the ability
    // and scopes all queries to that agency. Server-side bounded — when
    // the token expires (or is revoked via /admin/impersonate-stop) the
    // session simply 401s and the operator is bounced back to login.
    //
    // Previous behavior (X-Agency-Id header on the superadmin's own
    // token) is kept as a fallback for in-flight sessions but should
    // not be relied upon for new impersonation flows.

    public function impersonate(Request $request, int $agencyId): JsonResponse
    {
        $agency = Agency::findOrFail($agencyId);
        $superadmin = $request->user();

        // Revoke any prior impersonation tokens this superadmin holds
        // so they can't accumulate stale sessions across browser tabs.
        // We match by the token name prefix written below.
        $superadmin->tokens()
            ->where('name', 'like', 'impersonation:%')
            ->delete();

        // Mint the new token with a single ability that carries the
        // tenant context. Expiry honors config('sanctum.expiration')
        // but caps at 2 hours for impersonation specifically — operators
        // shouldn't sit in a tenant's UI for a full day.
        $expiresAt = now()->addHours(2);
        $token = $superadmin->createToken(
            "impersonation:{$agency->id}",
            ["impersonate:{$agency->id}"],
            $expiresAt,
        )->plainTextToken;

        // Audit-log the impersonation start so we have a record of
        // who-as-whom-when, even if writes during impersonation later.
        try {
            \App\Models\AuditLog::create([
                'agency_id' => $agency->id,
                'user_id' => $superadmin->id,
                'user_email' => $superadmin->email,
                'action' => 'impersonate.start',
                'auditable_type' => Agency::class,
                'auditable_id' => $agency->id,
                'new_values' => [
                    'impersonator_id' => $superadmin->id,
                    'impersonator_email' => $superadmin->email,
                    'expires_at' => $expiresAt->toIso8601String(),
                ],
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 200),
            ]);
        } catch (\Throwable $e) {
            // Non-fatal — impersonation continues even if audit fails.
            \Illuminate\Support\Facades\Log::warning('impersonation audit failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'agency_id' => $agency->id,
                'agency_name' => $agency->name,
                'agency_slug' => $agency->slug,
                'token' => $token,
                'expires_at' => $expiresAt->toIso8601String(),
            ],
        ])->withCookie($this->impersonationCookie($token, $expiresAt));
    }

    // Stop impersonation — revoke the impersonation token + clear the
    // cookie. The V2 client then needs to log back in as the superadmin
    // (their original token was implicitly invalidated when they got
    // the impersonation token).
    public function impersonateStop(Request $request): JsonResponse
    {
        $user = $request->user();

        // Revoke just the impersonation-prefixed tokens, not the
        // operator's regular session token (if any).
        $tokens = $user->tokens()->where('name', 'like', 'impersonation:%')->get();
        foreach ($tokens as $t) {
            $t->delete();
        }

        // Audit the stop. We can't reliably recover which agency
        // was being impersonated post-revocation without parsing the
        // token name, so we log just the count of sessions ended.
        try {
            \App\Models\AuditLog::create([
                'agency_id' => null,
                'user_id' => $user->id,
                'user_email' => $user->email,
                'action' => 'impersonate.stop',
                'new_values' => [
                    'sessions_ended' => $tokens->count(),
                ],
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 200),
            ]);
        } catch (\Throwable $e) {}

        return response()->json([
            'success' => true,
            'message' => "Ended {$tokens->count()} impersonation session(s).",
        ])->withCookie(\Illuminate\Support\Facades\Cookie::forget(
            'credentik_session',
            '/',
            app()->environment('production') ? '.credentik.com' : null,
        ));
    }

    // Cookie shape for the impersonation session — same domain/secure/
    // httponly attributes as the regular session cookie, but with the
    // explicit 2-hour expiry baked in. Mirrors AuthController::sessionCookie
    // (we don't share code because that's a private method on another
    // controller and the duplication is bounded).
    private function impersonationCookie(string $plainTextToken, \Carbon\Carbon $expiresAt): \Symfony\Component\HttpFoundation\Cookie
    {
        $minutes = (int) max(1, $expiresAt->diffInMinutes(now()));
        $secure = app()->environment('production');
        $domain = $secure ? '.credentik.com' : null;

        return \Illuminate\Support\Facades\Cookie::make(
            name: 'credentik_session',
            value: $plainTextToken,
            minutes: $minutes,
            path: '/',
            domain: $domain,
            secure: $secure,
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        );
    }

    // ── All Users (platform-wide) ─────────────────────────────

    public function users(Request $request): JsonResponse
    {
        $query = User::with('agency:id,name,slug');

        if ($request->has('role')) {
            $query->where('role', $request->role);
        }
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('email', 'ilike', "%{$search}%")
                  ->orWhere('first_name', 'ilike', "%{$search}%")
                  ->orWhere('last_name', 'ilike', "%{$search}%");
            });
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderBy('created_at', 'desc')->get(),
        ]);
    }

    public function userUpdate(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $request->validate([
            'is_active' => 'sometimes|boolean',
            'role' => 'sometimes|string|in:owner,agency,organization,provider,superadmin',
        ]);
        $data = $request->only(['is_active', 'role']);

        // Cannot demote another superadmin
        if ($user->role === 'superadmin' && isset($data['role']) && $data['role'] !== 'superadmin') {
            return response()->json(['success' => false, 'message' => 'Cannot demote a superadmin via API'], 403);
        }

        $user->update($data);

        return response()->json(['success' => true, 'data' => $user]);
    }

    // Reset a user's 2FA — for operators rescuing locked-out users.
    // Wipes the encrypted secret + recovery codes so the user can sign
    // in with email/password and re-enroll. Caller MUST verify identity
    // out-of-band (phone callback, etc.) before doing this.
    public function userResetMfa(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if ($user->role === 'superadmin' && $user->id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot reset another superadmin\'s 2FA via the API. Use artisan.',
            ], 403);
        }

        $user->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => '2FA reset. User must re-enroll on next login.',
        ]);
    }

    // Force-logout: revoke every Sanctum token for the user. Their
    // current sessions (web + mobile) drop to 401 on the next request.
    // The HttpOnly cookie also stops working since it carries a
    // revoked token. Used for compromised accounts or post-firing
    // off-boarding when the agency-level user-delete isn't enough.
    public function userForceLogout(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if ($user->role === 'superadmin' && $user->id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot force-logout another superadmin via the API. Use artisan.',
            ], 403);
        }

        $count = $user->tokens()->count();
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => "Revoked {$count} session(s). User will be logged out within seconds.",
        ]);
    }

    // ── Activity Log ─────────────────────────────────────────────

    public function auditLog(Request $request): JsonResponse
    {
        $query = \App\Models\ActivityLog::with([
            'application:id,agency_id,provider_id,payer_name,state',
            'application.provider:id,first_name,last_name',
        ]);

        if ($request->has('agency_id')) {
            $query->where('agency_id', $request->agency_id);
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderBy('created_at', 'desc')->limit(100)->get(),
        ]);
    }

    // ── Audit Logs (model-level change tracking) ──────────────

    public function auditLogs(Request $request): JsonResponse
    {
        $request->validate([
            'agency_id' => 'nullable|integer',
            'user_id' => 'nullable|integer',
            'action' => 'nullable|string|in:created,updated,deleted,restored,impersonate.start,impersonate.stop',
            'auditable_type' => 'nullable|string',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            // Filter to only entries written during impersonation. Useful
            // for compliance questions like "show me everything an
            // operator did while pretending to be a tenant in the last
            // 30 days" without combing through normal agency activity.
            'impersonated_only' => 'nullable|in:0,1,true,false',
        ]);

        $query = AuditLog::query();

        if (!$request->user()->isSuperAdmin()) {
            $query->where('agency_id', $request->user()->agency_id);
        } elseif ($agencyId = $request->input('agency_id')) {
            $query->where('agency_id', $agencyId);
        }

        if ($userId = $request->input('user_id')) $query->where('user_id', $userId);
        if ($action = $request->input('action')) $query->where('action', $action);
        if ($type = $request->input('auditable_type')) $query->where('auditable_type', $type);
        if ($from = $request->input('from')) $query->where('created_at', '>=', $from);
        if ($to = $request->input('to')) $query->where('created_at', '<=', $to);
        if ($request->boolean('impersonated_only')) {
            $query->whereNotNull('impersonator_user_id');
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderByDesc('created_at')->paginate(50),
        ]);
    }

    // ── System Health ───────────────────────────────────────────
    //
    // Aggregates real-time signals from the platform's load-bearing
    // subsystems: database, Stripe webhook log, audit log activity.
    // Returns a flat JSON the V2 operator console renders directly.
    // No expensive scans — each check is bounded (counts on indexed
    // columns, last-24h windows). Whole call should complete in <50ms.

    public function systemHealth(): JsonResponse
    {
        $now = now();
        $oneDayAgo = $now->copy()->subDay();

        // ── Database connectivity + size approximation ──
        $dbOk = false;
        $dbMs = null;
        try {
            $t0 = microtime(true);
            DB::select('SELECT 1');
            $dbMs = (int) ((microtime(true) - $t0) * 1000);
            $dbOk = true;
        } catch (\Throwable $e) {
            // dbOk stays false
        }

        // ── Stripe webhook log — uses the stripe_event_log table
        //    shipped earlier today as part of webhook idempotency. ──
        $stripeEvents24h = 0;
        $stripeEventTypes = [];
        try {
            $stripeEvents24h = DB::table('stripe_event_log')
                ->where('processed_at', '>=', $oneDayAgo)
                ->count();
            $stripeEventTypes = DB::table('stripe_event_log')
                ->select('event_type', DB::raw('count(*) as n'))
                ->where('processed_at', '>=', $oneDayAgo)
                ->groupBy('event_type')
                ->orderByDesc('n')
                ->limit(5)
                ->get()
                ->map(fn ($r) => ['type' => $r->event_type, 'count' => (int) $r->n])
                ->toArray();
        } catch (\Throwable $e) {
            // Table missing or query failed — leave defaults
        }

        // ── Audit log activity (24h volume per action) ──
        $auditActions = [];
        try {
            $auditActions = AuditLog::select('action', DB::raw('count(*) as n'))
                ->where('created_at', '>=', $oneDayAgo)
                ->groupBy('action')
                ->orderByDesc('n')
                ->get()
                ->map(fn ($r) => ['action' => $r->action, 'count' => (int) $r->n])
                ->toArray();
        } catch (\Throwable $e) {}

        // ── Active sessions estimate (logins in last 24h) ──
        $loginsLast24h = 0;
        try {
            $loginsLast24h = User::where('last_login_at', '>=', $oneDayAgo)->count();
        } catch (\Throwable $e) {}

        // ── User growth (last 7 days) ──
        $userGrowth7d = 0;
        try {
            $userGrowth7d = User::where('created_at', '>=', $now->copy()->subDays(7))->count();
        } catch (\Throwable $e) {}

        return response()->json([
            'success' => true,
            'data' => [
                'checked_at' => $now->toIso8601String(),
                'database' => [
                    'ok' => $dbOk,
                    'latency_ms' => $dbMs,
                ],
                'stripe' => [
                    'events_24h' => $stripeEvents24h,
                    'top_event_types' => $stripeEventTypes,
                ],
                'audit' => [
                    'actions_24h' => $auditActions,
                ],
                'sessions' => [
                    'logins_24h' => $loginsLast24h,
                ],
                'growth' => [
                    'new_users_7d' => $userGrowth7d,
                ],
            ],
        ]);
    }
}

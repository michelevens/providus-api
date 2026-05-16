<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\PasswordReset as PasswordResetMail;
use App\Mail\UserInvite;
use App\Models\Organization;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AgencyController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        // SuperAdmin can view any agency via X-Agency-Id header
        if ($user->isSuperAdmin() && $request->header('X-Agency-Id')) {
            $agency = \App\Models\Agency::find((int) $request->header('X-Agency-Id'));
            if ($agency) {
                return response()->json(['success' => true, 'data' => $agency]);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $user->agency,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'npi' => 'nullable|string|size:10',
            'tax_id' => 'nullable|string|max:20',
            'address_street' => 'nullable|string|max:255',
            'address_city' => 'nullable|string|max:100',
            'address_state' => 'nullable|string|size:2',
            'address_zip' => 'nullable|string|max:10',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'website' => 'nullable|string|max:255',
            'taxonomy' => 'nullable|string|max:20',
            'logo_url' => 'nullable|string|max:500000',
            'primary_color' => 'nullable|string|max:7',
            'accent_color' => 'nullable|string|max:7',
            'allowed_domains' => 'nullable|array',
        ]);

        $agency = $request->user()->agency;
        $agency->update($request->only([
            'name', 'npi', 'tax_id', 'address_street', 'address_city',
            'address_state', 'address_zip', 'phone', 'email', 'website',
            'taxonomy', 'logo_url', 'primary_color', 'accent_color', 'allowed_domains',
        ]));

        return response()->json(['success' => true, 'data' => $agency->fresh()]);
    }

    public function getConfig(Request $request): JsonResponse
    {
        $config = $request->user()->agency->config;
        return response()->json(['success' => true, 'data' => $config]);
    }

    public function updateConfig(Request $request): JsonResponse
    {
        $request->validate([
            'stedi_api_key' => 'nullable|string',
            'stedi_npi' => 'nullable|string|max:10',
            'stedi_org_name' => 'nullable|string|max:255',
            'caqh_org_id' => 'nullable|string|max:20',
            'caqh_username' => 'nullable|string|max:100',
            'caqh_password' => 'nullable|string',
            'caqh_environment' => 'nullable|in:production,sandbox',
            // Availity API credentials — OAuth2 client_credentials flow.
            // Used by AvailityService for eligibility (270/271), claim
            // status (276/277), and ERA file pickup (X12 835).
            'availity_client_id' => 'nullable|string',
            'availity_client_secret' => 'nullable|string',
            'availity_customer_id' => 'nullable|string|max:50',
            'availity_env' => 'nullable|in:production,sandbox',
            'notification_email' => 'nullable|email',
            'provider_name' => 'nullable|string|max:255',
            'elig_monthly_limit' => 'nullable|integer|min:0',
            'waves' => 'nullable|array',
            'waves.*.id' => 'required|integer',
            'waves.*.label' => 'required|string|max:50',
            'waves.*.short' => 'nullable|string|max:10',
            'waves.*.color' => 'nullable|string|max:20',
        ]);

        $config = $request->user()->agency->config;
        $config->update($request->only([
            'stedi_api_key', 'stedi_npi', 'stedi_org_name',
            'caqh_org_id', 'caqh_username', 'caqh_password', 'caqh_environment',
            'availity_client_id', 'availity_client_secret', 'availity_customer_id', 'availity_env',
            'notification_email', 'provider_name', 'elig_monthly_limit',
        ]));

        return response()->json(['success' => true, 'data' => $config->fresh()]);
    }

    // ── Agency User Management ───────────────────────────────────

    public function listUsers(Request $request): JsonResponse
    {
        $users = User::where('agency_id', $request->user()->effectiveAgencyId($request))
            ->with(['organization', 'provider'])
            ->get();

        return response()->json(['success' => true, 'data' => $users]);
    }

    public function inviteUser(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|unique:users,email',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'role' => 'required|in:owner,agency,staff,organization,provider',
            'organization_id' => 'nullable|integer',
            'provider_id' => 'nullable|integer',
        ]);

        // Privilege gate: only owners (or superadmin) can mint another owner.
        if ($request->role === 'owner' && !$request->user()->hasMinimumRole('owner')) {
            return response()->json(['success' => false, 'message' => 'Only owners can assign the owner role.'], 403);
        }

        $agencyId = $request->user()->effectiveAgencyId($request);

        // Enforce user plan limit
        $agency = \App\Models\Agency::find($agencyId);
        if ($agency && !$request->user()->isSuperAdmin()) {
            $limit = $agency->planLimit('users');
            if ($limit !== -1 && $agency->users()->count() >= $limit) {
                return response()->json([
                    'success' => false,
                    'message' => "User limit reached ({$limit}) for your " . ucfirst($agency->plan_tier) . " plan. Please upgrade.",
                    'error_code' => 'plan_limit_reached',
                ], 403);
            }
        }

        // Organization role requires organization_id
        if ($request->role === 'organization') {
            if (!$request->organization_id) {
                return response()->json([
                    'success' => false, 'message' => 'organization_id is required for organization role',
                ], 422);
            }
        }

        // Provider role requires provider_id
        if ($request->role === 'provider') {
            if (!$request->provider_id) {
                return response()->json([
                    'success' => false, 'message' => 'provider_id is required for provider role',
                ], 422);
            }
        }

        // Validate organization belongs to this agency
        if ($request->organization_id) {
            $org = Organization::where('agency_id', $agencyId)
                ->find($request->organization_id);

            if (!$org) {
                return response()->json([
                    'success' => false, 'message' => 'Organization not found in this agency',
                ], 404);
            }
        }

        // Validate provider belongs to this agency
        if ($request->provider_id) {
            $provider = Provider::where('agency_id', $agencyId)
                ->find($request->provider_id);

            if (!$provider) {
                return response()->json([
                    'success' => false, 'message' => 'Provider not found in this agency',
                ], 404);
            }
        }

        $inviteToken = Str::random(64);

        $user = User::create([
            'agency_id' => $agencyId,
            'organization_id' => $request->organization_id,
            'provider_id' => $request->provider_id,
            'email' => $request->email,
            'password' => Str::random(32), // temp password, replaced on invite accept
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'role' => $request->role,
            'ui_role' => $request->ui_role ?? $request->role,
            'is_active' => false,
            'invite_token' => hash('sha256', $inviteToken),
            'invite_expires' => now()->addDays(7),
        ]);

        // Send invitation email
        $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'https://app.credentik.com'));
        $inviteUrl = "{$frontendUrl}/#invite/{$inviteToken}";
        $agencyName = $request->user()->agency->name;

        $agency = $request->user()->agency;
        Mail::to($user->email)->send(new UserInvite($user, $agency, $inviteUrl));

        return response()->json([
            'success' => true,
            'data' => $user->load(['organization', 'provider']),
            'invite_url' => $inviteUrl,
        ], 201);
    }

    public function updateUser(Request $request, int $id): JsonResponse
    {
        $user = User::where('agency_id', $request->user()->effectiveAgencyId($request))->findOrFail($id);

        $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'email' => 'sometimes|email|max:200|unique:users,email,' . $id,
            'phone' => 'sometimes|nullable|string|max:30',
            'password' => 'sometimes|string|min:6',
            'role' => 'sometimes|in:owner,agency,staff,organization,provider',
            'ui_role' => 'sometimes|nullable|string|max:50',
            'is_active' => 'sometimes|boolean',
            'organization_id' => 'sometimes|nullable|integer',
            'provider_id' => 'sometimes|nullable|integer',
        ]);

        // Cannot promote to superadmin
        if ($request->has('role') && $request->role === 'superadmin') {
            return response()->json([
                'success' => false, 'message' => 'Cannot assign superadmin role',
            ], 403);
        }

        // Privilege gate: only owners (or superadmin) can promote anyone — including themselves —
        // to owner, and only owners can demote an existing owner.
        if ($request->has('role') && $request->role === 'owner' && !$request->user()->hasMinimumRole('owner')) {
            return response()->json(['success' => false, 'message' => 'Only owners can assign the owner role.'], 403);
        }
        if ($user->role === 'owner' && $request->has('role') && $request->role !== 'owner' && !$request->user()->hasMinimumRole('owner')) {
            return response()->json(['success' => false, 'message' => 'Only owners can change another owner\'s role.'], 403);
        }

        $agencyId = $request->user()->effectiveAgencyId($request);

        // Validate organization belongs to this agency
        if ($request->has('organization_id') && $request->organization_id) {
            $org = Organization::where('agency_id', $agencyId)
                ->find($request->organization_id);

            if (!$org) {
                return response()->json([
                    'success' => false, 'message' => 'Organization not found in this agency',
                ], 404);
            }
        }

        // Validate provider belongs to this agency
        if ($request->has('provider_id') && $request->provider_id) {
            $provider = Provider::where('agency_id', $agencyId)
                ->find($request->provider_id);

            if (!$provider) {
                return response()->json([
                    'success' => false, 'message' => 'Provider not found in this agency',
                ], 404);
            }
        }

        $data = $request->only(['first_name', 'last_name', 'email', 'phone', 'role', 'ui_role', 'is_active', 'organization_id', 'provider_id']);
        if ($request->has('password')) {
            $data['password'] = $request->password;
        }
        $user->update($data);

        return response()->json([
            'success' => true,
            'data' => $user->load(['organization', 'provider']),
        ]);
    }

    public function deleteUser(Request $request, int $id): JsonResponse
    {
        $user = User::where('agency_id', $request->user()->effectiveAgencyId($request))->findOrFail($id);

        // Cannot delete superadmin or agency-owner accounts
        if ($user->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Cannot delete a superadmin account'], 403);
        }

        // Prevent non-superadmin from deleting owner/agency-level users
        if (in_array($user->role, ['owner', 'agency']) && !$request->user()->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Cannot delete an owner/agency-level account'], 403);
        }

        $user->update(['is_active' => false]);
        return response()->json(['success' => true]);
    }

    /**
     * SuperAdmin: send password reset email to a user.
     */
    public function resetUserPassword(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'SuperAdmin access required'], 403);
        }

        $user = User::findOrFail($id);

        $resetToken = Str::random(64);
        $user->update([
            'password_reset_token' => hash('sha256', $resetToken),
            'password_reset_expires' => now()->addHours(24),
        ]);

        $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'https://app.credentik.com'));
        $resetUrl = "{$frontendUrl}/#reset-password/{$resetToken}";

        Mail::to($user->email)->send(new PasswordResetMail($user, $resetUrl));

        return response()->json([
            'success' => true,
            'message' => "Password reset email sent to {$user->email}",
        ]);
    }

    /**
     * SuperAdmin: change a user's email address.
     */
    public function changeUserEmail(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'SuperAdmin access required'], 403);
        }

        $request->validate([
            'email' => 'required|email|unique:users,email,' . $id,
        ]);

        $user = User::findOrFail($id);
        $oldEmail = $user->email;
        $user->update(['email' => $request->email]);

        return response()->json([
            'success' => true,
            'data' => $user->load(['organization', 'provider']),
            'message' => "Email changed from {$oldEmail} to {$request->email}",
        ]);
    }

    /**
     * GET /agency/users/{id}/activity — aggregator for the V2
     * TeamMemberDetailPage. Returns the user record + assignment
     * counts + denial-work tallies + recent claims + login/audit
     * history in a single payload so the page renders in one fetch.
     *
     * Agency-scoped — the target user must belong to the requester's
     * agency. Same `role:agency` gate as listUsers (route group).
     */
    public function userActivity(Request $request, int $id): JsonResponse
    {
        $agencyId = $request->user()->effectiveAgencyId($request);

        $user = User::where('agency_id', $agencyId)
            ->with(['organization:id,name', 'provider:id,first_name,last_name'])
            ->findOrFail($id);

        // ── Assignment counts ──
        // applications.assigned_to and tasks.assigned_to are the
        // single-user FKs available today. claims.followup_assigned_to
        // does NOT exist (checked the schema) so we drop that count.
        $appsOpenStatuses = ['draft', 'pending', 'in_progress', 'submitted', 'follow_up'];
        $appsQuery = \DB::table('applications')
            ->where('agency_id', $agencyId)
            ->where('assigned_to', $id)
            ->whereNull('deleted_at');
        $appsTotal = (clone $appsQuery)->count();
        $appsOpen  = (clone $appsQuery)->whereIn('status', $appsOpenStatuses)->count();

        $tasksQuery = \DB::table('tasks')
            ->where('agency_id', $agencyId)
            ->where('assigned_to', $id);
        $tasksTotal     = (clone $tasksQuery)->count();
        $tasksOpen      = (clone $tasksQuery)->where('is_completed', false)->count();
        $tasksCompleted = (clone $tasksQuery)->where('is_completed', true)->count();

        // ── Denial work ──
        // Multiple per-stage attribution columns. A denial "worked by
        // this user" = touched at any stage. Counted per stage so the
        // operator can see balance (lots of drafted, no sent = stuck).
        // Qualify agency_id to claim_denials because some derived
        // queries below leftJoin the claims table (which also has an
        // agency_id) — without the qualifier Postgres raises
        // "ambiguous column" after the join is applied to the clone.
        $denialBase = \DB::table('claim_denials')->where('claim_denials.agency_id', $agencyId);
        $triagedCount = (clone $denialBase)->where('triaged_by', $id)->count();
        $draftedCount = (clone $denialBase)->where('letter_drafted_by', $id)->count();
        $sentCount    = (clone $denialBase)->where('letter_sent_by', $id)->count();
        $createdCount = (clone $denialBase)->where('created_by', $id)->count();

        // Recovery rate on denials this user triaged: out of triaged,
        // how many ended in resolved_recovered. Skips upheld/written_off.
        $triagedTotal = $triagedCount;
        $triagedRecovered = (clone $denialBase)->where('triaged_by', $id)->where('status', 'resolved_recovered')->count();
        $recoveryRatePct = $triagedTotal > 0 ? round(($triagedRecovered / $triagedTotal) * 100, 1) : 0.0;

        // Open vs resolved denial split — anything not in a resolved_* or
        // escalated status counts as open work.
        $openStatuses = ['new', 'triaged', 'letter_drafted', 'letter_sent', 'awaiting_response', 'payer_responded'];
        $denialsOpen = (clone $denialBase)
            ->where(function ($q) use ($id) {
                $q->where('triaged_by', $id)
                  ->orWhere('letter_drafted_by', $id)
                  ->orWhere('letter_sent_by', $id)
                  ->orWhere('created_by', $id);
            })
            ->whereIn('status', $openStatuses)
            ->count();
        $denialsResolved = (clone $denialBase)
            ->where(function ($q) use ($id) {
                $q->where('triaged_by', $id)
                  ->orWhere('letter_drafted_by', $id)
                  ->orWhere('letter_sent_by', $id)
                  ->orWhere('created_by', $id);
            })
            ->whereIn('status', ['resolved_recovered', 'resolved_upheld', 'resolved_written_off'])
            ->count();

        // ── Claims created ──
        $claimsCreatedCount = \DB::table('claims')
            ->where('agency_id', $agencyId)
            ->where('created_by', $id)
            ->count();

        // ── Recent rows for each tab (capped) ──
        // Qualify every where on `agency_id` / `assigned_to` to the
        // applications table because the joined providers and
        // organizations tables also have agency_id columns (Postgres
        // would otherwise raise "column reference is ambiguous").
        $recentApplications = \DB::table('applications')
            ->where('applications.agency_id', $agencyId)
            ->where('applications.assigned_to', $id)
            ->whereNull('applications.deleted_at')
            ->leftJoin('providers', 'providers.id', '=', 'applications.provider_id')
            ->leftJoin('organizations', 'organizations.id', '=', 'applications.organization_id')
            ->orderByDesc('applications.updated_at')
            ->limit(20)
            ->get([
                'applications.id',
                'applications.status',
                'applications.payer_name',
                'applications.state',
                'applications.updated_at',
                \DB::raw("CONCAT(providers.first_name, ' ', providers.last_name) as provider_name"),
                'organizations.name as organization_name',
            ]);

        $recentTasks = \DB::table('tasks')
            ->where('agency_id', $agencyId)
            ->where('assigned_to', $id)
            ->orderByRaw('is_completed ASC, COALESCE(due_date, created_at) DESC')
            ->limit(20)
            ->get(['id', 'title', 'priority', 'category', 'due_date', 'is_completed', 'completed_at', 'created_at']);

        // Recent denial work — union of denials this user touched at any
        // stage, with a synthetic 'action' column saying which stage they
        // last touched. Bound to claim_number via the join so the V2
        // page can link to /rcm/denials/{id}.
        $recentDenials = (clone $denialBase)
            ->leftJoin('claims', 'claims.id', '=', 'claim_denials.claim_id')
            ->where(function ($q) use ($id) {
                $q->where('claim_denials.triaged_by', $id)
                  ->orWhere('claim_denials.letter_drafted_by', $id)
                  ->orWhere('claim_denials.letter_sent_by', $id)
                  ->orWhere('claim_denials.created_by', $id);
            })
            ->orderByDesc('claim_denials.updated_at')
            ->limit(15)
            ->get([
                'claim_denials.id',
                'claim_denials.status',
                'claim_denials.denial_code',
                'claim_denials.denied_amount',
                'claim_denials.denial_category',
                'claim_denials.updated_at',
                'claim_denials.letter_sent_by',
                'claim_denials.letter_drafted_by',
                'claim_denials.triaged_by',
                'claim_denials.created_by',
                'claims.claim_number',
                'claims.patient_name',
                'claims.payer_name as claim_payer_name',
            ])
            ->map(function ($row) use ($id) {
                // Surface the "last action by this user" so the UI
                // doesn't have to recompute it.
                $action = match (true) {
                    $row->letter_sent_by === $id      => 'sent letter',
                    $row->letter_drafted_by === $id   => 'drafted letter',
                    $row->triaged_by === $id          => 'triaged',
                    $row->created_by === $id          => 'created',
                    default                            => 'touched',
                };
                $row->last_action_by_user = $action;
                return $row;
            });

        $recentClaims = \DB::table('claims')
            ->where('agency_id', $agencyId)
            ->where('created_by', $id)
            ->orderByDesc('created_at')
            ->limit(15)
            ->get(['id', 'claim_number', 'patient_name', 'payer_name', 'total_charges', 'status', 'date_of_service', 'created_at']);

        // ── Login & audit history ──
        $recentLogins = \DB::table('auth_events')
            ->where('user_id', $id)
            ->orderByDesc('id')
            ->limit(15)
            ->get(['id', 'event_type', 'ip_address', 'user_agent', 'created_at']);

        $auditTrail = \DB::table('audit_logs')
            ->where('agency_id', $agencyId)
            ->where('user_id', $id)
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'action', 'auditable_type', 'auditable_id', 'created_at', 'impersonator_user_id']);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'kpis' => [
                    'applications_open'   => $appsOpen,
                    'applications_total'  => $appsTotal,
                    'tasks_open'          => $tasksOpen,
                    'tasks_completed'     => $tasksCompleted,
                    'tasks_total'         => $tasksTotal,
                    'denials_open'        => $denialsOpen,
                    'denials_resolved'    => $denialsResolved,
                    'recovery_rate_pct'   => $recoveryRatePct,
                    'claims_created'      => $claimsCreatedCount,
                ],
                'denial_work_breakdown' => [
                    'created'  => $createdCount,
                    'triaged'  => $triagedCount,
                    'drafted'  => $draftedCount,
                    'sent'     => $sentCount,
                ],
                'assignments' => [
                    'applications' => $recentApplications,
                    'tasks'        => $recentTasks,
                ],
                'denial_work'   => $recentDenials,
                'claims'        => $recentClaims,
                'history' => [
                    'logins' => $recentLogins,
                    'audit'  => $auditTrail,
                ],
            ],
        ]);
    }

    /**
     * Get agency branding configuration.
     */
    public function branding(Request $request): JsonResponse
    {
        $agency = $request->user()->agency;

        return response()->json([
            'success' => true,
            'data'    => [
                'logoUrl'            => $agency->logo_url,
                'primaryColor'       => $agency->primary_color,
                'accentColor'        => $agency->accent_color,
                'companyName'        => $agency->company_display_name ?? $agency->name,
                'emailFooter'        => $agency->email_footer,
                'customDomain'       => $agency->custom_domain,
            ],
        ]);
    }

    /**
     * Update agency branding configuration.
     */
    public function updateBranding(Request $request): JsonResponse
    {
        $data = $request->validate([
            'logoUrl'       => 'nullable|string|max:500000',
            'primaryColor'  => 'nullable|string|max:7',
            'accentColor'   => 'nullable|string|max:7',
            'companyName'   => 'nullable|string|max:255',
            'emailFooter'   => 'nullable|string|max:1000',
            'customDomain'  => 'nullable|string|max:255',
        ]);

        $agency = $request->user()->agency;
        $agency->update([
            'logo_url'             => $data['logoUrl'] ?? $agency->logo_url,
            'primary_color'        => $data['primaryColor'] ?? $agency->primary_color,
            'accent_color'         => $data['accentColor'] ?? $agency->accent_color,
            'company_display_name' => $data['companyName'] ?? $agency->company_display_name,
            'email_footer'         => $data['emailFooter'] ?? $agency->email_footer,
            'custom_domain'        => $data['customDomain'] ?? $agency->custom_domain,
        ]);

        return response()->json(['success' => true, 'data' => $agency->fresh()]);
    }
}

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
            'logo_url' => 'nullable|string|max:500',
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
            'notification_email' => 'nullable|email',
            'provider_name' => 'nullable|string|max:255',
            'elig_monthly_limit' => 'nullable|integer|min:0',
        ]);

        $config = $request->user()->agency->config;
        $config->update($request->only([
            'stedi_api_key', 'stedi_npi', 'stedi_org_name',
            'caqh_org_id', 'caqh_username', 'caqh_password', 'caqh_environment',
            'notification_email', 'provider_name', 'elig_monthly_limit',
        ]));

        return response()->json(['success' => true, 'data' => $config->fresh()]);
    }

    // ── Agency User Management ───────────────────────────────────

    public function listUsers(Request $request): JsonResponse
    {
        $users = User::where('agency_id', $request->user()->agency_id)
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
            'role' => 'required|in:owner,agency,organization,provider',
            'organization_id' => 'nullable|integer',
            'provider_id' => 'nullable|integer',
        ]);

        $agencyId = $request->user()->agency_id;

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
        $user = User::where('agency_id', $request->user()->agency_id)->findOrFail($id);

        $request->validate([
            'role' => 'sometimes|in:owner,agency,organization,provider',
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

        $agencyId = $request->user()->agency_id;

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

        $user->update($request->only(['role', 'is_active', 'organization_id', 'provider_id']));

        return response()->json([
            'success' => true,
            'data' => $user->load(['organization', 'provider']),
        ]);
    }

    public function deleteUser(Request $request, int $id): JsonResponse
    {
        $user = User::where('agency_id', $request->user()->agency_id)->findOrFail($id);

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
}

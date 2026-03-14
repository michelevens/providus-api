<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'role' => 'required|in:agency,organization,provider',
            'password' => 'required|string|min:8',
            'organization_id' => 'nullable|integer',
            'provider_id' => 'nullable|integer',
        ]);

        $agencyId = $request->user()->agency_id;

        // Organization role requires organization_id
        if ($request->role === 'organization') {
            if (!$request->organization_id) {
                return response()->json([
                    'error' => 'organization_id is required for organization role',
                ], 422);
            }
        }

        // Provider role requires provider_id
        if ($request->role === 'provider') {
            if (!$request->provider_id) {
                return response()->json([
                    'error' => 'provider_id is required for provider role',
                ], 422);
            }
        }

        // Validate organization belongs to this agency
        if ($request->organization_id) {
            $org = Organization::where('agency_id', $agencyId)
                ->find($request->organization_id);

            if (!$org) {
                return response()->json([
                    'error' => 'Organization not found in this agency',
                ], 404);
            }
        }

        // Validate provider belongs to this agency
        if ($request->provider_id) {
            $provider = Provider::where('agency_id', $agencyId)
                ->find($request->provider_id);

            if (!$provider) {
                return response()->json([
                    'error' => 'Provider not found in this agency',
                ], 404);
            }
        }

        $user = User::create([
            'agency_id' => $agencyId,
            'organization_id' => $request->organization_id,
            'provider_id' => $request->provider_id,
            'email' => $request->email,
            'password' => $request->password,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'role' => $request->role,
        ]);

        return response()->json([
            'success' => true,
            'data' => $user->load(['organization', 'provider']),
        ], 201);
    }

    public function updateUser(Request $request, int $id): JsonResponse
    {
        $user = User::where('agency_id', $request->user()->agency_id)->findOrFail($id);

        $request->validate([
            'role' => 'sometimes|in:agency,organization,provider',
            'is_active' => 'sometimes|boolean',
            'organization_id' => 'sometimes|nullable|integer',
            'provider_id' => 'sometimes|nullable|integer',
        ]);

        // Cannot promote to superadmin
        if ($request->has('role') && $request->role === 'superadmin') {
            return response()->json([
                'error' => 'Cannot assign superadmin role',
            ], 403);
        }

        $agencyId = $request->user()->agency_id;

        // Validate organization belongs to this agency
        if ($request->has('organization_id') && $request->organization_id) {
            $org = Organization::where('agency_id', $agencyId)
                ->find($request->organization_id);

            if (!$org) {
                return response()->json([
                    'error' => 'Organization not found in this agency',
                ], 404);
            }
        }

        // Validate provider belongs to this agency
        if ($request->has('provider_id') && $request->provider_id) {
            $provider = Provider::where('agency_id', $agencyId)
                ->find($request->provider_id);

            if (!$provider) {
                return response()->json([
                    'error' => 'Provider not found in this agency',
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
            return response()->json(['error' => 'Cannot delete a superadmin account'], 403);
        }

        // Prevent agency users from deleting other agency-level users
        // (only superadmin can do that, and they bypass this check above)
        if ($user->role === 'agency' && !$request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Cannot delete an agency-level account'], 403);
        }

        $user->update(['is_active' => false]);
        return response()->json(['success' => true]);
    }
}

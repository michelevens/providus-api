<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgencyController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $request->user()->agency,
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

    // Agency user management
    public function listUsers(Request $request): JsonResponse
    {
        $users = User::where('agency_id', $request->user()->agency_id)->get();
        return response()->json(['success' => true, 'data' => $users]);
    }

    public function inviteUser(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|unique:users,email',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'role' => 'required|in:admin,staff,readonly',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'agency_id' => $request->user()->agency_id,
            'email' => $request->email,
            'password' => $request->password,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'role' => $request->role,
        ]);

        return response()->json(['success' => true, 'data' => $user], 201);
    }

    public function updateUser(Request $request, int $id): JsonResponse
    {
        $user = User::where('agency_id', $request->user()->agency_id)->findOrFail($id);

        $request->validate([
            'role' => 'sometimes|in:admin,staff,readonly',
            'is_active' => 'sometimes|boolean',
        ]);

        $user->update($request->only(['role', 'is_active']));
        return response()->json(['success' => true, 'data' => $user]);
    }

    public function deleteUser(Request $request, int $id): JsonResponse
    {
        $user = User::where('agency_id', $request->user()->agency_id)->findOrFail($id);

        if ($user->role === 'owner') {
            return response()->json(['error' => 'Cannot delete the owner account'], 403);
        }

        $user->update(['is_active' => false]);
        return response()->json(['success' => true]);
    }
}

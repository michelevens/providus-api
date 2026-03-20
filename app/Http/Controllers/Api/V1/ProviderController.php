<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Provider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Provider::with(['organization', 'licenses']);
        if ($request->has('active')) $query->where('is_active', $request->boolean('active'));
        if ($request->has('organization_id')) $query->where('organization_id', $request->organization_id);
        return response()->json(['success' => true, 'data' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        // Enforce plan limit
        $agency = Agency::find($request->user()->agency_id);
        if ($agency && !$request->user()->isSuperAdmin()) {
            $limit = $agency->planLimit('providers');
            if ($limit !== -1 && $agency->providers()->count() >= $limit) {
                return response()->json([
                    'success' => false,
                    'message' => "Provider limit reached ({$limit}) for your " . ucfirst($agency->plan_tier) . " plan. Please upgrade.",
                    'error_code' => 'plan_limit_reached',
                ], 403);
            }
        }

        $data = $request->validate([
            'organization_id' => 'nullable|exists:organizations,id',
            'first_name' => 'required|string|max:100', 'last_name' => 'required|string|max:100',
            'credentials' => 'nullable|string|max:100', 'npi' => 'nullable|string|size:10',
            'taxonomy' => 'nullable|string|max:20', 'specialty' => 'nullable|string|max:100',
            'email' => 'nullable|email', 'phone' => 'nullable|string|max:20',
            'caqh_id' => 'nullable|string|max:20', 'is_active' => 'boolean',
        ]);

        return response()->json(['success' => true, 'data' => Provider::create($data)], 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['success' => true, 'data' => Provider::with(['organization', 'licenses', 'caqhTracking'])->findOrFail($id)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $provider = Provider::findOrFail($id);
        $provider->update($request->only([
            'organization_id', 'first_name', 'last_name', 'credentials', 'npi',
            'taxonomy', 'specialty', 'email', 'phone', 'caqh_id', 'is_active',
        ]));
        return response()->json(['success' => true, 'data' => $provider]);
    }

    public function destroy(int $id): JsonResponse
    {
        Provider::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }
}

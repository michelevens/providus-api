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

        $data = $request->validate(self::validationRules());

        return response()->json(['success' => true, 'data' => Provider::create($data)], 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['success' => true, 'data' => Provider::with(['organization', 'licenses', 'caqhTracking', 'deaRegistrations'])->findOrFail($id)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $provider = Provider::findOrFail($id);
        $rules = collect(self::validationRules())->map(fn($r) => str_replace('required|', 'sometimes|', $r))->all();
        $data = $request->validate($rules);
        $provider->update($data);
        return response()->json(['success' => true, 'data' => $provider]);
    }

    private static function validationRules(): array
    {
        return [
            'organization_id' => 'nullable|exists:organizations,id',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'date_of_birth' => 'nullable|date',
            'ssn_last4' => 'nullable|string|size:4',
            'gender' => 'nullable|string|in:male,female,non-binary,other,prefer_not_to_say',
            'credentials' => 'nullable|string|max:100',
            'npi' => 'nullable|string|size:10',
            'taxonomy' => 'nullable|string|max:20',
            'specialty' => 'nullable|string|max:100',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'address_street' => 'nullable|string|max:255',
            'address_city' => 'nullable|string|max:100',
            'address_state' => 'nullable|string|max:2',
            'address_zip' => 'nullable|string|max:10',
            'caqh_id' => 'nullable|string|max:20',
            // NP Collaborative Practice
            'supervising_physician' => 'nullable|string|max:255',
            'supervising_physician_npi' => 'nullable|string|size:10',
            'collaborative_agreement_status' => 'nullable|string|in:active,expired,not_required,pending',
            'collaborative_agreement_expiry' => 'nullable|date',
            // Scope of Practice
            'practice_authority' => 'nullable|string|in:full,reduced,restricted',
            'prescriptive_authority' => 'nullable|boolean',
            'controlled_substance_authority' => 'nullable|boolean',
            'cs_schedule_authority' => 'nullable|string|max:50',
            // Professional IDs
            'state_of_primary_license' => 'nullable|string|max:2',
            'medicaid_id' => 'nullable|string|max:30',
            'medicare_ptan' => 'nullable|string|max:30',
            'languages_spoken' => 'nullable|string|max:500',
            'bio' => 'nullable|string|max:2000',
            // Status
            'is_active' => 'nullable|boolean',
            'onboarding_status' => 'nullable|string|in:pending,in_progress,complete',
        ];
    }

    public function destroy(int $id): JsonResponse
    {
        Provider::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }
}

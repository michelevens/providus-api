<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OnboardToken;
use App\Models\Organization;
use App\Models\Provider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OnboardController extends Controller
{
    // List tokens for authenticated user's agency
    public function index(Request $request): JsonResponse
    {
        $tokens = OnboardToken::where('agency_id', $request->user()->agency_id)
            ->orderByDesc('created_at')
            ->get();
        return response()->json(['success' => true, 'data' => $tokens]);
    }

    // Create a new onboard token
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'provider_email' => 'required|email',
            'expires_hours' => 'nullable|integer|min:1|max:720',
        ]);

        $token = OnboardToken::create([
            'agency_id' => $request->user()->agency_id,
            'token' => Str::random(64),
            'provider_email' => $request->provider_email,
            'expires_at' => now()->addHours($request->input('expires_hours', 72)),
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['success' => true, 'data' => $token], 201);
    }

    // Delete/revoke a token
    public function destroy(Request $request, int $id): JsonResponse
    {
        $token = OnboardToken::where('agency_id', $request->user()->agency_id)->findOrFail($id);
        $token->delete();
        return response()->json(['success' => true]);
    }

    // PUBLIC: Validate a token (no auth required)
    public function validate_token(string $token): JsonResponse
    {
        $record = OnboardToken::where('token', $token)->first();

        if (!$record || !$record->isValid()) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired token'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'agency_name' => $record->agency->name,
                'provider_email' => $record->provider_email,
                'expires_at' => $record->expires_at,
            ],
        ]);
    }

    // PUBLIC: Search organizations for the token's agency (no auth required)
    public function organizations(Request $request, string $token): JsonResponse
    {
        $record = OnboardToken::where('token', $token)->first();

        if (!$record || !$record->isValid()) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired token'], 404);
        }

        $query = Organization::withoutGlobalScopes()
            ->where('agency_id', $record->agency_id)
            ->select(['id', 'name', 'npi', 'city', 'state']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('npi', 'like', "%{$search}%");
            });
        }

        $orgs = $query->orderBy('name')->limit(50)->get();

        return response()->json(['success' => true, 'data' => $orgs]);
    }

    // PUBLIC: Submit provider onboarding form (no auth required)
    // Supports full submission or step-by-step via 'step' parameter
    public function submit(Request $request, string $token): JsonResponse
    {
        $record = OnboardToken::where('token', $token)->first();

        if (!$record || !$record->isValid()) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired token'], 404);
        }

        $step = $request->input('step', 'complete');

        // Step-based validation
        $rules = match ($step) {
            'personal' => [
                'first_name' => 'required|string|max:100',
                'last_name' => 'required|string|max:100',
                'date_of_birth' => 'nullable|date',
                'gender' => 'nullable|string|in:male,female,non-binary,other,prefer_not_to_say',
                'email' => 'required|email',
                'phone' => 'nullable|string|max:20',
                'address_street' => 'nullable|string|max:255',
                'address_city' => 'nullable|string|max:100',
                'address_state' => 'nullable|string|max:2',
                'address_zip' => 'nullable|string|max:10',
                'organization_id' => 'nullable|integer|exists:organizations,id',
            ],
            'credentials' => [
                'credentials' => 'nullable|string|max:100',
                'npi' => 'nullable|string|size:10',
                'taxonomy' => 'nullable|string|max:20',
                'specialty' => 'nullable|string|max:100',
                'caqh_id' => 'nullable|string|max:20',
                'state_of_primary_license' => 'nullable|string|max:2',
                'medicaid_id' => 'nullable|string|max:30',
                'medicare_ptan' => 'nullable|string|max:30',
            ],
            'practice' => [
                'supervising_physician' => 'nullable|string|max:255',
                'supervising_physician_npi' => 'nullable|string|size:10',
                'collaborative_agreement_status' => 'nullable|string|in:active,expired,not_required,pending',
                'collaborative_agreement_expiry' => 'nullable|date',
                'practice_authority' => 'nullable|string|in:full,reduced,restricted',
                'prescriptive_authority' => 'nullable|boolean',
                'controlled_substance_authority' => 'nullable|boolean',
                'cs_schedule_authority' => 'nullable|string|max:50',
            ],
            'bio' => [
                'languages_spoken' => 'nullable|string|max:500',
                'bio' => 'nullable|string|max:2000',
            ],
            default => [ // 'complete' — all fields at once (backward compatible)
                'first_name' => 'required|string|max:100',
                'last_name' => 'required|string|max:100',
                'credentials' => 'nullable|string|max:100',
                'npi' => 'nullable|string|size:10',
                'taxonomy' => 'nullable|string|max:20',
                'specialty' => 'nullable|string|max:100',
                'email' => 'required|email',
                'phone' => 'nullable|string|max:20',
                'caqh_id' => 'nullable|string|max:20',
                'organization_id' => 'nullable|integer|exists:organizations,id',
                'date_of_birth' => 'nullable|date',
                'gender' => 'nullable|string',
                'address_street' => 'nullable|string|max:255',
                'address_city' => 'nullable|string|max:100',
                'address_state' => 'nullable|string|max:2',
                'address_zip' => 'nullable|string|max:10',
                'supervising_physician' => 'nullable|string|max:255',
                'supervising_physician_npi' => 'nullable|string|size:10',
                'collaborative_agreement_status' => 'nullable|string',
                'collaborative_agreement_expiry' => 'nullable|date',
                'practice_authority' => 'nullable|string',
                'prescriptive_authority' => 'nullable|boolean',
                'controlled_substance_authority' => 'nullable|boolean',
                'cs_schedule_authority' => 'nullable|string|max:50',
                'state_of_primary_license' => 'nullable|string|max:2',
                'medicaid_id' => 'nullable|string|max:30',
                'medicare_ptan' => 'nullable|string|max:30',
                'languages_spoken' => 'nullable|string|max:500',
                'bio' => 'nullable|string|max:2000',
            ],
        };

        $data = $request->validate($rules);

        // Check if provider already exists for this token (step-based resume)
        $existingProvider = Provider::withoutGlobalScopes()
            ->where('agency_id', $record->agency_id)
            ->where('email', $record->provider_email)
            ->first();

        if ($existingProvider && $step !== 'complete') {
            // Update existing provider with new step data
            $existingProvider->update($data);
            if ($step === 'bio' || $request->boolean('finalize')) {
                $existingProvider->update([
                    'onboarding_status' => 'complete',
                    'onboarding_completed_at' => now(),
                ]);
                $record->update(['used_at' => now()]);
            } else {
                $existingProvider->update(['onboarding_status' => 'in_progress']);
            }
            return response()->json(['success' => true, 'data' => $existingProvider, 'step' => $step]);
        }

        // Create new provider
        $provider = Provider::withoutGlobalScopes()->create(array_merge($data, [
            'agency_id' => $record->agency_id,
            'onboarding_status' => $step === 'complete' ? 'complete' : 'in_progress',
            'onboarding_completed_at' => $step === 'complete' ? now() : null,
        ]));

        if ($step === 'complete') {
            $record->update(['used_at' => now()]);
        }

        return response()->json(['success' => true, 'data' => $provider], 201);
    }
}

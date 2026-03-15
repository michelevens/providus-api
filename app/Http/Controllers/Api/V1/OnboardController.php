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
            return response()->json(['success' => false, 'error' => 'Invalid or expired token'], 404);
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
            return response()->json(['success' => false, 'error' => 'Invalid or expired token'], 404);
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
    public function submit(Request $request, string $token): JsonResponse
    {
        $record = OnboardToken::where('token', $token)->first();

        if (!$record || !$record->isValid()) {
            return response()->json(['success' => false, 'error' => 'Invalid or expired token'], 404);
        }

        $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'credentials' => 'nullable|string|max:50',
            'npi' => 'nullable|string|size:10',
            'taxonomy' => 'nullable|string|max:20',
            'specialty' => 'nullable|string|max:100',
            'email' => 'required|email',
            'phone' => 'nullable|string|max:20',
            'caqh_id' => 'nullable|string|max:20',
            'organization_id' => 'nullable|integer|exists:organizations,id',
        ]);

        $provider = Provider::withoutGlobalScopes()->create([
            'agency_id' => $record->agency_id,
            'organization_id' => $request->organization_id,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'credentials' => $request->credentials,
            'npi' => $request->npi,
            'taxonomy' => $request->taxonomy,
            'specialty' => $request->specialty,
            'email' => $request->email,
            'phone' => $request->phone,
            'caqh_id' => $request->caqh_id,
        ]);

        $record->update(['used_at' => now()]);

        return response()->json(['success' => true, 'data' => $provider], 201);
    }
}

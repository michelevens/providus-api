<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\EligibilityCheck;
use App\Services\StediService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EligibilityController extends Controller
{
    public function __construct(private StediService $stedi) {}

    // Admin: list eligibility check history
    public function index(Request $request): JsonResponse
    {
        $checks = EligibilityCheck::where('agency_id', $request->user()->agency_id)
            ->orderByDesc('created_at')
            ->paginate(50);
        return response()->json(['success' => true, 'data' => $checks]);
    }

    // PUBLIC: Run eligibility check via agency slug
    public function publicCheck(Request $request, string $slug): JsonResponse
    {
        $agency = Agency::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $config = $agency->config;

        if (!$config || !$config->stedi_api_key) {
            return response()->json(['success' => false, 'message' => 'Eligibility checking not configured for this agency'], 400);
        }

        $request->validate([
            'memberId' => 'required|string',
            'firstName' => 'required|string',
            'lastName' => 'required|string',
            'dateOfBirth' => 'required|date',
            'payerId' => 'required|string',
            'serviceType' => 'nullable|string',
        ]);

        // Check rate limit
        $monthlyCount = EligibilityCheck::where('agency_id', $agency->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        if ($monthlyCount >= $config->elig_monthly_limit) {
            return response()->json(['success' => false, 'message' => 'Monthly eligibility check limit reached'], 429);
        }

        $result = $this->stedi->checkEligibility($config, $request->all());

        // Log the check
        EligibilityCheck::create([
            'agency_id' => $agency->id,
            'insurance_payer' => $request->payerId,
            'member_id' => $request->memberId,
            'patient_first_name' => $request->firstName,
            'patient_last_name' => $request->lastName,
            'patient_dob' => $request->dateOfBirth,
            'stedi_response' => $result['data'] ?? null,
            'is_eligible' => $result['success'] && ($result['data']['eligible'] ?? false),
            'plan_name' => $result['data']['plan_name'] ?? null,
            'copay' => $result['data']['copay'] ?? null,
            'coinsurance' => $result['data']['coinsurance'] ?? null,
            'deductible' => $result['data']['deductible'] ?? null,
            'deductible_remaining' => $result['data']['deductible_remaining'] ?? null,
        ]);

        return response()->json($result);
    }
}

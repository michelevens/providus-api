<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ExclusionCheck;
use App\Models\Provider;
use App\Services\ExclusionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExclusionController extends Controller
{
    public function __construct(private ExclusionService $exclusionService) {}

    // List all exclusion checks for agency
    public function index(Request $request): JsonResponse
    {
        $query = ExclusionCheck::where('agency_id', $request->user()->agency_id)
            ->with('provider:id,first_name,last_name,npi,credentials');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($providerId = $request->input('provider_id')) {
            $query->where('provider_id', $providerId);
        }

        $checks = $query->orderByDesc('checked_at')->paginate(50);
        return response()->json(['success' => true, 'data' => $checks]);
    }

    // Run exclusion screening for a provider
    public function screen(Request $request, int $providerId): JsonResponse
    {
        $provider = Provider::where('agency_id', $request->user()->agency_id)
            ->findOrFail($providerId);

        $results = $this->exclusionService->runAllChecks(
            $provider->first_name,
            $provider->last_name,
            $provider->npi
        );

        // Save check results
        foreach ($results['checks'] as $type => $checkResult) {
            ExclusionCheck::updateOrCreate(
                [
                    'agency_id' => $request->user()->agency_id,
                    'provider_id' => $provider->id,
                    'check_type' => $type,
                ],
                [
                    'status' => ($checkResult['checked'] ?? false)
                        ? ($checkResult['is_excluded'] ? 'excluded' : 'clear')
                        : 'error',
                    'is_excluded' => $checkResult['is_excluded'] ?? false,
                    'checked_at' => now(),
                    'next_check_at' => now()->addDays(30),
                    'result_data' => $checkResult,
                    'checked_by' => $request->user()->id,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'data' => $results,
            'provider' => $provider->only('id', 'first_name', 'last_name', 'npi'),
        ]);
    }

    // Batch screen all providers
    public function screenAll(Request $request): JsonResponse
    {
        $providers = Provider::where('agency_id', $request->user()->agency_id)
            ->where('is_active', true)
            ->get();

        $results = [];
        foreach ($providers as $provider) {
            $checkResults = $this->exclusionService->runAllChecks(
                $provider->first_name,
                $provider->last_name,
                $provider->npi
            );

            foreach ($checkResults['checks'] as $type => $checkResult) {
                ExclusionCheck::updateOrCreate(
                    [
                        'agency_id' => $request->user()->agency_id,
                        'provider_id' => $provider->id,
                        'check_type' => $type,
                    ],
                    [
                        'status' => ($checkResult['checked'] ?? false)
                            ? ($checkResult['is_excluded'] ? 'excluded' : 'clear')
                            : 'error',
                        'is_excluded' => $checkResult['is_excluded'] ?? false,
                        'checked_at' => now(),
                        'next_check_at' => now()->addDays(30),
                        'result_data' => $checkResult,
                        'checked_by' => $request->user()->id,
                    ]
                );
            }

            $results[] = [
                'provider_id' => $provider->id,
                'name' => $provider->full_name,
                'overall' => $checkResults['overall_status'],
            ];
        }

        return response()->json(['success' => true, 'data' => $results]);
    }

    // Get screening summary/dashboard
    public function summary(Request $request): JsonResponse
    {
        $agencyId = $request->user()->agency_id;

        $totalProviders = Provider::where('agency_id', $agencyId)->where('is_active', true)->count();
        $screened = ExclusionCheck::where('agency_id', $agencyId)
            ->distinct('provider_id')->count('provider_id');
        $excluded = ExclusionCheck::where('agency_id', $agencyId)
            ->where('is_excluded', true)->distinct('provider_id')->count('provider_id');
        $needsRecheck = ExclusionCheck::where('agency_id', $agencyId)
            ->where('next_check_at', '<', now())->distinct('provider_id')->count('provider_id');
        $neverScreened = $totalProviders - $screened;

        return response()->json([
            'success' => true,
            'data' => compact('totalProviders', 'screened', 'excluded', 'needsRecheck', 'neverScreened'),
        ]);
    }
}

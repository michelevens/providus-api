<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CaqhService;
use App\Services\NppesService;
use App\Services\StediService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProxyController extends Controller
{
    public function __construct(
        private NppesService $nppes,
        private StediService $stedi,
        private CaqhService $caqh,
    ) {}

    // NPPES NPI Lookup
    public function nppesLookup(string $npi): JsonResponse
    {
        $result = $this->nppes->lookupNpi($npi);
        return response()->json($result);
    }

    // NPPES Provider Search
    public function nppesSearch(Request $request): JsonResponse
    {
        $result = $this->nppes->searchProviders($request->all());
        return response()->json($result);
    }

    // Stedi Eligibility Check
    public function stediEligibility(Request $request): JsonResponse
    {
        $request->validate([
            'memberId' => 'required|string',
            'firstName' => 'required|string',
            'lastName' => 'required|string',
            'dateOfBirth' => 'required|date',
            'payerId' => 'required|string',
            'serviceType' => 'nullable|string',
        ]);

        $config = $request->user()->agency->config;
        $result = $this->stedi->checkEligibility($config, $request->all());
        return response()->json($result);
    }

    // CAQH ProView operations
    public function caqh(Request $request, string $action): JsonResponse
    {
        $config = $request->user()->agency->config;
        $result = $this->caqh->call($config, $action, $request->all());
        return response()->json($result);
    }
}

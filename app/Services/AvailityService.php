<?php

namespace App\Services;

use App\Models\EligibilityCheck;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AvailityService
{
    // Availity API endpoints
    private const AUTH_URL_PROD = 'https://api.availity.com/availity/v1/token';
    private const AUTH_URL_SANDBOX = 'https://api.availity.com/availity/v1/token';
    private const API_BASE_PROD = 'https://api.availity.com/availity/v1';
    private const API_BASE_SANDBOX = 'https://api.availity.com/availity/v1';

    // Availity payer IDs for eligibility (270/271)
    private const PAYER_MAP = [
        'Aetna'                   => '60054',
        'Anthem BCBS'             => 'ANTHEM',
        'BCBS of Arizona'         => 'BCBSAZ',
        'BCBS of Florida'         => 'BCBSFL',
        'Florida Blue'            => 'BCBSFL',
        'BlueCross BlueShield'    => 'BCBSF',
        'Cigna'                   => '62308',
        'Humana'                  => '61101',
        'UnitedHealthcare'        => '87726',
        'Medicare'                => 'CMSF',
        'Medicaid'                => 'SKFL0', // FL Medicaid, varies by state
        'Tricare'                 => '99726',
        'VACCN'                   => '99726',
        'Optum'                   => '87726',
        'Carelon'                 => 'CAREL',
        'Molina Healthcare'       => 'MOLIN',
        'Oscar Health'            => 'OSCAR',
        'CarePlus'                => 'CRPLS',
        'Quest Behavioral'        => 'QUEST',
        'Regence BCBS'            => 'REGBC',
        'Moda Health'             => 'MODAH',
        'Providence Health Plan'  => 'PROVH',
        'Select Health'           => 'SELHT',
    ];

    /**
     * Get OAuth2 access token (cached for 4 minutes — tokens last 5 min)
     */
    private function getAccessToken(array $config): ?string
    {
        $cacheKey = 'availity_token_' . ($config['availity_client_id'] ?? 'default');

        return Cache::remember($cacheKey, 240, function () use ($config) {
            $clientId = $config['availity_client_id'] ?? null;
            $clientSecret = $config['availity_client_secret'] ?? null;

            if (!$clientId || !$clientSecret) {
                return null;
            }

            $authUrl = ($config['availity_env'] ?? 'production') === 'sandbox'
                ? self::AUTH_URL_SANDBOX
                : self::AUTH_URL_PROD;

            try {
                $response = Http::asForm()->post($authUrl, [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $clientId,
                    'client_secret' => $clientSecret,
                    'scope'         => 'hipaa',
                ]);

                if ($response->successful()) {
                    return $response->json('access_token');
                }

                Log::warning('Availity auth failed', [
                    'status' => $response->status(),
                    'body'   => $response->json(),
                ]);
                return null;
            } catch (\Throwable $e) {
                Log::error('Availity auth error: ' . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Get API base URL based on environment
     */
    private function getBaseUrl(array $config): string
    {
        return ($config['availity_env'] ?? 'production') === 'sandbox'
            ? self::API_BASE_SANDBOX
            : self::API_BASE_PROD;
    }

    /**
     * Resolve payer name to Availity payer ID
     */
    private function resolvePayerId(string $payerName): ?string
    {
        // Exact match first
        if (isset(self::PAYER_MAP[$payerName])) {
            return self::PAYER_MAP[$payerName];
        }

        // Fuzzy match
        $lower = strtolower($payerName);
        foreach (self::PAYER_MAP as $name => $id) {
            if (str_contains(strtolower($name), $lower) || str_contains($lower, strtolower($name))) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Real-time eligibility check (270/271)
     */
    public function checkEligibility(array $config, array $data): array
    {
        // Handle test mode
        if (!empty($data['test'])) {
            return $this->mockEligibilityResponse($data);
        }

        $token = $this->getAccessToken($config);
        if (!$token) {
            return [
                'success' => false,
                'error'   => 'Availity not configured or authentication failed. Check your Client ID and Secret in Settings > Integrations.',
            ];
        }

        $payerName = $data['payer_name'] ?? $data['insurance'] ?? '';
        $payerId = $data['payer_id'] ?? $this->resolvePayerId($payerName);

        if (!$payerId) {
            return [
                'success' => false,
                'error'   => "Payer \"{$payerName}\" not found in Availity payer directory. Check the payer name or provide the Availity Payer ID.",
            ];
        }

        $baseUrl = $this->getBaseUrl($config);

        // Build 270 request payload
        $payload = [
            'payerID'              => $payerId,
            'submitterID'          => $config['availity_customer_id'] ?? '',
            'providerNPI'          => $data['provider_npi'] ?? $config['agency_npi'] ?? '',
            'providerLastName'     => $config['agency_name'] ?? 'Provider',
            'memberId'             => $data['member_id'] ?? '',
            'patientFirstName'     => $data['first_name'] ?? '',
            'patientLastName'      => $data['last_name'] ?? '',
            'patientBirthDate'     => $data['date_of_birth'] ?? $data['dob'] ?? '',
            'serviceType'          => $data['service_type'] ?? 'MH', // Mental Health default
        ];

        try {
            $response = Http::withToken($token)
                ->timeout(30)
                ->post("{$baseUrl}/coverages", $payload);

            $body = $response->json();

            if ($response->failed()) {
                $error = $body['message'] ?? $body['error'] ?? 'Eligibility check failed';
                $this->logCheck($config['agency_id'] ?? null, $data, $body, 'error', $error);
                return ['success' => false, 'error' => $error];
            }

            $result = $this->parseEligibilityResponse($body, $payerName);
            $this->logCheck($config['agency_id'] ?? null, $data, $body, $result['status'], null);

            return ['success' => true, 'eligibility' => $result];
        } catch (\Throwable $e) {
            Log::error('Availity eligibility error: ' . $e->getMessage());
            $this->logCheck($config['agency_id'] ?? null, $data, null, 'error', $e->getMessage());
            return ['success' => false, 'error' => 'Availity service temporarily unavailable.'];
        }
    }

    /**
     * Claim status inquiry (276/277)
     */
    public function checkClaimStatus(array $config, array $data): array
    {
        $token = $this->getAccessToken($config);
        if (!$token) {
            return ['success' => false, 'error' => 'Availity not configured or authentication failed.'];
        }

        $payerName = $data['payer_name'] ?? '';
        $payerId = $data['payer_id'] ?? $this->resolvePayerId($payerName);

        if (!$payerId) {
            return ['success' => false, 'error' => "Payer \"{$payerName}\" not found in Availity."];
        }

        $baseUrl = $this->getBaseUrl($config);

        $payload = [
            'payerID'          => $payerId,
            'submitterID'      => $config['availity_customer_id'] ?? '',
            'providerNPI'      => $data['provider_npi'] ?? $config['agency_npi'] ?? '',
            'claimNumber'      => $data['claim_number'] ?? '',
            'patientFirstName' => $data['first_name'] ?? '',
            'patientLastName'  => $data['last_name'] ?? '',
            'patientBirthDate' => $data['date_of_birth'] ?? '',
            'dateOfService'    => $data['date_of_service'] ?? '',
            'chargeAmount'     => $data['charge_amount'] ?? '',
        ];

        try {
            $response = Http::withToken($token)
                ->timeout(30)
                ->post("{$baseUrl}/claim-statuses", $payload);

            $body = $response->json();

            if ($response->failed()) {
                $error = $body['message'] ?? $body['error'] ?? 'Claim status check failed';
                return ['success' => false, 'error' => $error];
            }

            return [
                'success'     => true,
                'claimStatus' => [
                    'status'         => $body['status'] ?? $body['claimStatus'] ?? 'unknown',
                    'statusCategory' => $body['statusCategory'] ?? $body['categoryCode'] ?? '',
                    'statusCode'     => $body['statusCode'] ?? '',
                    'effectiveDate'  => $body['effectiveDate'] ?? '',
                    'totalCharge'    => $body['totalCharge'] ?? $body['chargeAmount'] ?? 0,
                    'paidAmount'     => $body['paidAmount'] ?? 0,
                    'checkNumber'    => $body['checkNumber'] ?? $body['traceNumber'] ?? '',
                    'paidDate'       => $body['paidDate'] ?? $body['adjudicatedDate'] ?? '',
                    'raw'            => $body,
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('Availity claim status error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Availity service temporarily unavailable.'];
        }
    }

    // ─── ERA file pickup (X12 835) ─────────────────────────────────
    //
    // Availity's EDI File Management product exposes a flat list of
    // every 835 file that arrived for an agency, accessed via the
    // X12 file pickup API. The exact endpoint path varies by Availity
    // product tier and account configuration — see PLACEHOLDER notes
    // below. The request/response shape here matches Availity's
    // documented v1 transmission/file pattern; adjust once you have
    // a sandbox call against your tier.

    /**
     * List ERA (X12 835) files available for download from Availity.
     *
     * @param array $config Agency config (availity_client_id, _secret, _customer_id, _env).
     * @param array $filters Optional filters: 'from' (ISO date), 'to' (ISO date),
     *                       'received_after' (ISO datetime — only new files since last sync).
     * @return array { success: bool, files: array<{file_id, received_at, payer, size_bytes, claim_count}>, error?: string }
     */
    public function listEraFiles(array $config, array $filters = []): array
    {
        $token = $this->getAccessToken($config);
        if (!$token) {
            return [
                'success' => false,
                'error'   => 'Availity not configured or authentication failed. Check your Client ID and Secret in Settings > Integrations.',
                'files'   => [],
            ];
        }

        $baseUrl = $this->getBaseUrl($config);

        // PLACEHOLDER ENDPOINT — verify against Availity API docs for
        // your account tier. Common variants:
        //   GET /v1/x12-receivers/{customer_id}/files?type=835
        //   GET /v1/transmissions?type=ERA&customer_id={customer_id}
        // Some tiers prefix with /availity (already in base URL),
        // others nest under /availity/v1/edi/files.
        $endpoint = "{$baseUrl}/transmissions";

        $query = [
            'type'        => 'ERA',
            'customer_id' => $config['availity_customer_id'] ?? '',
        ];
        if (!empty($filters['from'])) {
            $query['from_date'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $query['to_date'] = $filters['to'];
        }
        if (!empty($filters['received_after'])) {
            // Cursor-style filter for nightly syncs — pulls only files
            // received since the last successful run.
            $query['received_after'] = $filters['received_after'];
        }

        try {
            $response = Http::withToken($token)
                ->timeout(60)
                ->get($endpoint, $query);

            if ($response->failed()) {
                $body = $response->json();
                $error = $body['message'] ?? $body['error'] ?? "Availity ERA list failed (HTTP {$response->status()})";
                Log::warning('Availity ERA list failed', [
                    'status' => $response->status(),
                    'body'   => $body,
                ]);
                return ['success' => false, 'error' => $error, 'files' => []];
            }

            // PLACEHOLDER RESPONSE PARSING — adjust field names to
            // match the real Availity transmission/file schema.
            // Expected shape per their X12 file pickup docs:
            //   { transmissions: [ { id, receivedDate, payerName, byteSize, claimCount, ... } ] }
            $body = $response->json();
            $rawList = $body['transmissions'] ?? $body['files'] ?? $body['data'] ?? [];

            $files = [];
            foreach ($rawList as $row) {
                $files[] = [
                    'file_id'     => $row['id'] ?? $row['fileId'] ?? $row['transmissionId'] ?? null,
                    'received_at' => $row['receivedDate'] ?? $row['received_at'] ?? $row['createdDate'] ?? null,
                    'payer'       => $row['payerName'] ?? $row['payer'] ?? null,
                    'size_bytes'  => $row['byteSize'] ?? $row['size'] ?? 0,
                    'claim_count' => $row['claimCount'] ?? $row['claim_count'] ?? null,
                ];
            }

            return ['success' => true, 'files' => $files];
        } catch (\Throwable $e) {
            Log::error('Availity ERA list error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Availity service temporarily unavailable.', 'files' => []];
        }
    }

    /**
     * Download a single ERA file's raw 835 content by file ID.
     * Returns the X12 EDI string body, ready to feed into Era835Importer.
     *
     * @param array $config Agency config.
     * @param string $fileId File ID from listEraFiles().
     * @return array { success: bool, content?: string, error?: string }
     */
    public function downloadEraFile(array $config, string $fileId): array
    {
        $token = $this->getAccessToken($config);
        if (!$token) {
            return ['success' => false, 'error' => 'Availity not configured or authentication failed.'];
        }

        $baseUrl = $this->getBaseUrl($config);

        // PLACEHOLDER ENDPOINT — verify against Availity API docs.
        // Common variants:
        //   GET /v1/transmissions/{file_id}/content
        //   GET /v1/x12-files/{file_id}
        // Response may be raw text/plain (the X12 string) OR base64-
        // encoded inside a JSON wrapper. Handle both below.
        $endpoint = "{$baseUrl}/transmissions/{$fileId}/content";

        try {
            $response = Http::withToken($token)
                ->timeout(120) // 835s can be multi-megabyte
                ->get($endpoint);

            if ($response->failed()) {
                $body = $response->json();
                $error = $body['message'] ?? $body['error'] ?? "Availity ERA download failed (HTTP {$response->status()})";
                Log::warning('Availity ERA download failed', [
                    'file_id' => $fileId,
                    'status'  => $response->status(),
                    'body'    => $body,
                ]);
                return ['success' => false, 'error' => $error];
            }

            // If text/plain, response body IS the X12 string. If JSON
            // wrapper with a base64 payload, unwrap it. Detect by
            // content-type header.
            $contentType = $response->header('Content-Type') ?? '';
            if (str_contains($contentType, 'application/json')) {
                $body = $response->json();
                $raw = $body['content'] ?? $body['data'] ?? null;
                if ($raw && !str_contains($raw, '~')) {
                    // No segment terminator — assume base64.
                    $raw = base64_decode($raw, true);
                    if ($raw === false) {
                        return ['success' => false, 'error' => 'Availity returned malformed file content.'];
                    }
                }
                return ['success' => true, 'content' => $raw];
            }

            return ['success' => true, 'content' => $response->body()];
        } catch (\Throwable $e) {
            Log::error('Availity ERA download error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Availity service temporarily unavailable.'];
        }
    }

    /**
     * Parse Availity 271 eligibility response into standardized format
     */
    private function parseEligibilityResponse(array $body, string $payerName): array
    {
        $result = [
            'carrier'       => $payerName,
            'plan_name'     => '',
            'status'        => 'unknown',
            'network'       => 'unknown',
            'effective_date' => null,
            'termination_date' => null,
            'mental_health' => [
                'copay'              => null,
                'coinsurance'        => null,
                'deductible'         => null,
                'deductible_remaining' => null,
                'out_of_pocket_max'  => null,
                'visits_remaining'   => null,
                'prior_auth_required' => false,
            ],
        ];

        // Availity response structure varies, handle common formats
        $coverage = $body['coverages'][0] ?? $body;

        $result['plan_name'] = $coverage['planName'] ?? $coverage['groupName'] ?? '';
        $result['effective_date'] = $coverage['effectiveDate'] ?? $coverage['eligibilityBeginDate'] ?? null;
        $result['termination_date'] = $coverage['terminationDate'] ?? $coverage['eligibilityEndDate'] ?? null;

        // Status
        $statusCode = $coverage['statusCode'] ?? $coverage['status'] ?? '';
        if (in_array($statusCode, ['1', 'active', 'Active'])) {
            $result['status'] = 'active';
        } elseif (in_array($statusCode, ['6', 'inactive', 'Inactive'])) {
            $result['status'] = 'inactive';
        }

        // Network
        if (!empty($coverage['inPlanNetwork']) || !empty($coverage['inNetwork'])) {
            $result['network'] = 'in_network';
        }

        // Benefits
        foreach ($coverage['benefits'] ?? $coverage['benefitsInformation'] ?? [] as $benefit) {
            $code = $benefit['code'] ?? $benefit['benefitCode'] ?? '';
            $serviceTypes = $benefit['serviceTypeCodes'] ?? $benefit['serviceTypes'] ?? [];
            $isMH = empty($serviceTypes) || array_intersect(['MH', '30', 'A4', 'A5', 'A6'], $serviceTypes);
            $isInNetwork = ($benefit['inPlanNetworkIndicator'] ?? $benefit['inNetwork'] ?? '') !== 'N';

            if (!$isMH || !$isInNetwork) continue;

            $amount = $benefit['benefitAmount'] ?? $benefit['amount'] ?? null;
            $percent = $benefit['benefitPercent'] ?? $benefit['percent'] ?? null;

            switch ($code) {
                case 'B': // Copay
                    if ($amount !== null) $result['mental_health']['copay'] = (float) $amount;
                    break;
                case 'A': // Coinsurance
                    if ($percent !== null) $result['mental_health']['coinsurance'] = round((float) $percent * 100);
                    break;
                case 'C': // Deductible
                    if ($amount !== null) $result['mental_health']['deductible'] = (float) $amount;
                    break;
                case 'G': // Out of pocket max
                    if ($amount !== null) $result['mental_health']['out_of_pocket_max'] = (float) $amount;
                    break;
                case 'F': // Visits remaining
                    if ($amount !== null) $result['mental_health']['visits_remaining'] = (int) $amount;
                    break;
            }

            // Prior auth
            if (!empty($benefit['authRequired']) || ($benefit['authOrCertIndicator'] ?? '') === 'Y') {
                $result['mental_health']['prior_auth_required'] = true;
            }
        }

        return $result;
    }

    /**
     * Mock response for testing connection
     */
    private function mockEligibilityResponse(array $data): array
    {
        return [
            'success'     => true,
            'test'        => true,
            'eligibility' => [
                'carrier'       => $data['payer_name'] ?? 'Test Payer',
                'plan_name'     => 'Test Plan - Premium PPO',
                'status'        => 'active',
                'network'       => 'in_network',
                'effective_date' => '2025-01-01',
                'mental_health' => [
                    'copay'              => 25.00,
                    'coinsurance'        => 20,
                    'deductible'         => 500.00,
                    'deductible_remaining' => 350.00,
                    'out_of_pocket_max'  => 3000.00,
                    'visits_remaining'   => 42,
                    'prior_auth_required' => false,
                ],
            ],
        ];
    }

    /**
     * Log eligibility check to database
     */
    private function logCheck(?int $agencyId, array $data, ?array $response, string $status, ?string $error): void
    {
        if (!$agencyId) return;

        try {
            EligibilityCheck::create([
                'agency_id'          => $agencyId,
                'insurance'          => $data['payer_name'] ?? $data['insurance'] ?? null,
                'member_id'          => $data['member_id'] ?? null,
                'patient_dob'        => $data['date_of_birth'] ?? $data['dob'] ?? null,
                'patient_first_name' => $data['first_name'] ?? null,
                'patient_last_name'  => $data['last_name'] ?? null,
                'provider'           => 'availity',
                'stedi_response'     => $response, // reuse existing column
                'status'             => $status,
                'error_message'      => $error,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to log eligibility check: ' . $e->getMessage());
        }
    }
}

<?php

namespace App\Services;

use App\Models\AgencyConfig;
use App\Models\EligibilityCheck;
use Illuminate\Support\Facades\Http;

class StediService
{
    private const ENDPOINT = 'https://healthcare.us.stedi.com/2024-04-01/change/medicalnetwork/eligibility/v3';

    private const PAYER_MAP = [
        'Aetna' => '60054',
        'BlueCross BlueShield' => 'BCBSF',
        'Cigna' => '62308',
        'UnitedHealthcare' => '87726',
        'Humana' => '61101',
        'Oscar' => 'OSCAR',
        'Ambetter' => 'AMB01',
        'Medicare' => 'CMS',
        'Tricare' => 'TRIC',
        'Molina' => 'MOLIN',
    ];

    public function checkEligibility(AgencyConfig $config, array $data): array
    {
        $payerId = self::PAYER_MAP[$data['insurance']] ?? null;
        if (!$payerId) {
            return ['success' => false, 'error' => 'Insurance carrier not supported for real-time verification.'];
        }

        if (!$config->stedi_api_key) {
            return ['success' => false, 'error' => 'Eligibility verification is not configured.'];
        }

        // Rate limit check
        $monthlyCount = EligibilityCheck::where('agency_id', $config->agency_id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        if ($monthlyCount >= $config->elig_monthly_limit) {
            return ['success' => false, 'error' => 'Monthly eligibility check limit reached.'];
        }

        $apiKey = $config->stedi_api_key;
        $isTest = str_starts_with($apiKey, 'test_');

        $payload = [
            'payer' => ['id' => $payerId],
            'encounter' => ['serviceTypeCodes' => ['MH']],
            'provider' => [
                'organizationName' => $config->stedi_org_name ?? 'Credentik',
                'npi' => $config->stedi_npi ?? '1234567890',
            ],
            'subscriber' => [
                'memberId' => trim($data['member_id']),
                'dateOfBirth' => $data['dob'],
            ],
        ];

        if (!empty($data['first_name'])) $payload['subscriber']['firstName'] = $data['first_name'];
        if (!empty($data['last_name'])) $payload['subscriber']['lastName'] = $data['last_name'];

        // Use mock data for test keys
        if ($isTest) {
            $payload['payer']['id'] = 'aetna';
            $payload['subscriber'] = [
                'firstName' => $data['first_name'] ?? 'John',
                'lastName' => $data['last_name'] ?? 'Doe',
                'dateOfBirth' => '1960-01-15',
                'memberId' => '123456789',
            ];
            $payload['encounter']['serviceTypeCodes'] = ['30'];
        }

        try {
            $response = Http::withToken($apiKey)
                ->post(self::ENDPOINT, $payload);

            $body = $response->json();

            if ($response->failed()) {
                $error = $body['message'] ?? $body['error'] ?? 'Verification failed';
                $this->logCheck($config->agency_id, $data, null, 'error', $error);
                return ['success' => false, 'error' => "Unable to verify coverage: {$error}"];
            }

            $result = $this->parseResponse($body, $data['insurance']);
            $this->logCheck($config->agency_id, $data, $body, $result['status'], null);

            return ['success' => true, 'eligibility' => $result];
        } catch (\Throwable $e) {
            $this->logCheck($config->agency_id, $data, null, 'error', $e->getMessage());
            return ['success' => false, 'error' => 'Verification service temporarily unavailable.'];
        }
    }

    private function parseResponse(array $body, string $carrierName): array
    {
        $result = [
            'carrier' => $carrierName,
            'plan_name' => '',
            'status' => 'unknown',
            'network' => 'unknown',
            'mental_health' => [
                'copay' => null, 'coinsurance' => null, 'deductible' => null,
                'deductible_remaining' => null, 'out_of_pocket_max' => null,
            ],
        ];

        if (!empty($body['planInformation'][0]['planDescription'])) {
            $result['plan_name'] = $body['planInformation'][0]['planDescription'];
        }

        foreach ($body['benefitsInformation'] ?? [] as $b) {
            $code = $b['code'] ?? '';
            $isInNetwork = ($b['inPlanNetworkIndicator'] ?? '') === 'Y';
            $serviceTypes = $b['serviceTypeCodes'] ?? [];
            $isMH = empty($serviceTypes) || in_array('MH', $serviceTypes) || in_array('30', $serviceTypes);

            if ($code === '1') {
                $result['status'] = 'active';
                if ($isInNetwork) $result['network'] = 'in_network';
            }
            if ($code === '6') $result['status'] = 'inactive';
            if ($code === 'B' && isset($b['benefitAmount']) && $isMH && $isInNetwork) {
                $result['mental_health']['copay'] = (float) $b['benefitAmount'];
            }
            if ($code === 'A' && isset($b['benefitPercent']) && $isMH && $isInNetwork) {
                $result['mental_health']['coinsurance'] = round((float) $b['benefitPercent'] * 100);
            }
            if ($code === 'C' && isset($b['benefitAmount']) && $isInNetwork) {
                $result['mental_health']['deductible'] = (float) $b['benefitAmount'];
            }
            if ($code === 'G' && isset($b['benefitAmount']) && $isInNetwork) {
                $result['mental_health']['out_of_pocket_max'] = (float) $b['benefitAmount'];
            }
        }

        return $result;
    }

    private function logCheck(int $agencyId, array $data, ?array $response, string $status, ?string $error): void
    {
        EligibilityCheck::create([
            'agency_id' => $agencyId,
            'insurance' => $data['insurance'] ?? null,
            'member_id' => $data['member_id'] ?? null,
            'patient_dob' => $data['dob'] ?? null,
            'patient_first_name' => $data['first_name'] ?? null,
            'patient_last_name' => $data['last_name'] ?? null,
            'stedi_response' => $response,
            'status' => $status,
            'error_message' => $error,
        ]);
    }
}

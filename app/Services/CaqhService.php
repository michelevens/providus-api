<?php

namespace App\Services;

use App\Models\AgencyConfig;
use Illuminate\Support\Facades\Http;

class CaqhService
{
    public function call(AgencyConfig $config, string $action, array $params = []): array
    {
        if (!$config->caqh_org_id || !$config->caqh_username || !$config->caqh_password) {
            return ['success' => false, 'error' => 'CAQH credentials not configured'];
        }

        $baseUrl = $config->caqh_environment === 'sandbox'
            ? 'https://proview-demo.caqh.org/RosterAPI/api'
            : 'https://proview.caqh.org/RosterAPI/api';

        $endpoints = [
            'roster_status' => ['method' => 'GET', 'path' => "/Roster?Product=PV&Organization_Id={$config->caqh_org_id}&Caqh_Provider_Id={$params['caqh_provider_id']}"],
            'roster_add' => ['method' => 'POST', 'path' => '/Roster', 'body' => [
                'Product' => 'PV', 'Organization_Id' => $config->caqh_org_id,
                'Provider_First_Name' => $params['first_name'] ?? '',
                'Provider_Last_Name' => $params['last_name'] ?? '',
                'Provider_NPI' => $params['npi'] ?? '',
            ]],
            'roster_remove' => ['method' => 'DELETE', 'path' => "/Roster?Product=PV&Organization_Id={$config->caqh_org_id}&Caqh_Provider_Id={$params['caqh_provider_id']}"],
            'provider_status' => ['method' => 'GET', 'path' => "/providerstatus?Product=PV&Organization_Id={$config->caqh_org_id}&Caqh_Provider_Id={$params['caqh_provider_id']}"],
            'provider_status_npi' => ['method' => 'GET', 'path' => "/providerstatus?Product=PV&Organization_Id={$config->caqh_org_id}&NPI_Provider_Id={$params['npi']}"],
            'attestation_status' => ['method' => 'GET', 'path' => "/providerstatus?Product=PV&Organization_Id={$config->caqh_org_id}&Caqh_Provider_Id={$params['caqh_provider_id']}&Attestation=true"],
            'provider_profile' => ['method' => 'GET', 'path' => "/providerprofile?Product=PV&Organization_Id={$config->caqh_org_id}&Caqh_Provider_Id={$params['caqh_provider_id']}"],
        ];

        $ep = $endpoints[$action] ?? null;
        if (!$ep) return ['success' => false, 'error' => "Unknown CAQH action: {$action}"];

        try {
            $http = Http::withBasicAuth($config->caqh_username, $config->caqh_password)
                ->acceptJson();

            $url = $baseUrl . $ep['path'];

            $response = match ($ep['method']) {
                'POST' => $http->post($url, $ep['body'] ?? []),
                'DELETE' => $http->delete($url),
                default => $http->get($url),
            };

            if ($response->failed()) {
                return ['success' => false, 'error' => "CAQH API returned {$response->status()}: {$response->body()}"];
            }

            return ['success' => true, 'data' => $response->json()];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

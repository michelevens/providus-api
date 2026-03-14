<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class NppesService
{
    private const BASE_URL = 'https://npiregistry.cms.hhs.gov/api/?version=2.1';

    public function lookupNpi(string $npi): ?array
    {
        if (!preg_match('/^\d{10}$/', $npi)) {
            throw new \InvalidArgumentException('NPI must be a 10-digit number');
        }

        $response = Http::get(self::BASE_URL . "&number={$npi}");
        $data = $response->json();

        if (($data['result_count'] ?? 0) === 0) return null;
        return $this->parseProvider($data['results'][0]);
    }

    public function searchProviders(array $params): array
    {
        $query = [];
        if (!empty($params['first_name'])) $query['first_name'] = $params['first_name'];
        if (!empty($params['last_name'])) $query['last_name'] = $params['last_name'];
        if (!empty($params['state'])) $query['state'] = $params['state'];
        if (!empty($params['city'])) $query['city'] = $params['city'];
        if (!empty($params['taxonomy_desc'])) $query['taxonomy_description'] = $params['taxonomy_desc'];
        if (!empty($params['postal_code'])) $query['postal_code'] = $params['postal_code'];
        $query['limit'] = min($params['limit'] ?? 50, 200);
        $query['enumeration_type'] = 'NPI-1';

        $url = self::BASE_URL . '&' . http_build_query($query);
        $response = Http::get($url);
        $data = $response->json();

        return array_map([$this, 'parseProvider'], $data['results'] ?? []);
    }

    public function searchByTaxonomy(string $keyword, ?string $state = null, int $limit = 50): array
    {
        $query = [
            'taxonomy_description' => $keyword,
            'limit' => min($limit, 200),
        ];
        if ($state) $query['state'] = $state;

        $url = self::BASE_URL . '&' . http_build_query($query);
        $response = Http::get($url);
        $data = $response->json();

        return array_map([$this, 'parseProvider'], $data['results'] ?? []);
    }

    private function parseProvider(array $result): array
    {
        $basic = $result['basic'] ?? [];
        $taxonomies = $result['taxonomies'] ?? [];
        $addresses = $result['addresses'] ?? [];
        $practiceAddr = collect($addresses)->firstWhere('address_purpose', 'LOCATION') ?? $addresses[0] ?? [];
        $mailingAddr = collect($addresses)->firstWhere('address_purpose', 'MAILING') ?? [];
        $primaryTax = collect($taxonomies)->firstWhere('primary', true) ?? $taxonomies[0] ?? [];

        return [
            'npi' => $result['number'] ?? '',
            'entity_type' => ($result['enumeration_type'] ?? '') === 'NPI-1' ? 'individual' : 'organization',
            'first_name' => $basic['first_name'] ?? '',
            'last_name' => $basic['last_name'] ?? '',
            'middle_name' => $basic['middle_name'] ?? '',
            'credential' => $basic['credential'] ?? '',
            'gender' => $basic['gender'] ?? '',
            'status' => ($basic['status'] ?? '') === 'A' ? 'Active' : ($basic['status'] ?? ''),
            'enumeration_date' => $basic['enumeration_date'] ?? '',
            'last_updated' => $basic['last_updated'] ?? '',
            'taxonomy_code' => $primaryTax['code'] ?? '',
            'taxonomy_desc' => $primaryTax['desc'] ?? '',
            'taxonomy_license' => $primaryTax['license'] ?? '',
            'taxonomy_state' => $primaryTax['state'] ?? '',
            'all_taxonomies' => array_map(fn($t) => [
                'code' => $t['code'] ?? '', 'desc' => $t['desc'] ?? '',
                'license' => $t['license'] ?? '', 'state' => $t['state'] ?? '',
                'primary' => $t['primary'] ?? false,
            ], $taxonomies),
            'address1' => $practiceAddr['address_1'] ?? '',
            'address2' => $practiceAddr['address_2'] ?? '',
            'city' => $practiceAddr['city'] ?? '',
            'state' => $practiceAddr['state'] ?? '',
            'zip' => $practiceAddr['postal_code'] ?? '',
            'phone' => $practiceAddr['telephone_number'] ?? '',
            'fax' => $practiceAddr['fax_number'] ?? '',
        ];
    }
}

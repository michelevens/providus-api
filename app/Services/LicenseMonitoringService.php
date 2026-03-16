<?php

namespace App\Services;

use App\Models\DeaRegistration;
use App\Models\License;
use App\Models\LicenseVerification;
use App\Models\Provider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class LicenseMonitoringService
{
    public function __construct(private NppesService $nppesService) {}

    /**
     * Verify a single license against NPPES taxonomy/license data.
     */
    public function verifyLicense(License $license, ?int $verifiedBy = null): LicenseVerification
    {
        $provider = $license->provider;
        if (!$provider || !$provider->npi) {
            return $this->createVerification($license, [
                'status' => 'error',
                'verification_source' => 'nppes',
                'discrepancies' => 'Provider has no NPI on file',
                'verified_by' => $verifiedBy,
            ]);
        }

        try {
            $nppesData = $this->nppesService->lookupNpi($provider->npi);

            if (!$nppesData) {
                return $this->createVerification($license, [
                    'status' => 'error',
                    'verification_source' => 'nppes',
                    'source_data' => ['error' => 'NPI not found in NPPES'],
                    'discrepancies' => 'NPI not found in NPPES registry',
                    'verified_by' => $verifiedBy,
                ]);
            }

            // Check taxonomies for matching license
            $taxonomies = $nppesData['all_taxonomies'] ?? [];
            $discrepancies = [];
            $matchedTaxonomy = null;

            foreach ($taxonomies as $tax) {
                // Match by state + license number if available
                if ($tax['state'] === $license->state && !empty($tax['license'])) {
                    $matchedTaxonomy = $tax;
                    if ($tax['license'] !== $license->license_number) {
                        $discrepancies[] = "License number mismatch: NPPES has '{$tax['license']}', record has '{$license->license_number}'";
                    }
                    break;
                }
                // Match by state only
                if ($tax['state'] === $license->state) {
                    $matchedTaxonomy = $tax;
                }
            }

            // Check NPI status
            if ($nppesData['status'] !== 'Active') {
                $discrepancies[] = "NPI status is '{$nppesData['status']}' (not Active)";
            }

            // Name verification
            $npiFName = strtolower(trim($nppesData['first_name'] ?? ''));
            $npiLName = strtolower(trim($nppesData['last_name'] ?? ''));
            $provFName = strtolower(trim($provider->first_name));
            $provLName = strtolower(trim($provider->last_name));
            if ($npiFName !== $provFName || $npiLName !== $provLName) {
                $discrepancies[] = "Name mismatch: NPPES has '{$nppesData['first_name']} {$nppesData['last_name']}'";
            }

            $status = empty($discrepancies) ? 'verified' : 'mismatch';

            return $this->createVerification($license, [
                'status' => $status,
                'verification_source' => 'nppes',
                'source_data' => $nppesData,
                'source_name' => trim(($nppesData['first_name'] ?? '') . ' ' . ($nppesData['last_name'] ?? '')),
                'source_status' => $nppesData['status'] ?? 'unknown',
                'source_expiration' => null, // NPPES doesn't provide license expiration
                'discrepancies' => !empty($discrepancies) ? implode('; ', $discrepancies) : null,
                'verified_by' => $verifiedBy,
            ]);
        } catch (\Exception $e) {
            Log::warning('License verification failed', [
                'license_id' => $license->id,
                'error' => $e->getMessage(),
            ]);

            return $this->createVerification($license, [
                'status' => 'error',
                'verification_source' => 'nppes',
                'discrepancies' => 'Verification failed: ' . $e->getMessage(),
                'verified_by' => $verifiedBy,
            ]);
        }
    }

    /**
     * Bulk verify all licenses for an agency.
     */
    public function verifyAllForAgency(int $agencyId, ?int $verifiedBy = null): array
    {
        $licenses = License::where('agency_id', $agencyId)
            ->whereNotNull('license_number')
            ->with('provider')
            ->get();

        $results = ['verified' => 0, 'mismatch' => 0, 'error' => 0, 'total' => $licenses->count()];

        foreach ($licenses as $license) {
            $verification = $this->verifyLicense($license, $verifiedBy);
            $results[$verification->status] = ($results[$verification->status] ?? 0) + 1;
        }

        return $results;
    }

    /**
     * Check all licenses for expirations and create notifications.
     * Returns counts of alerts generated at each threshold.
     */
    public function checkExpirations(?int $agencyId = null): array
    {
        $query = License::with('provider')
            ->whereNotNull('expiration_date')
            ->where('status', '!=', 'inactive');

        if ($agencyId) {
            $query->withoutGlobalScopes()->where('agency_id', $agencyId);
        } else {
            $query->withoutGlobalScopes();
        }

        $licenses = $query->get();

        $alerts = ['expired' => 0, 'critical_30' => 0, 'warning_60' => 0, 'notice_90' => 0];

        foreach ($licenses as $license) {
            $daysUntilExpiry = now()->startOfDay()->diffInDays($license->expiration_date, false);

            $alertType = null;
            if ($daysUntilExpiry < 0) {
                $alertType = 'expired';
            } elseif ($daysUntilExpiry <= 30) {
                $alertType = 'critical_30';
            } elseif ($daysUntilExpiry <= 60) {
                $alertType = 'warning_60';
            } elseif ($daysUntilExpiry <= 90) {
                $alertType = 'notice_90';
            }

            if ($alertType) {
                $this->createExpirationAlert($license, $alertType, $daysUntilExpiry);
                $alerts[$alertType]++;

                // Auto-update license status if expired
                if ($alertType === 'expired' && $license->status === 'active') {
                    $license->update(['status' => 'expired']);
                }
            }
        }

        // Also check DEA registrations
        $deaQuery = DeaRegistration::with('provider')
            ->whereNotNull('expiration_date')
            ->where('status', '!=', 'revoked');

        if ($agencyId) {
            $deaQuery->withoutGlobalScopes()->where('agency_id', $agencyId);
        } else {
            $deaQuery->withoutGlobalScopes();
        }

        foreach ($deaQuery->get() as $dea) {
            $daysUntilExpiry = now()->startOfDay()->diffInDays($dea->expiration_date, false);

            if ($daysUntilExpiry < 0 || $daysUntilExpiry <= 90) {
                $alertType = $daysUntilExpiry < 0 ? 'expired' : ($daysUntilExpiry <= 30 ? 'critical_30' : ($daysUntilExpiry <= 60 ? 'warning_60' : 'notice_90'));
                $this->createDeaExpirationAlert($dea, $alertType, $daysUntilExpiry);
                $alerts[$alertType]++;

                if ($daysUntilExpiry < 0 && $dea->status === 'active') {
                    $dea->update(['status' => 'expired']);
                }
            }
        }

        return $alerts;
    }

    /**
     * Get monitoring summary for an agency.
     */
    public function getMonitoringSummary(int $agencyId): array
    {
        $licenses = License::withoutGlobalScopes()
            ->where('agency_id', $agencyId)
            ->get();

        $deaRegs = DeaRegistration::withoutGlobalScopes()
            ->where('agency_id', $agencyId)
            ->get();

        $verifications = LicenseVerification::where('agency_id', $agencyId)
            ->orderByDesc('verified_at')
            ->get()
            ->unique('license_id');

        $now = now();

        return [
            'licenses' => [
                'total' => $licenses->count(),
                'active' => $licenses->where('status', 'active')->count(),
                'expired' => $licenses->filter(fn($l) => $l->isExpired())->count(),
                'expiring_30' => $licenses->filter(fn($l) => $l->isExpiringSoon(30))->count(),
                'expiring_60' => $licenses->filter(fn($l) => $l->expiration_date && $l->expiration_date->isBetween($now->copy()->addDays(30), $now->copy()->addDays(60)))->count(),
                'expiring_90' => $licenses->filter(fn($l) => $l->expiration_date && $l->expiration_date->isBetween($now->copy()->addDays(60), $now->copy()->addDays(90)))->count(),
                'no_expiration' => $licenses->whereNull('expiration_date')->count(),
            ],
            'verifications' => [
                'total_verified' => $verifications->count(),
                'verified' => $verifications->where('status', 'verified')->count(),
                'mismatch' => $verifications->where('status', 'mismatch')->count(),
                'error' => $verifications->where('status', 'error')->count(),
                'never_verified' => $licenses->count() - $verifications->count(),
                'last_run' => $verifications->max('verified_at')?->toIso8601String(),
            ],
            'dea' => [
                'total' => $deaRegs->count(),
                'active' => $deaRegs->where('status', 'active')->count(),
                'expired' => $deaRegs->filter(fn($d) => $d->isExpired())->count(),
                'expiring_90' => $deaRegs->filter(fn($d) => $d->isExpiringSoon(90))->count(),
            ],
        ];
    }

    /**
     * Get all expiring licenses/DEAs for an agency, grouped by urgency.
     */
    public function getExpiringItems(int $agencyId): array
    {
        $now = now();

        $licenses = License::withoutGlobalScopes()
            ->where('agency_id', $agencyId)
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '<=', $now->copy()->addDays(90))
            ->with('provider:id,first_name,last_name,credentials,npi')
            ->orderBy('expiration_date')
            ->get()
            ->map(fn($l) => [
                'id' => $l->id,
                'type' => 'license',
                'item' => $l->license_type . ' - ' . $l->state,
                'license_number' => $l->license_number,
                'provider_id' => $l->provider_id,
                'provider_name' => $l->provider?->full_name,
                'expiration_date' => $l->expiration_date->toDateString(),
                'days_left' => (int) $now->startOfDay()->diffInDays($l->expiration_date, false),
                'status' => $l->status,
            ]);

        $deas = DeaRegistration::withoutGlobalScopes()
            ->where('agency_id', $agencyId)
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '<=', $now->copy()->addDays(90))
            ->with('provider:id,first_name,last_name,credentials,npi')
            ->orderBy('expiration_date')
            ->get()
            ->map(fn($d) => [
                'id' => $d->id,
                'type' => 'dea',
                'item' => 'DEA ' . $d->dea_number,
                'license_number' => $d->dea_number,
                'provider_id' => $d->provider_id,
                'provider_name' => $d->provider?->full_name,
                'expiration_date' => $d->expiration_date->toDateString(),
                'days_left' => (int) $now->startOfDay()->diffInDays($d->expiration_date, false),
                'status' => $d->status,
            ]);

        $all = $licenses->concat($deas)->sortBy('days_left')->values();

        return [
            'expired' => $all->filter(fn($i) => $i['days_left'] < 0)->values(),
            'critical' => $all->filter(fn($i) => $i['days_left'] >= 0 && $i['days_left'] <= 30)->values(),
            'warning' => $all->filter(fn($i) => $i['days_left'] > 30 && $i['days_left'] <= 60)->values(),
            'notice' => $all->filter(fn($i) => $i['days_left'] > 60 && $i['days_left'] <= 90)->values(),
        ];
    }

    private function createVerification(License $license, array $data): LicenseVerification
    {
        return LicenseVerification::updateOrCreate(
            [
                'agency_id' => $license->agency_id,
                'license_id' => $license->id,
                'verification_source' => $data['verification_source'],
            ],
            array_merge($data, [
                'provider_id' => $license->provider_id,
                'state' => $license->state,
                'license_number' => $license->license_number ?? '',
                'verified_at' => now(),
            ])
        );
    }

    private function createExpirationAlert(License $license, string $alertType, int $daysLeft): void
    {
        $provider = $license->provider;
        $provName = $provider ? $provider->full_name : "Provider #{$license->provider_id}";
        $state = $license->state;

        $title = match ($alertType) {
            'expired' => "License EXPIRED: {$provName} ({$state})",
            'critical_30' => "License expires in {$daysLeft} days: {$provName} ({$state})",
            'warning_60' => "License expires in {$daysLeft} days: {$provName} ({$state})",
            'notice_90' => "License expires in {$daysLeft} days: {$provName} ({$state})",
        };

        NotificationService::send($license->agency_id, 'license_expiring', $title, [
            'body' => "{$license->license_type} #{$license->license_number} in {$state} expires {$license->expiration_date->toDateString()}",
            'linkable_type' => License::class,
            'linkable_id' => $license->id,
        ]);
    }

    private function createDeaExpirationAlert(DeaRegistration $dea, string $alertType, int $daysLeft): void
    {
        $provider = $dea->provider;
        $provName = $provider ? $provider->full_name : "Provider #{$dea->provider_id}";

        $title = $daysLeft < 0
            ? "DEA EXPIRED: {$provName} ({$dea->dea_number})"
            : "DEA expires in {$daysLeft} days: {$provName} ({$dea->dea_number})";

        NotificationService::send($dea->agency_id, 'license_expiring', $title, [
            'body' => "DEA #{$dea->dea_number} expires {$dea->expiration_date->toDateString()}",
            'linkable_type' => DeaRegistration::class,
            'linkable_id' => $dea->id,
        ]);
    }
}

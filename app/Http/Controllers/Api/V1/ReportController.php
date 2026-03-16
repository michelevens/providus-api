<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\BoardCertification;
use App\Models\ExclusionCheck;
use App\Models\License;
use App\Models\MalpracticePolicy;
use App\Models\Provider;
use App\Models\ProviderEducation;
use App\Services\CredentialingPacketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReportController extends Controller
{
    private function resolveAgencyId(Request $request): int
    {
        $user = $request->user();
        $agencyId = $user->agency_id;
        if (!$agencyId && $user->role === 'superadmin' && $request->header('X-Agency-Id')) {
            $agencyId = (int) $request->header('X-Agency-Id');
        }
        abort_unless($agencyId, 400, 'No agency context. Provide X-Agency-Id header.');
        return $agencyId;
    }

    // Provider credentialing packet (all data for one provider)
    public function providerPacket(Request $request, int $providerId): JsonResponse
    {
        $agencyId = $this->resolveAgencyId($request);
        $provider = Provider::where('agency_id', $agencyId)
            ->with(['organization:id,name,npi', 'licenses'])
            ->findOrFail($providerId);

        $where = fn($model) => $model::where('agency_id', $agencyId)->where('provider_id', $providerId)->get();

        return response()->json([
            'success' => true,
            'data' => [
                'provider' => $provider,
                'education' => $where(ProviderEducation::class),
                'board_certifications' => $where(BoardCertification::class),
                'malpractice' => $where(MalpracticePolicy::class),
                'exclusion_checks' => $where(ExclusionCheck::class),
                'applications' => Application::where('agency_id', $agencyId)
                    ->where('provider_id', $providerId)
                    ->with('payer:id,name')->get(),
            ],
        ]);
    }

    // Download provider credentialing packet as PDF
    public function providerPacketPdf(Request $request, int $providerId): Response
    {
        $agencyId = $this->resolveAgencyId($request);
        $provider = Provider::where('agency_id', $agencyId)->findOrFail($providerId);
        $name = str_replace(' ', '_', trim($provider->first_name . '_' . $provider->last_name));

        $pdf = CredentialingPacketService::generate($agencyId, $providerId);

        return $pdf->download("Credentialing_Packet_{$name}_{$provider->npi}.pdf");
    }

    // Agency-wide compliance report
    public function complianceReport(Request $request): JsonResponse
    {
        $agencyId = $this->resolveAgencyId($request);

        // Expiring licenses (next 90 days)
        $expiringLicenses = License::where('agency_id', $agencyId)
            ->whereBetween('expiration_date', [now(), now()->addDays(90)])
            ->with('provider:id,first_name,last_name,credentials')
            ->orderBy('expiration_date')->get();

        // Expiring malpractice (next 90 days)
        $expiringMalpractice = MalpracticePolicy::where('agency_id', $agencyId)
            ->whereBetween('expiration_date', [now(), now()->addDays(90)])
            ->with('provider:id,first_name,last_name,credentials')
            ->orderBy('expiration_date')->get();

        // Expiring board certs (next 90 days)
        $expiringBoards = BoardCertification::where('agency_id', $agencyId)
            ->where('is_lifetime', false)
            ->whereBetween('expiration_date', [now(), now()->addDays(90)])
            ->with('provider:id,first_name,last_name,credentials')
            ->orderBy('expiration_date')->get();

        // Exclusion flags
        $excludedProviders = ExclusionCheck::where('agency_id', $agencyId)
            ->where('is_excluded', true)
            ->with('provider:id,first_name,last_name,npi')
            ->get();

        // Providers never screened
        $screenedIds = ExclusionCheck::where('agency_id', $agencyId)->distinct()->pluck('provider_id');
        $neverScreened = Provider::where('agency_id', $agencyId)
            ->where('is_active', true)
            ->whereNotIn('id', $screenedIds)
            ->select('id', 'first_name', 'last_name', 'npi', 'credentials')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'expiring_licenses' => $expiringLicenses,
                'expiring_malpractice' => $expiringMalpractice,
                'expiring_board_certs' => $expiringBoards,
                'excluded_providers' => $excludedProviders,
                'never_screened' => $neverScreened,
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    // Export data as JSON (for CSV generation client-side)
    public function export(Request $request): JsonResponse
    {
        $request->validate(['type' => 'required|in:providers,organizations,licenses,applications,facilities']);
        $agencyId = $this->resolveAgencyId($request);

        $data = match ($request->type) {
            'providers' => Provider::where('agency_id', $agencyId)
                ->with(['organization:id,name', 'licenses'])->get(),
            'organizations' => \App\Models\Organization::where('agency_id', $agencyId)->get(),
            'licenses' => License::where('agency_id', $agencyId)
                ->with('provider:id,first_name,last_name,npi')->get(),
            'applications' => Application::where('agency_id', $agencyId)
                ->with(['provider:id,first_name,last_name', 'payer:id,name', 'organization:id,name'])->get(),
            'facilities' => \App\Models\Facility::where('agency_id', $agencyId)->get(),
        };

        return response()->json(['success' => true, 'data' => $data]);
    }
}

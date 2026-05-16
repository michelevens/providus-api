<?php

namespace App\Http\Controllers\Api\V1;

// ProviderActivityController — powers the rebuilt ProviderProfilePage:
// expiration countdown row (licenses + malpractice + boards + DEA +
// CAQH), adaptive action row, performance stat strip (denial rate +
// clean claim rate + avg days to pay), and provider-scoped notes CRUD.
//
// Mirrors PatientActivityController but keyed by provider_id (FK).
// Providers are first-class rows so we don't need the patient_key
// normalization trick used on the patient side.

use App\Http\Controllers\Controller;
use App\Models\ProviderNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProviderActivityController extends Controller
{
    /**
     * GET /providers/{providerId}/activity
     *
     * One payload powering the rebuilt profile page header:
     *   - expirations: per-domain countdown rows (kind, label, daysOut)
     *   - performance: denial rate, clean claim rate, avg days to pay,
     *     total claims, last 90 days throughput
     *   - notes: pinned + recent
     *   - timeline: merged chronological feed (RCM events + cred milestones)
     */
    public function show(Request $request, int $providerId): JsonResponse
    {
        $agencyId = $request->user()->effectiveAgencyId($request);

        // Provider-role users can only see their own provider page.
        $user = $request->user();
        if ($user->role === 'provider' && $user->provider_id != $providerId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        // Confirm the provider exists in this tenant. 404 early instead
        // of returning empty arrays everywhere.
        $provider = DB::table('providers')
            ->where('agency_id', $agencyId)
            ->where('id', $providerId)
            ->first(['id', 'first_name', 'last_name', 'npi', 'caqh_id']);

        if (!$provider) {
            return response()->json(['success' => false, 'message' => 'Provider not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'expirations' => $this->expirations($agencyId, $providerId),
                'performance' => $this->performance($agencyId, $providerId),
                'notes'       => $this->notesFor($agencyId, $providerId),
                'timeline'    => $this->timeline($agencyId, $providerId),
            ],
        ]);
    }

    /**
     * Every credentialing artifact with an expiration date, normalized
     * into one shape so the V2 countdown row can sort by daysOut.
     * Negative daysOut == past expiry.
     */
    private function expirations(int $agencyId, int $providerId): array
    {
        $rows = [];

        // Licenses — primary credential. Renewal cycle is state-specific
        // (1–3 yrs).
        DB::table('licenses')
            ->where('agency_id', $agencyId)
            ->where('provider_id', $providerId)
            ->whereNotNull('expiration_date')
            ->get(['id', 'state', 'license_type', 'expiration_date'])
            ->each(function ($r) use (&$rows) {
                $rows[] = [
                    'kind'           => 'license',
                    'id'             => $r->id,
                    'label'          => trim(($r->state ?? '') . ' ' . ($r->license_type ?? 'License')) ?: 'License',
                    'expirationDate' => $r->expiration_date,
                    'daysOut'        => self::daysUntil($r->expiration_date),
                ];
            });

        // Malpractice — 1-yr cycles. Lapsed coverage = immediate panel risk.
        DB::table('malpractice_policies')
            ->where('agency_id', $agencyId)
            ->where('provider_id', $providerId)
            ->whereNotNull('expiration_date')
            ->get(['id', 'carrier_name', 'expiration_date'])
            ->each(function ($r) use (&$rows) {
                $rows[] = [
                    'kind'           => 'malpractice',
                    'id'             => $r->id,
                    'label'          => $r->carrier_name ?: 'Malpractice',
                    'expirationDate' => $r->expiration_date,
                    'daysOut'        => self::daysUntil($r->expiration_date),
                ];
            });

        // Board certifications.
        DB::table('board_certifications')
            ->where('agency_id', $agencyId)
            ->where('provider_id', $providerId)
            ->whereNotNull('expiration_date')
            ->where('is_lifetime', false)
            ->get(['id', 'board_name', 'expiration_date'])
            ->each(function ($r) use (&$rows) {
                $rows[] = [
                    'kind'           => 'board',
                    'id'             => $r->id,
                    'label'          => $r->board_name ?: 'Board cert',
                    'expirationDate' => $r->expiration_date,
                    'daysOut'        => self::daysUntil($r->expiration_date),
                ];
            });

        // DEA — 3-yr cycles, but lapses can suspend prescribing in a day.
        DB::table('dea_registrations')
            ->where('agency_id', $agencyId)
            ->where('provider_id', $providerId)
            ->whereNotNull('expiration_date')
            ->get(['id', 'state', 'dea_number', 'expiration_date'])
            ->each(function ($r) use (&$rows) {
                $rows[] = [
                    'kind'           => 'dea',
                    'id'             => $r->id,
                    'label'          => trim(($r->state ?? '') . ' DEA') ?: 'DEA',
                    'expirationDate' => $r->expiration_date,
                    'daysOut'        => self::daysUntil($r->expiration_date),
                ];
            });

        // CAQH attestation — every 120 days. The most fragile recurring
        // task in credentialing.
        $caqh = DB::table('caqh_tracking')
            ->where('agency_id', $agencyId)
            ->where('provider_id', $providerId)
            ->orderByDesc('id')
            ->first(['id', 'next_attestation', 'attestation_date']);
        if ($caqh && $caqh->next_attestation) {
            $rows[] = [
                'kind'           => 'caqh',
                'id'             => $caqh->id,
                'label'          => 'CAQH attestation',
                'expirationDate' => $caqh->next_attestation,
                'daysOut'        => self::daysUntil($caqh->next_attestation),
            ];
        }

        // Soonest-expiring first; null daysOut sinks to the bottom.
        usort($rows, function ($a, $b) {
            if ($a['daysOut'] === null) return 1;
            if ($b['daysOut'] === null) return -1;
            return $a['daysOut'] <=> $b['daysOut'];
        });

        return $rows;
    }

    /**
     * Provider-level RCM performance: how this clinician is doing
     * against payer-mix benchmarks. Computed from claims + denials
     * filtered to this provider.
     */
    private function performance(int $agencyId, int $providerId): array
    {
        $claims = DB::table('claims')
            ->where('agency_id', $agencyId)
            ->where('provider_id', $providerId)
            ->select('id', 'status', 'submitted_date', 'paid_date', 'created_at')
            ->get();

        $totalClaims = $claims->count();
        if ($totalClaims === 0) {
            return [
                'totalClaims'     => 0,
                'denialRate'      => null,
                'cleanClaimRate'  => null,
                'avgDaysToPay'    => null,
                'last90Submitted' => 0,
                'last90Paid'      => 0,
            ];
        }

        $claimIds = $claims->pluck('id')->all();

        // Denials in scope.
        $deniedCount = DB::table('claim_denials')
            ->whereIn('claim_id', $claimIds)
            ->distinct('claim_id')
            ->count('claim_id');

        // Clean-claim rate: paid without ever being denied, divided by
        // total claims. Industry benchmark is >95%.
        $paidClaimIds = $claims->whereNotNull('paid_date')->pluck('id')->all();
        $deniedClaimIds = DB::table('claim_denials')
            ->whereIn('claim_id', $claimIds)
            ->distinct('claim_id')
            ->pluck('claim_id')
            ->all();
        $cleanCount = count(array_diff($paidClaimIds, $deniedClaimIds));

        // Avg days from submission to payment for claims that have paid.
        $daysToPay = $claims
            ->filter(fn ($c) => $c->submitted_date && $c->paid_date)
            ->map(fn ($c) => max(0, (strtotime((string) $c->paid_date) - strtotime((string) $c->submitted_date)) / 86400))
            ->values();
        $avgDays = $daysToPay->count() > 0 ? round($daysToPay->avg(), 1) : null;

        // Throughput over the last 90 days.
        $cutoff = now()->subDays(90);
        $last90Submitted = $claims->filter(fn ($c) => $c->submitted_date && strtotime((string) $c->submitted_date) >= $cutoff->timestamp)->count();
        $last90Paid      = $claims->filter(fn ($c) => $c->paid_date && strtotime((string) $c->paid_date) >= $cutoff->timestamp)->count();

        return [
            'totalClaims'     => $totalClaims,
            'denialRate'      => round(($deniedCount / $totalClaims) * 100, 1),
            'cleanClaimRate'  => round(($cleanCount / $totalClaims) * 100, 1),
            'avgDaysToPay'    => $avgDays,
            'last90Submitted' => $last90Submitted,
            'last90Paid'      => $last90Paid,
        ];
    }

    private function notesFor(int $agencyId, int $providerId): \Illuminate\Support\Collection
    {
        return DB::table('provider_notes')
            ->where('agency_id', $agencyId)
            ->where('provider_id', $providerId)
            ->orderByDesc('pinned')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'body', 'pinned', 'created_by', 'updated_by', 'created_at', 'updated_at']);
    }

    /**
     * Merged chronological feed: applications, denials, notes,
     * license/cred renewals. Caps at 200 events so V2 doesn't choke.
     */
    private function timeline(int $agencyId, int $providerId): array
    {
        $events = [];

        // Applications.
        $apps = DB::table('applications')
            ->where('agency_id', $agencyId)
            ->where('provider_id', $providerId)
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'status', 'created_at', 'submitted_date', 'effective_date', 'payer_name']);
        foreach ($apps as $a) {
            $events[] = [
                'type'  => 'application',
                'at'    => (string) ($a->submitted_date ?: $a->created_at),
                'title' => 'Application — ' . ($a->payer_name ?: 'Payer'),
                'sub'   => ucfirst((string) $a->status),
                'severity' => match ($a->status) {
                    'approved', 'credentialed' => 'success',
                    'denied', 'rejected' => 'danger',
                    default => 'info',
                },
            ];
        }

        // Denials on this provider's claims.
        $claimIds = DB::table('claims')
            ->where('agency_id', $agencyId)
            ->where('provider_id', $providerId)
            ->pluck('id')
            ->all();
        if (!empty($claimIds)) {
            $denials = DB::table('claim_denials')
                ->whereIn('claim_id', $claimIds)
                ->orderByDesc('id')
                ->limit(50)
                ->get(['id', 'created_at', 'denial_code', 'denial_reason', 'denial_status']);
            foreach ($denials as $d) {
                $events[] = [
                    'type'  => 'denial',
                    'at'    => (string) $d->created_at,
                    'title' => 'Denial ' . ($d->denial_code ?: ''),
                    'sub'   => $d->denial_reason ?: ucfirst((string) $d->denial_status),
                    'severity' => 'danger',
                ];
            }
        }

        // Notes.
        $notes = DB::table('provider_notes')
            ->where('agency_id', $agencyId)
            ->where('provider_id', $providerId)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'body', 'created_at', 'pinned']);
        foreach ($notes as $n) {
            $events[] = [
                'type'  => 'note_added',
                'at'    => (string) $n->created_at,
                'title' => 'Note added',
                'sub'   => substr((string) $n->body, 0, 140),
                'severity' => $n->pinned ? 'pinned' : 'info',
            ];
        }

        usort($events, fn ($a, $b) => strcmp((string) ($b['at'] ?? ''), (string) ($a['at'] ?? '')));
        return array_slice($events, 0, 200);
    }

    private static function daysUntil(?string $iso): ?int
    {
        if (!$iso) return null;
        $ts = strtotime($iso);
        if ($ts === false) return null;
        return (int) floor(($ts - time()) / 86400);
    }

    // ─── Notes CRUD ─────────────────────────────────────────────

    public function storeNote(Request $request, int $providerId): JsonResponse
    {
        $request->validate([
            'body' => 'required|string|max:5000',
            'pinned' => 'sometimes|boolean',
        ]);
        $agencyId = $request->user()->effectiveAgencyId($request);

        $note = ProviderNote::create([
            'agency_id'   => $agencyId,
            'provider_id' => $providerId,
            'body'        => $request->input('body'),
            'pinned'      => (bool) $request->input('pinned', false),
            'created_by'  => $request->user()->id,
            'updated_by'  => $request->user()->id,
        ]);

        return response()->json(['success' => true, 'data' => $note], 201);
    }

    public function updateNote(Request $request, int $providerId, int $id): JsonResponse
    {
        $request->validate([
            'body' => 'sometimes|string|max:5000',
            'pinned' => 'sometimes|boolean',
        ]);
        $agencyId = $request->user()->effectiveAgencyId($request);

        $note = ProviderNote::where('agency_id', $agencyId)
            ->where('provider_id', $providerId)
            ->findOrFail($id);

        $note->update([
            ...$request->only(['body', 'pinned']),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json(['success' => true, 'data' => $note->fresh()]);
    }

    public function deleteNote(Request $request, int $providerId, int $id): JsonResponse
    {
        $agencyId = $request->user()->effectiveAgencyId($request);

        $note = ProviderNote::where('agency_id', $agencyId)
            ->where('provider_id', $providerId)
            ->findOrFail($id);

        $note->delete();
        return response()->json(['success' => true]);
    }
}

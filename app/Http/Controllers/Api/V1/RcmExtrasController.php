<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ChargeEntry;
use App\Models\Claim;
use App\Models\ClearinghouseConfig;
use App\Models\PriorAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints that V2 was calling but the backend hadn't implemented yet —
 * surfaced by V2's /tools/health probe. Grouped into one controller because
 * each is small enough that a dedicated file per endpoint would be wasteful.
 *
 *  - GET/POST/PUT/DELETE /rcm/authorizations           prior-auth tracking
 *  - GET/PUT             /rcm/clearinghouse/config     Availity OAuth creds
 *  - POST                /rcm/claims/import            bulk CSV → claims
 *  - POST                /rcm/charges/import           bulk CSV → charge entries
 *  - POST                /rcm/availity/import          stub (501) — file-based
 *  - POST                /rcm/availity/pull            stub (501) — auto-pull
 *  - POST                /rcm/era/pull                 stub (501) — 835 pull
 *
 * Availity endpoints are stubs that return HTTP 501 with a clear message; full
 * implementation requires Availity OAuth client setup. Stubs keep V2's UI from
 * 404'ing and give the user a real error message instead of silent failure.
 */
class RcmExtrasController extends Controller
{
    // ══════════════════════════════════════════════════
    // PRIOR AUTHORIZATIONS
    // ══════════════════════════════════════════════════

    public function listAuthorizations(Request $request): JsonResponse
    {
        $query = PriorAuthorization::where('agency_id', $request->user()->agency_id)
            ->orderByDesc('created_at');

        if ($status = $request->query('status'))     $query->where('status', $status);
        if ($payer = $request->query('payer_name'))  $query->where('payer_name', 'ilike', "%{$payer}%");
        if ($claimId = $request->query('claim_id'))  $query->where('claim_id', $claimId);

        $perPage = (int) $request->query('per_page', 50);
        return response()->json([
            'success' => true,
            'data'    => $query->paginate($perPage),
        ]);
    }

    public function storeAuthorization(Request $request): JsonResponse
    {
        $data = $request->validate([
            'patient_name'         => 'nullable|string|max:255',
            'patient_member_id'    => 'nullable|string|max:100',
            'payer_name'           => 'nullable|string|max:255',
            'authorization_number' => 'required|string|max:100',
            'cpt_code'             => 'nullable|string|max:10',
            'cpt_codes'            => 'nullable|string|max:255',
            'units_authorized'     => 'nullable|numeric|min:0',
            'units_used'           => 'nullable|numeric|min:0',
            'effective_date'       => 'nullable|date',
            'expiration_date'      => 'nullable|date|after_or_equal:effective_date',
            'status'               => 'nullable|in:active,expired,exhausted,denied,cancelled',
            'notes'                => 'nullable|string',
            'claim_id'             => 'nullable|integer|exists:claims,id',
            'billing_client_id'    => 'nullable|integer|exists:billing_clients,id',
        ]);

        $data['agency_id']  = $request->user()->agency_id;
        $data['created_by'] = $request->user()->id;
        $data['status']     = $data['status'] ?? 'active';

        $auth = PriorAuthorization::create($data);
        return response()->json(['success' => true, 'data' => $auth], 201);
    }

    public function updateAuthorization(Request $request, int $id): JsonResponse
    {
        $auth = PriorAuthorization::where('agency_id', $request->user()->agency_id)->findOrFail($id);
        $data = $request->validate([
            'patient_name'         => 'sometimes|nullable|string|max:255',
            'authorization_number' => 'sometimes|string|max:100',
            'cpt_code'             => 'sometimes|nullable|string|max:10',
            'cpt_codes'            => 'sometimes|nullable|string|max:255',
            'units_authorized'     => 'sometimes|nullable|numeric|min:0',
            'units_used'           => 'sometimes|nullable|numeric|min:0',
            'effective_date'       => 'sometimes|nullable|date',
            'expiration_date'      => 'sometimes|nullable|date',
            'status'               => 'sometimes|in:active,expired,exhausted,denied,cancelled',
            'notes'                => 'sometimes|nullable|string',
        ]);
        $auth->update($data);
        return response()->json(['success' => true, 'data' => $auth->fresh()]);
    }

    public function destroyAuthorization(Request $request, int $id): JsonResponse
    {
        PriorAuthorization::where('agency_id', $request->user()->agency_id)->findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    // ══════════════════════════════════════════════════
    // CLEARINGHOUSE CONFIG (Availity creds)
    // ══════════════════════════════════════════════════

    public function getClearinghouseConfig(Request $request): JsonResponse
    {
        $cfg = ClearinghouseConfig::firstOrCreate(
            ['agency_id' => $request->user()->agency_id],
            ['clearinghouse_name' => 'availity', 'connected' => false],
        );
        // Never return the encrypted secret. Just indicate whether one is set.
        return response()->json([
            'success' => true,
            'data'    => [
                'id'                  => $cfg->id,
                'clearinghouse_name'  => $cfg->clearinghouse_name,
                'client_id'           => $cfg->client_id,
                'has_secret'          => !empty($cfg->client_secret_encrypted),
                'submitter_id'        => $cfg->submitter_id,
                'organization_name'   => $cfg->organization_name,
                'last_pulled_at'      => $cfg->last_pulled_at,
                'connected'           => $cfg->connected,
                'metadata'            => $cfg->metadata,
            ],
        ]);
    }

    public function updateClearinghouseConfig(Request $request): JsonResponse
    {
        $data = $request->validate([
            'clearinghouse_name' => 'sometimes|string|max:30',
            'client_id'          => 'sometimes|nullable|string|max:255',
            'client_secret'      => 'sometimes|nullable|string|max:500', // plaintext input
            'submitter_id'       => 'sometimes|nullable|string|max:100',
            'organization_name'  => 'sometimes|nullable|string|max:255',
            'connected'          => 'sometimes|boolean',
        ]);

        $cfg = ClearinghouseConfig::firstOrCreate(
            ['agency_id' => $request->user()->agency_id],
            ['clearinghouse_name' => 'availity'],
        );
        if (array_key_exists('client_secret', $data)) {
            $cfg->setSecret($data['client_secret']);
            unset($data['client_secret']);
        }
        $cfg->fill($data);
        $cfg->connected = !empty($cfg->client_id) && !empty($cfg->client_secret_encrypted);
        $cfg->save();

        return $this->getClearinghouseConfig($request);
    }

    // ══════════════════════════════════════════════════
    // BULK CLAIMS IMPORT (CSV)
    // ══════════════════════════════════════════════════

    public function importClaims(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file']);
        $rows = $this->parseUploadedCsv($request->file('file'));
        if (empty($rows)) {
            return response()->json(['success' => false, 'error' => 'No rows found in CSV'], 422);
        }

        $aid = $request->user()->agency_id;
        $uid = $request->user()->id;
        $clientId = $request->input('billing_client_id');
        $imported = 0; $updated = 0; $skipped = 0; $errors = [];

        foreach ($rows as $i => $row) {
            $cn = trim((string)($row['claim_number'] ?? ''));
            if ($cn === '') { $skipped++; continue; }

            $existing = Claim::where('agency_id', $aid)->where('claim_number', $cn)->first();
            $payload = [
                'agency_id'         => $aid,
                'billing_client_id' => $clientId,
                'claim_number'      => $cn,
                'patient_name'      => $row['patient_name'] ?? null,
                'payer_name'        => $row['payer_name'] ?? $row['payer'] ?? null,
                'date_of_service'   => $this->normalizeDate($row['date_of_service'] ?? $row['dos'] ?? null),
                'total_charges'     => (float)($row['total_charges'] ?? $row['charges'] ?? 0),
                'status'            => $row['status'] ?? 'submitted',
            ];
            if ($payload['date_of_service'] === null) {
                $errors[] = ['line' => $i + 2, 'message' => "Missing/invalid date_of_service for claim {$cn}"];
                $skipped++;
                continue;
            }

            try {
                if ($existing) {
                    // Upsert by (agency_id, claim_number) — never insert dupes
                    $existing->fill($payload)->save();
                    $updated++;
                } else {
                    $payload['balance'] = $payload['total_charges'];
                    $payload['created_by'] = $uid;
                    Claim::create($payload);
                    $imported++;
                }
            } catch (\Throwable $e) {
                $errors[] = ['line' => $i + 2, 'message' => $e->getMessage()];
                $skipped++;
            }
        }

        return response()->json([
            'success' => true,
            'data'    => compact('imported', 'updated', 'skipped', 'errors'),
        ], 201);
    }

    // ══════════════════════════════════════════════════
    // BULK CHARGES IMPORT (CSV)
    // ══════════════════════════════════════════════════

    public function importCharges(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file']);
        $rows = $this->parseUploadedCsv($request->file('file'));
        if (empty($rows)) {
            return response()->json(['success' => false, 'error' => 'No rows found in CSV'], 422);
        }

        $aid = $request->user()->agency_id;
        $uid = $request->user()->id;
        $clientId = $request->input('billing_client_id');
        $imported = 0; $skipped = 0; $errors = [];

        foreach ($rows as $i => $row) {
            $dos = $this->normalizeDate($row['date_of_service'] ?? $row['dos'] ?? null);
            $cpt = trim((string)($row['cpt_code'] ?? $row['cpt'] ?? ''));
            if (!$dos || !$cpt) {
                $errors[] = ['line' => $i + 2, 'message' => 'Missing date_of_service or cpt_code'];
                $skipped++;
                continue;
            }

            try {
                Charge::create([
                    'agency_id'         => $aid,
                    'billing_client_id' => $clientId,
                    'patient_name'      => $row['patient_name'] ?? null,
                    'payer_name'        => $row['payer_name'] ?? $row['payer'] ?? null,
                    'date_of_service'   => $dos,
                    'cpt_code'          => $cpt,
                    'modifiers'         => $row['modifiers'] ?? $row['modifier'] ?? null,
                    'units'             => (float)($row['units'] ?? 1),
                    'charge_amount'     => (float)($row['total_charge'] ?? $row['charge_amount'] ?? $row['unit_charge'] ?? 0),
                    'status'            => 'pending',
                    'created_by'        => $uid,
                ]);
                $imported++;
            } catch (\Throwable $e) {
                $errors[] = ['line' => $i + 2, 'message' => $e->getMessage()];
                $skipped++;
            }
        }

        return response()->json([
            'success' => true,
            'data'    => compact('imported', 'skipped', 'errors'),
        ], 201);
    }

    // ══════════════════════════════════════════════════
    // AVAILITY STUBS (full integration TBD)
    // ══════════════════════════════════════════════════

    public function availityImport(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error'   => 'not_implemented',
            'message' => 'Availity file-based import not yet wired. Use Tools → RCM Import → ERA / 835 upload for individual 835 files, or POST a Payment Detail CSV to /rcm/payments one at a time.',
        ], 501);
    }

    public function availityPull(Request $request): JsonResponse
    {
        $cfg = ClearinghouseConfig::where('agency_id', $request->user()->agency_id)->first();
        if (!$cfg || !$cfg->connected) {
            return response()->json([
                'success' => false,
                'error'   => 'no_credentials',
                'message' => 'Availity API credentials not configured. Set client_id and client_secret via PUT /rcm/clearinghouse/config first.',
            ], 422);
        }
        return response()->json([
            'success' => false,
            'error'   => 'not_implemented',
            'message' => 'Availity OAuth + EFT/ERA pull not yet wired. Credentials are saved; pull mechanism pending Availity sandbox testing.',
        ], 501);
    }

    public function eraPull(Request $request): JsonResponse
    {
        // Same gating as availityPull
        $cfg = ClearinghouseConfig::where('agency_id', $request->user()->agency_id)->first();
        if (!$cfg || !$cfg->connected) {
            return response()->json([
                'success' => false,
                'error'   => 'no_credentials',
                'message' => 'Clearinghouse credentials not configured.',
            ], 422);
        }
        return response()->json([
            'success' => false,
            'error'   => 'not_implemented',
            'message' => 'Clearinghouse 835 pull not yet wired. Upload 835 files manually via /rcm/era/upload until Availity integration ships.',
        ], 501);
    }

    // ══════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════

    /** Parse uploaded CSV to associative arrays keyed by header. */
    private function parseUploadedCsv($file): array
    {
        $text = file_get_contents($file->getRealPath());
        if (!$text) return [];

        $lines = preg_split('/\r\n|\r|\n/', $text);
        $lines = array_filter($lines, fn($l) => trim($l) !== '');
        if (count($lines) < 2) return [];

        $headerLine = array_shift($lines);
        $headers = array_map(
            fn($h) => strtolower(trim(str_replace(' ', '_', $h), " \t\"")),
            str_getcsv($headerLine),
        );

        $rows = [];
        foreach ($lines as $line) {
            $cols = str_getcsv($line);
            $row = [];
            foreach ($headers as $i => $h) {
                $row[$h] = isset($cols[$i]) ? trim($cols[$i]) : null;
            }
            $rows[] = $row;
        }
        return $rows;
    }

    private function normalizeDate(?string $raw): ?string
    {
        if (!$raw) return null;
        $raw = trim($raw);
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw)) return substr($raw, 0, 10);
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/', $raw, $m)) {
            $mm = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $dd = str_pad($m[2], 2, '0', STR_PAD_LEFT);
            $yy = $m[3];
            if (strlen($yy) === 2) $yy = (intval($yy) > 50 ? '19' : '20') . $yy;
            return "{$yy}-{$mm}-{$dd}";
        }
        return null;
    }
}

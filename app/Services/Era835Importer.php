<?php

namespace App\Services;

use App\Models\CarcCode;
use App\Models\Claim;
use App\Models\ClaimDenial;
use App\Models\ClaimPayment;
use App\Models\PaymentAllocation;
use App\Models\RarcCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Era835Importer — turns an X12 835 (Health Care Claim Payment/Advice) file
 * into rows on claim_payments + payment_allocations + claim_denials.
 *
 * The old RcmPhase2Controller::parseEra was a dry-run preview — it extracted
 * CAS adjustments and returned them in a JSON response, but never persisted
 * anything. This service is the missing importer that:
 *   1. Walks CLP loops (claim-level)
 *   2. Walks CAS segments inside each loop (claim AND service-line level)
 *   3. Matches CLP01 (patient control number) to existing claim_number
 *   4. Creates claim_payments + payment_allocations (the dollars)
 *   5. Creates claim_denials with denial_code, denial_category, denial_reason,
 *      appeal_deadline (derived from RARC + payer defaults)
 *
 * V2's Denial Inbox classifies on denial_category. Once this importer runs,
 * the Inbox can finally route denials out of the "Unknown" queue.
 *
 * Usage:
 *   $importer = new Era835Importer($rawX12Body, $agencyId, $userId);
 *   $result = $importer->run();
 *   // → ['imported'=>1, 'posted'=>47, 'matched'=>50, 'unmatched'=>2,
 *   //    'denials_created'=>12, 'check_number'=>'EFT123456', 'total_amount'=>...]
 */
class Era835Importer
{
    private string $raw;
    private int $agencyId;
    private ?int $userId;
    private ?int $billingClientId;
    private string $segTerm;
    private string $elemSep;

    /** Payer-specific appeal window defaults (in days). Used when an MOA/MA01
        RARC is present but the payer doesn't carry the timeframe inline. */
    private const APPEAL_DAYS_BY_PAYER = [
        'medicare'              => 120,
        'cms'                   => 120,
        'first coast'           => 120,
        'novitas'               => 120,
        'palmetto'              => 120,
        'florida blue'          => 180,
        'blue cross blue shield'=> 180,
        'bcbs'                  => 180,
        'unitedhealthcare'      => 90,
        'uhc'                   => 90,
        'optum'                 => 90,
        'cigna'                 => 60,
        'evernorth'             => 60,
        'aetna'                 => 60,
        'humana'                => 60,
        'tricare'               => 90,
    ];

    public function __construct(string $rawX12, int $agencyId, ?int $userId = null, ?int $billingClientId = null)
    {
        $this->raw = $rawX12;
        $this->agencyId = $agencyId;
        $this->userId = $userId;
        $this->billingClientId = $billingClientId;

        // ISA segment is exactly 106 chars; element separator is at position 3
        // and segment terminator at position 105. Falling back to common defaults
        // when the file is malformed.
        $this->elemSep = $rawX12[3] ?? '*';
        $this->segTerm = $rawX12[105] ?? '~';
    }

    public function run(): array
    {
        $stats = [
            'imported' => 0, 'posted' => 0, 'matched' => 0, 'unmatched' => 0,
            'denials_created' => 0, 'check_number' => null, 'total_amount' => 0,
            'payer_name' => null, 'errors' => [],
        ];

        // Walk every segment. The 835 is a flat list of segments grouped by
        // hierarchical context (we track current payer + current claim).
        $segments = array_filter(array_map('trim', explode($this->segTerm, $this->raw)));
        if (empty($segments)) {
            $stats['errors'][] = 'Empty or unparseable 835 file';
            return $stats;
        }

        // Parse pass 1: extract header info + CLP loops into structured array.
        $parsed = $this->parseSegments($segments);
        if (empty($parsed['claims'])) {
            $stats['errors'][] = 'No CLP segments found — file may not be an 835';
            return $stats;
        }

        $stats['payer_name'] = $parsed['payer_name'];
        $stats['check_number'] = $parsed['check_number'];
        $stats['total_amount'] = $parsed['total_amount'];

        // ── Idempotency guard ──────────────────────────────────────────
        // X12 TRN02 is the trace number — the canonical "this is the same
        // payment event" key. Combined with check_number (BPR's reference)
        // it's stable across retries of the same 835. If we already imported
        // this exact remit, return the prior result instead of double-posting.
        // Without this, re-uploading the same file (intentionally or via a
        // webhook retry) would create a second ClaimPayment and double the
        // posted_amount on every matched claim.
        if (!empty($parsed['trace_number']) || !empty($parsed['check_number'])) {
            $dupeQuery = ClaimPayment::where('agency_id', $this->agencyId);
            if (!empty($parsed['trace_number'])) {
                $dupeQuery->where('trace_number', $parsed['trace_number']);
            } elseif (!empty($parsed['check_number'])) {
                $dupeQuery->where('check_number', $parsed['check_number']);
            }
            $existing = $dupeQuery->first();
            if ($existing) {
                $stats['errors'][] = 'Already imported this remit on ' . $existing->created_at->toDateString() .
                    ' (ClaimPayment #' . $existing->id . '). Skipped to avoid duplicate posting.';
                $stats['already_imported'] = $existing->id;
                return $stats;
            }
        }

        // Cache CARC/RARC lookups for this run (one query, not per-denial).
        $carcMap = CarcCode::whereIn('code', $this->collectAllCarcCodes($parsed['claims']))->get()->keyBy('code');
        $rarcMap = RarcCode::whereIn('code', $this->collectAllRarcCodes($parsed['claims']))->get()->keyBy('code');

        // Wrap the write phase in a transaction — partial writes on this kind of
        // import are worse than a clean rollback.
        DB::transaction(function () use ($parsed, $carcMap, $rarcMap, &$stats) {
            // Create the parent ClaimPayment row.
            $payment = ClaimPayment::create([
                'agency_id'        => $this->agencyId,
                'billing_client_id'=> $this->billingClientId,
                'payer_name'       => $parsed['payer_name'] ?? 'Unknown',
                'payment_type'     => $parsed['payment_method'] === 'ACH' ? 'eft' : 'check',
                'check_number'     => $parsed['check_number'] ?: null,
                'trace_number'     => $parsed['trace_number'] ?: null,
                'payment_date'     => $parsed['payment_date'] ?: now()->toDateString(),
                'total_amount'     => $parsed['total_amount'],
                'posted_amount'    => 0, // updated below as allocations land
                'remaining_amount' => $parsed['total_amount'],
                'status'           => 'posted',
                'created_by'       => $this->userId,
                'posted_by'        => $this->userId,
                'posted_at'        => now(),
            ]);
            $stats['imported']++;

            $postedTotal = 0;

            foreach ($parsed['claims'] as $clp) {
                // Match CLP01 to an existing claim_number for this agency.
                $claim = Claim::where('agency_id', $this->agencyId)
                    ->where('claim_number', $clp['claim_number'])
                    ->first();

                if (!$claim) {
                    $stats['unmatched']++;
                    // Stash unmatched info on the payment notes for now — until
                    // we build the claim_payment_unmatched table.
                    $stats['errors'][] = "Unmatched CLP01={$clp['claim_number']} (payer ICN={$clp['payer_claim_id']})";
                    continue;
                }
                $stats['matched']++;

                // Sum CAS adjustments by group for the allocation row.
                $adjustmentByGroup = $this->sumAdjustmentsByGroup($clp['adjustments']);

                $allocation = PaymentAllocation::create([
                    'claim_payment_id'        => $payment->id,
                    'claim_id'                => $claim->id,
                    'service_line_number'     => null,
                    'charged_amount'          => $clp['charged_amount'],
                    'allowed_amount'          => $clp['charged_amount'] - ($adjustmentByGroup['CO'] ?? 0),
                    'paid_amount'             => $clp['paid_amount'],
                    'adjustment_amount'       => $adjustmentByGroup['CO'] ?? 0,
                    'patient_responsibility'  => $clp['patient_responsibility'] ?: ($adjustmentByGroup['PR'] ?? 0),
                    // Stash full CARC breakdown as JSON string — schema column is varchar.
                    'adjustment_codes'        => json_encode($this->enrichAdjustments($clp['adjustments'], $carcMap)),
                    'remark_codes'            => $clp['remarks'] ? implode(',', $clp['remarks']) : null,
                ]);
                $stats['posted']++;
                $postedTotal += (float) $clp['paid_amount'];

                // Update the claim itself with paid + balance.
                $newTotalPaid = (float) $claim->total_paid + (float) $clp['paid_amount'];
                $newBalance   = (float) $claim->total_charges - $newTotalPaid;
                $claimUpdate = [
                    'total_paid' => $newTotalPaid,
                    'balance'    => max(0, $newBalance),
                ];
                // CLP02 status codes: 1=primary paid, 2=secondary paid, 3=denied,
                // 4=denied, 19=processed-paid-secondary, 22=reversal.
                if (in_array($clp['status_code'], ['3', '4', '22'])) {
                    $claimUpdate['status'] = 'denied';
                } elseif ($newBalance <= 0.01) {
                    $claimUpdate['status'] = 'paid';
                    $claimUpdate['paid_date'] = $parsed['payment_date'] ?: now()->toDateString();
                } else {
                    // Partial payment received. Keep the balance visible in A/R aging.
                    // Marking these as 'paid' (the old behavior) hid every partially-
                    // paid claim from outstanding-A/R reports — board-level
                    // collections-rate looked artificially high.
                    $claimUpdate['status'] = 'partially_paid';
                }
                $claim->update($claimUpdate);

                // Create denial row when CLP02 indicates denial OR there's a
                // meaningful CO adjustment that wasn't just a contractual write-down.
                if ($this->shouldCreateDenial($clp)) {
                    $denialCarc = $this->pickDriverCarc($clp['adjustments']);
                    if ($denialCarc) {
                        $carcMeta = $carcMap->get($denialCarc['code']);
                        ClaimDenial::create([
                            'agency_id'         => $this->agencyId,
                            'claim_id'          => $claim->id,
                            'billing_client_id' => $claim->billing_client_id,
                            'denial_category'   => $carcMeta?->category ?? 'other',
                            'denial_code'       => $denialCarc['code'],
                            'denial_reason'     => $carcMeta?->description ?? "CARC {$denialCarc['code']}",
                            'denied_amount'     => $clp['charged_amount'] - $clp['paid_amount'],
                            'status'            => 'new',
                            'priority'          => $this->derivePriority($clp, $denialCarc, $parsed, $rarcMap),
                            'denial_date'       => $parsed['payment_date'] ?: now()->toDateString(),
                            'appeal_deadline'   => $this->deriveAppealDeadline($parsed, $rarcMap),
                            'created_by'        => $this->userId,
                        ]);
                        $stats['denials_created']++;
                    }
                }
            }

            // Update payment totals after all allocations land.
            $payment->update([
                'posted_amount'    => $postedTotal,
                'remaining_amount' => max(0, $parsed['total_amount'] - $postedTotal),
            ]);
        });

        return $stats;
    }

    /** Pass 1: walk segments, build structured intermediate representation. */
    private function parseSegments(array $segments): array
    {
        $out = [
            'payer_name'      => null,
            'check_number'    => '',
            'trace_number'    => '',
            'payment_date'    => null,
            'payment_method'  => 'CHK',
            'total_amount'    => 0.0,
            'claims'          => [],
        ];

        $currentClaim = null;
        $currentLine = null;

        foreach ($segments as $segment) {
            $parts = explode($this->elemSep, $segment);
            $tag = $parts[0];

            switch ($tag) {
                case 'BPR':
                    // BPR02 = total payment, BPR04 = payment method (ACH/CHK), BPR16 = effective date YYYYMMDD
                    $out['total_amount']   = (float) ($parts[2] ?? 0);
                    $out['payment_method'] = $parts[4] ?? 'CHK';
                    if (!empty($parts[16])) {
                        $out['payment_date'] = $this->parseDate($parts[16]);
                    }
                    break;

                case 'TRN':
                    $out['check_number'] = $parts[2] ?? '';
                    $out['trace_number'] = $parts[3] ?? '';
                    break;

                case 'N1':
                    if (($parts[1] ?? '') === 'PR') {
                        $out['payer_name'] = trim($parts[2] ?? '');
                    }
                    break;

                case 'CLP':
                    // Close previous claim
                    if ($currentClaim) {
                        if ($currentLine) {
                            $currentClaim['service_lines'][] = $currentLine;
                            $currentLine = null;
                        }
                        $out['claims'][] = $currentClaim;
                    }
                    $currentClaim = [
                        'claim_number'           => $parts[1] ?? '',
                        'status_code'            => $parts[2] ?? '',
                        'charged_amount'         => (float) ($parts[3] ?? 0),
                        'paid_amount'            => (float) ($parts[4] ?? 0),
                        'patient_responsibility' => (float) ($parts[5] ?? 0),
                        'filing_indicator'       => $parts[6] ?? '',
                        'payer_claim_id'         => $parts[7] ?? '',
                        'service_date'           => null,
                        'adjustments'            => [],
                        'service_lines'          => [],
                        'remarks'                => [],
                    ];
                    break;

                case 'CAS':
                    // CAS*GROUP*CODE*AMOUNT*…*CODE*AMOUNT — pairs can repeat up to 6 times
                    if (!$currentClaim) break;
                    $group = $parts[1] ?? '';
                    for ($i = 2; $i + 1 < count($parts); $i += 3) {
                        $code = trim($parts[$i] ?? '');
                        $amt  = (float) ($parts[$i + 1] ?? 0);
                        if ($code === '' || $amt == 0.0) continue;
                        $adj = [
                            'group'  => $group,
                            'code'   => $code,
                            'amount' => $amt,
                        ];
                        // Attach to current line if we're inside a SVC loop, else to the claim
                        if ($currentLine) {
                            $currentLine['adjustments'][] = $adj;
                        } else {
                            $currentClaim['adjustments'][] = $adj;
                        }
                    }
                    break;

                case 'SVC':
                    // Open a new service line — close the previous one first
                    if ($currentClaim && $currentLine) {
                        $currentClaim['service_lines'][] = $currentLine;
                    }
                    if ($currentClaim) {
                        $cpt = $parts[1] ?? '';
                        $cptParts = explode(':', $cpt);
                        $currentLine = [
                            'cpt_code'        => $cptParts[1] ?? $cptParts[0] ?? '',
                            'modifiers'       => implode(',', array_slice($cptParts, 2)),
                            'charged_amount'  => (float) ($parts[2] ?? 0),
                            'paid_amount'     => (float) ($parts[3] ?? 0),
                            'units'           => (float) ($parts[5] ?? 1),
                            'adjustments'    => [],
                        ];
                    }
                    break;

                case 'DTM':
                    // Service date qualifier 232=claim-level, 472=line-level
                    if (!$currentClaim) break;
                    $qual = $parts[1] ?? '';
                    if (in_array($qual, ['232', '472']) && !empty($parts[2])) {
                        $currentClaim['service_date'] = $this->parseDate($parts[2]);
                    }
                    break;

                case 'MOA':
                case 'MIA':
                    // Remark codes — these carry RARC like MA01 / N350
                    if (!$currentClaim) break;
                    $start = $tag === 'MOA' ? 3 : 4; // first reference position
                    for ($i = $start; $i < count($parts); $i++) {
                        $code = trim($parts[$i] ?? '');
                        if ($code !== '' && preg_match('/^[NMR][A-Z]?\d+/', $code)) {
                            $currentClaim['remarks'][] = $code;
                        }
                    }
                    break;
            }
        }

        // Close trailing claim/line
        if ($currentClaim) {
            if ($currentLine) $currentClaim['service_lines'][] = $currentLine;
            $out['claims'][] = $currentClaim;
        }

        return $out;
    }

    /** Convert CCYYMMDD → YYYY-MM-DD. Returns null when format unrecognized. */
    private function parseDate(string $raw): ?string
    {
        if (preg_match('/^(\d{4})(\d{2})(\d{2})/', $raw, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }
        return null;
    }

    private function sumAdjustmentsByGroup(array $adjustments): array
    {
        $sum = [];
        foreach ($adjustments as $a) {
            $g = $a['group'] ?? 'OA';
            $sum[$g] = ($sum[$g] ?? 0) + $a['amount'];
        }
        return $sum;
    }

    private function enrichAdjustments(array $adjustments, $carcMap): array
    {
        return array_map(function ($a) use ($carcMap) {
            $meta = $carcMap->get($a['code']);
            return [
                'group'    => $a['group'],
                'code'     => $a['code'],
                'amount'   => $a['amount'],
                'reason'   => $meta?->description ?? "CARC {$a['code']}",
                'category' => $meta?->category ?? 'other',
            ];
        }, $adjustments);
    }

    /** Should we create a claim_denials row for this CLP? */
    private function shouldCreateDenial(array $clp): bool
    {
        // CLP02 status codes 3=denied (primary), 4=denied (secondary), 22=reversal
        if (in_array($clp['status_code'], ['3', '4', '22'])) return true;
        // Partial denial — has CO adjustment that isn't just patient resp
        foreach ($clp['adjustments'] as $a) {
            if ($a['group'] === 'CO' && $a['amount'] > 0 && !in_array($a['code'], ['45'])) {
                // CARC 45 (fee-schedule contractual) is normal — only flag others
                return true;
            }
        }
        return false;
    }

    /** Pick the CARC that "drove" the denial — biggest CO-group adjustment,
        or the first non-CO if no CO present. */
    private function pickDriverCarc(array $adjustments): ?array
    {
        $coAdjustments = array_filter($adjustments, fn($a) => $a['group'] === 'CO');
        if (!empty($coAdjustments)) {
            usort($coAdjustments, fn($a, $b) => $b['amount'] <=> $a['amount']);
            return reset($coAdjustments) ?: null;
        }
        return $adjustments[0] ?? null;
    }

    /** Priority: high if denied >$500 OR appeal deadline <30d, else normal. */
    private function derivePriority(array $clp, array $carc, array $parsed, $rarcMap): string
    {
        $denied = $clp['charged_amount'] - $clp['paid_amount'];
        if ($denied >= 500) return 'high';
        $deadline = $this->deriveAppealDeadline($parsed, $rarcMap);
        if ($deadline) {
            $days = now()->diffInDays($deadline, false);
            if ($days < 30) return 'high';
        }
        return 'normal';
    }

    /** Compute appeal_deadline from MOA/MA01 RARC + payer defaults. */
    private function deriveAppealDeadline(array $parsed, $rarcMap): ?string
    {
        // Look for an appeal-window-triggering RARC across ALL claims in this remit
        // (the appeal window applies to the whole remit, typically).
        $hasAppealRarc = false;
        foreach ($parsed['claims'] as $c) {
            foreach ($c['remarks'] as $r) {
                $rarc = $rarcMap->get($r);
                if ($rarc?->triggers_appeal_window) {
                    $hasAppealRarc = true;
                    break 2;
                }
            }
        }
        if (!$hasAppealRarc) return null;

        $payer = strtolower($parsed['payer_name'] ?? '');
        $days = 60; // default for unknown commercial payers
        foreach (self::APPEAL_DAYS_BY_PAYER as $needle => $window) {
            if (str_contains($payer, $needle)) {
                $days = $window;
                break;
            }
        }

        $denialDate = $parsed['payment_date'] ?? now()->toDateString();
        return now()->parse($denialDate)->addDays($days)->toDateString();
    }

    private function collectAllCarcCodes(array $claims): array
    {
        $codes = [];
        foreach ($claims as $c) {
            foreach ($c['adjustments'] as $a) $codes[$a['code']] = true;
            foreach ($c['service_lines'] as $sl) {
                foreach ($sl['adjustments'] ?? [] as $a) $codes[$a['code']] = true;
            }
        }
        return array_keys($codes);
    }

    private function collectAllRarcCodes(array $claims): array
    {
        $codes = [];
        foreach ($claims as $c) {
            foreach ($c['remarks'] as $r) $codes[$r] = true;
        }
        return array_keys($codes);
    }
}

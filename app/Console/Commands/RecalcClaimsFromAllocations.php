<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Roll per-service-line payment_allocations data UP into the claim's
 * aggregate columns when they disagree.
 *
 * Background: Era835Importer reliably writes per-allocation
 * patient_responsibility, adjustment_amount, paid_amount on
 * payment_allocations rows. But its claim-level total aggregation
 * occasionally drops the patient_responsibility roll-up (and rarely,
 * adjustments) — leaving the claim with a positive `balance` that
 * SHOULD have been categorized as patient-owes.
 *
 * Audit on 2026-05-15 found 1 claim in this state (claim 3592,
 * MARIANA AFANADOR, 530Z103079: alloc.pt_resp=$35, claim.pt_resp=$0).
 * The audit found 256 OTHER claims where the inverse holds —
 * claim totals are correct but per-allocation rows are empty
 * (Pattern A). This command DELIBERATELY skips Pattern A: backfilling
 * per-allocation data from claim totals is impossible (we don't
 * have the resolution to break $40.83 of adjustments down to
 * individual service lines). Pattern A is a known issue logged in
 * memory; this fix is Pattern B only.
 *
 * Match criterion: at least one of alloc.patient_resp_sum or
 * alloc.adj_sum is > 0 AND the claim's column is less than the
 * allocation sum. That isolates Pattern B (allocation has data the
 * claim doesn't reflect) and excludes Pattern A (claim has totals
 * the allocations don't).
 *
 * Usage:
 *   php artisan claims:recalc-from-allocations --dry-run    # preview
 *   php artisan claims:recalc-from-allocations              # apply
 *
 * Idempotent — re-running confirms nothing left to fix.
 */
class RecalcClaimsFromAllocations extends Command
{
    protected $signature = 'claims:recalc-from-allocations {--dry-run : Preview without writing}';

    protected $description = 'Roll up payment_allocations.{patient_responsibility, adjustment_amount} to claims when allocations have data the claim record is missing (Pattern B from the 2026-05-15 audit).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Pattern B: allocations have non-zero PT resp or adj that
        // the claim columns don't reflect. We coerce the claim
        // column to 0 when NULL so the comparison is a clean math.
        //
        // SAFETY GATE — only trust allocations whose charged_amount
        // sum agrees with the claim's total_charges (±$1 for cent
        // rounding). 2026-05-15 audit found an allocation for
        // claim 3552 with charged_amount=$10,275.92 against a
        // claim.total_charges=$185.84 (55x off; clearly corrupt).
        // Rolling that allocation up would have produced a
        // $10,108 patient-responsibility entry and could have
        // triggered a $10K patient statement. Trust the claim
        // when the allocation disagrees on what was billed.
        $rows = DB::select("
            SELECT
                c.id, c.claim_number, c.patient_name, c.payer_name, c.status,
                c.total_charges, c.total_paid, c.adjustments, c.patient_responsibility, c.balance,
                COALESCE(SUM(pa.patient_responsibility), 0) AS alloc_ptresp_sum,
                COALESCE(SUM(pa.adjustment_amount), 0)     AS alloc_adj_sum,
                COALESCE(SUM(pa.paid_amount), 0)           AS alloc_paid_sum,
                COALESCE(SUM(pa.charged_amount), 0)        AS alloc_charged_sum
            FROM claims c
            JOIN payment_allocations pa ON pa.claim_id = c.id
            WHERE c.deleted_at IS NULL
            GROUP BY c.id
            HAVING (
                COALESCE(SUM(pa.patient_responsibility), 0) > COALESCE(c.patient_responsibility, 0)
              OR COALESCE(SUM(pa.adjustment_amount),     0) > COALESCE(c.adjustments,             0)
            )
            -- Skip claims where the allocation's charged_amount sum
            -- disagrees with the claim's total_charges. Those have
            -- corrupt allocations the rollup would propagate as bad
            -- patient bills.
              AND ABS(COALESCE(SUM(pa.charged_amount), 0) - COALESCE(c.total_charges, 0)) < 1.00
        ");

        if (empty($rows)) {
            $this->info('No claims with Pattern B mismatch (already clean).');
            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d claim(s) where allocations have data the claim record is missing%s.',
            count($rows), $dryRun ? ' (dry run)' : ''));

        $updated = 0;
        $reconciledStatusChanges = 0;

        DB::transaction(function () use ($rows, $dryRun, &$updated, &$reconciledStatusChanges) {
            foreach ($rows as $r) {
                $newPtResp = (float) $r->alloc_ptresp_sum;
                $newAdj    = (float) $r->alloc_adj_sum;
                // total_paid mirrors allocation paid_sum — claim already
                // has this right, but recomputing is harmless and
                // self-checking.
                $newTotalPaid = (float) $r->alloc_paid_sum;
                $charges      = (float) $r->total_charges;
                // Balance is what's STILL outstanding from the payer
                // perspective. Patient responsibility doesn't reduce
                // payer-balance — it's owed by the patient instead.
                // But the conventional Credentik balance column means
                // "what's left to collect" overall: charges - paid -
                // adjustments - patient_resp. The patient owes the
                // last term separately; the claim-level balance going
                // to zero means "fully reconciled at the claim level."
                $newBalance = max(0, $charges - $newTotalPaid - $newAdj - $newPtResp);

                // Status: balance zero means fully reconciled.
                // partial_paid stays when there's STILL balance, OR
                // when patient responsibility was carved out and the
                // patient hasn't paid yet. paid means everything is
                // closed at the claim level (patient bill goes via
                // patient_statements, not via claim.balance).
                $newStatus = $r->status;
                if ($newBalance <= 0.01) {
                    // If patient still owes (PT resp > 0), keep as
                    // partial_paid so the operator knows there's a
                    // patient statement to send. If patient owes $0
                    // too, the claim is genuinely done.
                    $newStatus = $newPtResp > 0.01 ? 'partial_paid' : 'paid';
                }

                $this->line(sprintf(
                    '  claim #%d %s (%s): pt_resp %s→%s, adj %s→%s, bal %s→%s, status %s→%s',
                    $r->id, $r->claim_number, $r->patient_name,
                    number_format((float) $r->patient_responsibility, 2),
                    number_format($newPtResp, 2),
                    number_format((float) $r->adjustments, 2),
                    number_format($newAdj, 2),
                    number_format((float) $r->balance, 2),
                    number_format($newBalance, 2),
                    $r->status, $newStatus,
                ));

                if ($newStatus !== $r->status) {
                    $reconciledStatusChanges++;
                }

                if (!$dryRun) {
                    DB::table('claims')->where('id', $r->id)->update([
                        'patient_responsibility' => $newPtResp,
                        'adjustments'            => $newAdj,
                        'total_paid'             => $newTotalPaid,
                        'balance'                => $newBalance,
                        'status'                 => $newStatus,
                        'updated_at'             => now(),
                    ]);
                    $updated++;
                }
            }
        });

        if (!$dryRun) {
            $this->info(sprintf('Recalculated %d claim(s); %d status change(s).', $updated, $reconciledStatusChanges));
        } else {
            $this->info(sprintf('Would recalculate %d claim(s); %d status change(s).', count($rows), $reconciledStatusChanges));
        }

        return self::SUCCESS;
    }
}

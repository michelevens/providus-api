<?php

namespace App\Console\Commands;

use App\Models\ClaimPayment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Find ClaimPayment rows that are duplicates of each other —
 * same (agency_id, check_number, payer_name, total_amount,
 * payment_date) — and consolidate them.
 *
 * Strategy: keep the oldest (lowest id) per group, soft-delete the
 * others. The new destroyPayment cascade handles allocation cleanup
 * + claim recalculation automatically, so this command stays small.
 *
 * Use case: re-running the same CSV bulk-import created duplicate
 * payment rows before the bulkMatchPayments dedup guard shipped.
 *
 * Usage:
 *   php artisan payments:dedupe --dry-run   # preview
 *   php artisan payments:dedupe             # apply
 *
 * Idempotent — re-running confirms zero remaining dupes.
 */
class DedupePayments extends Command
{
    protected $signature = 'payments:dedupe {--dry-run : Preview what would be deleted}';

    protected $description = 'Find and consolidate duplicate ClaimPayment rows. Keeps oldest, soft-deletes the rest, cascades allocation cleanup.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $groups = ClaimPayment::whereNotNull('check_number')
            ->where('check_number', '!=', '')
            ->select(
                'agency_id',
                'check_number',
                'payer_name',
                'total_amount',
                'payment_date',
                DB::raw('count(*) as n'),
                DB::raw('array_agg(id ORDER BY created_at) as ids'),
            )
            ->groupBy('agency_id', 'check_number', 'payer_name', 'total_amount', 'payment_date')
            ->havingRaw('count(*) > 1')
            ->get();

        if ($groups->isEmpty()) {
            $this->info('No duplicate payments found.');
            return self::SUCCESS;
        }

        $totalExtras = $groups->sum(fn ($g) => $g->n - 1);
        $phantomDollars = $groups->sum(fn ($g) => ($g->n - 1) * (float) $g->total_amount);

        $this->info(sprintf(
            'Found %d duplicate group(s) → %d extra payment row(s) → $%s phantom dollars' . ($dryRun ? ' (dry run)' : ''),
            $groups->count(),
            $totalExtras,
            number_format($phantomDollars, 2),
        ));

        $idsToDelete = [];
        foreach ($groups as $g) {
            // Parse the Postgres-style array `{106,183}` into a PHP array.
            $ids = is_string($g->ids)
                ? array_filter(explode(',', trim($g->ids, '{}')))
                : (array) $g->ids;
            $ids = array_map('intval', $ids);
            $keeper = array_shift($ids); // already sorted by created_at ASC
            $toDelete = $ids;

            $this->line(sprintf(
                '  check %s (%s, $%s): keep #%d, delete %s',
                $g->check_number,
                $g->payer_name ?: '(no payer)',
                number_format((float) $g->total_amount, 2),
                $keeper,
                implode(', #', array_map(fn ($i) => '#' . $i, $toDelete)),
            ));
            $idsToDelete = array_merge($idsToDelete, $toDelete);
        }

        if ($dryRun) {
            return self::SUCCESS;
        }

        // Delete one at a time so each goes through the model's
        // boot logic (Auditable trait + SoftDeletes). We deliberately
        // do NOT use mass delete, because we want each to hit the
        // destroyPayment cascade equivalent — but here we're operating
        // on the model directly, not the controller. So we replicate
        // the cascade inline.
        $cascaded = 0;
        DB::transaction(function () use ($idsToDelete, &$cascaded) {
            foreach ($idsToDelete as $id) {
                $payment = ClaimPayment::find($id);
                if (!$payment) continue;
                $affectedClaimIds = $payment->allocations()->pluck('claim_id')->unique()->all();
                $payment->allocations()->delete();
                $payment->delete();
                foreach ($affectedClaimIds as $cid) {
                    $claim = \App\Models\Claim::find($cid);
                    if (!$claim) continue;
                    $remainingPaid = (float) \App\Models\PaymentAllocation::where('claim_id', $cid)->sum('paid_amount');
                    $remainingPtResp = (float) \App\Models\PaymentAllocation::where('claim_id', $cid)->sum('patient_responsibility');
                    $claim->total_paid = $remainingPaid;
                    $claim->patient_responsibility = $remainingPtResp;
                    $claim->balance = (float) $claim->total_charges - $remainingPaid - (float) ($claim->adjustments ?? 0);
                    if (!in_array($claim->status, ['denied', 'appealed', 'rejected', 'written_off'], true)) {
                        if ($claim->balance <= 0.005 && $remainingPaid > 0) {
                            $claim->status = 'paid';
                        } elseif ($remainingPaid > 0) {
                            $claim->status = 'partial_paid';
                        } else {
                            $claim->status = 'submitted';
                        }
                    }
                    $claim->save();
                    $cascaded++;
                }
            }
        });

        $this->info(sprintf('Soft-deleted %d duplicate payment(s), recalculated %d affected claim(s).', count($idsToDelete), $cascaded));
        return self::SUCCESS;
    }
}

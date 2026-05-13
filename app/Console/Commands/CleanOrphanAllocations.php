<?php

namespace App\Console\Commands;

use App\Models\Claim;
use App\Models\ClaimPayment;
use App\Models\PaymentAllocation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Find PaymentAllocation rows whose parent ClaimPayment is missing or
 * soft-deleted, and clean them up. Reproduces what the new
 * destroyPayment cascade does for any historical orphans that
 * predate that fix.
 *
 * For each orphan:
 *   1. Hard-delete the allocation.
 *   2. Recalculate the linked Claim's total_paid + balance + status
 *      from its remaining (still-valid) allocations.
 *
 * One-shot backfill: `php artisan payments:clean-orphans`
 * Dry run preview:   `php artisan payments:clean-orphans --dry-run`
 *
 * Idempotent — re-running confirms zero orphans remain.
 */
class CleanOrphanAllocations extends Command
{
    protected $signature = 'payments:clean-orphans {--dry-run : Show what would be deleted without changing anything}';

    protected $description = 'Hard-delete PaymentAllocations whose parent ClaimPayment is missing or soft-deleted, and recalculate the affected claims';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Valid = exists AND not soft-deleted. We deliberately do NOT
        // include trashed payments — those are the orphan source.
        $validIds = ClaimPayment::pluck('id')->all();
        $orphans = PaymentAllocation::whereNotIn('claim_payment_id', $validIds)->get();

        if ($orphans->isEmpty()) {
            $this->info('No orphan allocations found.');
            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Found %d orphan allocation(s) totaling $%s' . ($dryRun ? ' (dry run)' : ''),
            $orphans->count(),
            number_format((float) $orphans->sum('paid_amount'), 2),
        ));

        $affectedClaimIds = $orphans->pluck('claim_id')->unique()->filter()->all();

        if ($dryRun) {
            foreach ($orphans as $o) {
                $this->line(sprintf(
                    '  alloc #%d → claim #%d ($%s) [parent payment #%d missing/soft-deleted]',
                    $o->id,
                    $o->claim_id ?? 0,
                    number_format((float) $o->paid_amount, 2),
                    $o->claim_payment_id,
                ));
            }
            $this->info(sprintf('Would touch %d claim(s).', count($affectedClaimIds)));
            return self::SUCCESS;
        }

        DB::transaction(function () use ($orphans, $affectedClaimIds) {
            // Hard-delete the orphan allocations.
            PaymentAllocation::whereIn('id', $orphans->pluck('id'))->delete();

            // Recalculate each affected claim from its remaining
            // (still-valid) allocations.
            foreach ($affectedClaimIds as $cid) {
                $claim = Claim::find($cid);
                if (!$claim) continue;
                $remainingPaid = (float) PaymentAllocation::where('claim_id', $cid)->sum('paid_amount');
                $remainingPtResp = (float) PaymentAllocation::where('claim_id', $cid)->sum('patient_responsibility');
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
            }
        });

        $this->info(sprintf('Deleted %d orphan(s), recalculated %d claim(s).', $orphans->count(), count($affectedClaimIds)));
        return self::SUCCESS;
    }
}

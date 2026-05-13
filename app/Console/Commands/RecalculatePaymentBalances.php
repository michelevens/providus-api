<?php

namespace App\Console\Commands;

use App\Models\ClaimPayment;
use Illuminate\Console\Command;

/**
 * Walk every ClaimPayment and recalculate() it.
 *
 * Use case: the historical CSV bulkMatchPayments path created allocations
 * but never called recalculate(), so posted_amount + remaining_amount
 * drifted to whatever values they were initialized with — often $0 on
 * the parent payment row while the allocations actually summed to
 * hundreds or thousands.
 *
 * One-shot backfill: `php artisan payments:recalculate`
 *
 * Idempotent — re-running just confirms current state. Logs which
 * payments changed so the operator sees the impact.
 */
class RecalculatePaymentBalances extends Command
{
    protected $signature = 'payments:recalculate {--agency= : Only process this agency_id} {--dry-run : Show what would change without saving}';

    protected $description = 'Recalculate posted_amount + remaining_amount on every ClaimPayment from its allocations';

    public function handle(): int
    {
        $query = ClaimPayment::query();
        if ($aid = $this->option('agency')) {
            $query->where('agency_id', $aid);
        }
        $dryRun = (bool) $this->option('dry-run');

        $total = $query->count();
        $this->info("Found {$total} payment(s) to inspect" . ($dryRun ? ' (dry run)' : ''));

        $changed = 0;
        $unchanged = 0;
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunk(100, function ($payments) use (&$changed, &$unchanged, $dryRun, $bar) {
            foreach ($payments as $payment) {
                $oldPosted = (float) $payment->posted_amount;
                $oldRemaining = (float) $payment->remaining_amount;
                $newPosted = (float) $payment->allocations()->sum('paid_amount');
                $newRemaining = (float) $payment->total_amount - $newPosted;
                $bar->advance();

                $diffPosted = abs($newPosted - $oldPosted);
                $diffRemaining = abs($newRemaining - $oldRemaining);
                if ($diffPosted < 0.01 && $diffRemaining < 0.01) {
                    $unchanged++;
                    continue;
                }

                $this->newLine();
                $this->line(sprintf(
                    '  pmt #%d (%s): posted %s → %s · remaining %s → %s',
                    $payment->id,
                    $payment->check_number ?: $payment->trace_number ?: '?',
                    number_format($oldPosted, 2),
                    number_format($newPosted, 2),
                    number_format($oldRemaining, 2),
                    number_format($newRemaining, 2),
                ));

                if (!$dryRun) {
                    $payment->recalculate();
                }
                $changed++;
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. {$changed} changed, {$unchanged} unchanged.");

        return self::SUCCESS;
    }
}

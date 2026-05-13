<?php

namespace App\Console\Commands;

use App\Models\Claim;
use Illuminate\Console\Command;

/**
 * Pro-rate claims.total_paid across service_lines.paid_amount where
 * the line value is missing.
 *
 * Why: the bulk-match-payments path historically set claims.total_paid
 * directly without touching service_lines.paid_amount. That left every
 * CPT-level paid analysis (rate analysis, payer-detail CPT tab,
 * underpayments) silently reading $0 for every line. The
 * RcmController@bulkMatchPayments now pro-rates on the way in; this
 * command repairs the data that arrived before that fix shipped.
 *
 * Targets only claims that have:
 *   - total_paid > 0
 *   - at least one service line
 *   - lines whose summed paid_amount is < total_paid (i.e. broken)
 *
 * Idempotent: re-running confirms zero remaining mismatched claims.
 * Pro-rate weight: each line's charges / total line charges. The last
 * line absorbs the rounding remainder so the line sum equals
 * claim.total_paid exactly.
 *
 * Usage:
 *   php artisan claims:prorate-service-lines --dry-run   # preview
 *   php artisan claims:prorate-service-lines             # apply
 */
class ProrateServiceLines extends Command
{
    protected $signature = 'claims:prorate-service-lines {--dry-run : Preview what would be updated}';
    protected $description = 'Allocate claims.total_paid across service line paid_amount columns when missing.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Find candidate claims. We can't easily compare aggregates in
        // SQL without a subquery; pull all paid claims with lines and
        // filter in PHP. The set is small enough (few thousand at most)
        // that this is fine.
        $query = Claim::query()
            ->withoutGlobalScopes() // hit every agency
            ->where('total_paid', '>', 0)
            ->whereHas('serviceLines');

        $total = $query->count();
        $this->info("Scanning {$total} paid claims with service lines…");

        $touched = 0;
        $skipped = 0;
        $totalRedistributed = 0.0;

        $query->with('serviceLines')->chunkById(200, function ($claims) use (&$touched, &$skipped, &$totalRedistributed, $dryRun) {
            foreach ($claims as $claim) {
                $linesSumPaid = $claim->serviceLines->sum(fn ($sl) => (float) $sl->paid_amount);
                $totalPaid = (float) $claim->total_paid;
                if ($linesSumPaid >= $totalPaid - 0.01) {
                    $skipped++;
                    continue;
                }
                $sumCharges = $claim->serviceLines->sum(fn ($sl) => (float) $sl->charges);
                if ($sumCharges <= 0.005) {
                    $skipped++;
                    continue;
                }

                if ($dryRun) {
                    $this->line(sprintf(
                        '  claim #%d total_paid=$%s lines=%d sumLinePaid=$%s → redistribute',
                        $claim->id,
                        number_format($totalPaid, 2),
                        $claim->serviceLines->count(),
                        number_format($linesSumPaid, 2),
                    ));
                    $touched++;
                    $totalRedistributed += ($totalPaid - $linesSumPaid);
                    continue;
                }

                // Apply pro-rate. Same algorithm as the controller — last
                // line absorbs the remainder so the sum exactly matches
                // claim.total_paid.
                $lines = $claim->serviceLines->values();
                $accumPaid = 0.0;
                $lastIdx = $lines->count() - 1;
                foreach ($lines as $idx => $sl) {
                    if ((float) $sl->paid_amount > 0.005) {
                        $accumPaid += (float) $sl->paid_amount;
                        continue;
                    }
                    $linePaid = $idx === $lastIdx
                        ? round($totalPaid - $accumPaid, 2)
                        : round($totalPaid * ((float) $sl->charges / $sumCharges), 2);
                    $sl->paid_amount = max(0, $linePaid);
                    $sl->save();
                    $accumPaid += $sl->paid_amount;
                }
                $touched++;
                $totalRedistributed += ($totalPaid - $linesSumPaid);
            }
        });

        $this->info(sprintf(
            "%s — %d claim(s) %s, %d already balanced. \$%s redistributed across service lines.",
            $dryRun ? 'Dry run' : 'Done',
            $touched,
            $dryRun ? 'would be updated' : 'updated',
            $skipped,
            number_format($totalRedistributed, 2),
        ));

        return self::SUCCESS;
    }
}

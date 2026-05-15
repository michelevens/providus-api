<?php

namespace App\Console\Commands;

use App\Models\Payer;
use App\Services\PayerResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Walk every claim + charge_entries row that has a payer_name but
 * no payer_id, run it through PayerResolver, and stamp the FK.
 *
 * Output is grouped by source-table so the auto-create count is easy
 * to spot. By default the command is idempotent — it only touches
 * rows whose payer_id is currently null. --force re-resolves
 * everything (useful when alias data has been updated and you want
 * existing claims to re-bucket).
 *
 * Usage:
 *   php artisan payers:backfill --dry-run    # preview
 *   php artisan payers:backfill              # apply
 *   php artisan payers:backfill --force      # re-resolve already-set rows too
 *
 * Bypasses Eloquent model events so the per-row resolve doesn't fire
 * Auditable updates — backfilling 8k rows would generate 8k audit
 * log entries for what is effectively a schema-shaped change, not
 * a meaningful business event.
 */
class BackfillPayerIds extends Command
{
    protected $signature = 'payers:backfill {--dry-run : Preview without writing} {--force : Re-resolve rows that already have a payer_id}';

    protected $description = 'Stamp payer_id on claims + charge_entries from their payer_name using PayerResolver.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force  = (bool) $this->option('force');

        $modeLabel = $dryRun ? ' (dry run)' : '';
        $this->info("Backfilling payer_id on claims + charge_entries{$modeLabel}…");
        if ($force) {
            $this->warn('  --force: re-resolving rows that already have payer_id');
        }

        // Warm the resolver cache once so the per-row resolve is fast.
        PayerResolver::flushCache();

        $beforeAutoCreate = Payer::where('needs_review', true)->count();

        $stats = [
            'claims' => $this->backfillTable('claims', $dryRun, $force),
            'charge_entries' => $this->backfillTable('charge_entries', $dryRun, $force),
        ];

        $afterAutoCreate = Payer::where('needs_review', true)->count();
        $newlyCreated = $afterAutoCreate - $beforeAutoCreate;

        $this->line('');
        $this->info('Summary:');
        foreach ($stats as $table => $s) {
            $this->line(sprintf(
                '  %-18s scanned=%d  resolved=%d  unchanged=%d  empty_name=%d  failed=%d',
                $table, $s['scanned'], $s['resolved'], $s['unchanged'], $s['empty_name'], $s['failed'],
            ));
        }
        $this->line('');
        $this->info(sprintf(
            'Payers flagged needs_review: %d before → %d after (%s%d new)',
            $beforeAutoCreate, $afterAutoCreate,
            $newlyCreated >= 0 ? '+' : '',
            $newlyCreated,
        ));

        if ($newlyCreated > 0 && !$dryRun) {
            $this->warn('Review the auto-created payers via /superadmin or POST /payers?needs_review=1; merge duplicates into canonicals.');
        }

        return self::SUCCESS;
    }

    /**
     * Scan one table and stamp payer_id where missing (or always,
     * with --force). Returns a counts array.
     */
    private function backfillTable(string $table, bool $dryRun, bool $force): array
    {
        $counts = ['scanned' => 0, 'resolved' => 0, 'unchanged' => 0, 'empty_name' => 0, 'failed' => 0];

        $query = DB::table($table)->select(['id', 'payer_name', 'payer_id']);
        if (!$force) {
            $query->whereNull('payer_id');
        }

        // chunkById processes in 500-row batches with stable ordering
        // — safe even if rows are being inserted concurrently.
        $query->orderBy('id')->chunkById(500, function ($rows) use ($table, $dryRun, &$counts) {
            foreach ($rows as $row) {
                $counts['scanned']++;

                $name = trim((string) ($row->payer_name ?? ''));
                if ($name === '') {
                    $counts['empty_name']++;
                    continue;
                }

                try {
                    $resolved = PayerResolver::resolve($name);
                } catch (\Throwable $e) {
                    $counts['failed']++;
                    $this->error("  failed to resolve {$table}#{$row->id} (payer_name={$name}): {$e->getMessage()}");
                    continue;
                }

                if ($resolved === null) {
                    $counts['failed']++;
                    continue;
                }
                if ((int) ($row->payer_id ?? 0) === (int) $resolved) {
                    $counts['unchanged']++;
                    continue;
                }

                if (!$dryRun) {
                    DB::table($table)->where('id', $row->id)->update(['payer_id' => $resolved]);
                }
                $counts['resolved']++;
            }
        }, 'id');

        return $counts;
    }
}

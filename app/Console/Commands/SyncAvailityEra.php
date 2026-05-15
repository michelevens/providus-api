<?php

namespace App\Console\Commands;

use App\Models\AgencyConfig;
use App\Services\AvailityEraSyncService;
use Illuminate\Console\Command;

/**
 * Nightly ERA sync from Availity for every agency that has API
 * creds configured. Cursor-based — each run starts from the
 * received_at of the newest file imported by the previous successful
 * run, so we only pull deltas, not the whole history.
 *
 * Usage:
 *   php artisan era:sync-availity                # all eligible agencies
 *   php artisan era:sync-availity --agency=2     # one agency
 *   php artisan era:sync-availity --from-scratch # ignore cursor (re-pull last 90 days)
 *
 * Per agency the command short-circuits if availity_client_id or
 * availity_client_secret are null — no error, no log entry. Adding
 * creds in Settings is what activates an agency for sync.
 */
class SyncAvailityEra extends Command
{
    protected $signature = 'era:sync-availity {--agency= : Run for a single agency id} {--from-scratch : Ignore stored cursor; pull last 90 days fresh}';

    protected $description = 'Pull ERA files from Availity for every agency that has API credentials configured.';

    public function handle(AvailityEraSyncService $orchestrator): int
    {
        $agencyFilter = $this->option('agency');
        $fromScratch  = (bool) $this->option('from-scratch');

        $query = AgencyConfig::query()
            ->whereNotNull('availity_client_id')
            ->whereNotNull('availity_client_secret');
        if ($agencyFilter) {
            $query->where('agency_id', $agencyFilter);
        }
        $configs = $query->get();

        if ($configs->isEmpty()) {
            $this->info('No agencies with Availity credentials configured.');
            return self::SUCCESS;
        }

        $this->info("Running Availity ERA sync for {$configs->count()} agency(ies)…");

        $totalImported = 0;
        $totalSkipped  = 0;
        $totalErrored  = 0;

        foreach ($configs as $cfg) {
            $filters = [];
            if (!$fromScratch) {
                $cursor = $orchestrator->cursorForAgency($cfg->agency_id);
                if ($cursor) {
                    $filters['cursor'] = $cursor;
                }
            } else {
                // Fresh pull: last 90 days.
                $filters['from'] = now()->subDays(90)->toDateString();
                $filters['to']   = now()->toDateString();
            }

            $sync = $orchestrator->run($cfg->agency_id, 'scheduled', null, $filters);

            $this->line(sprintf(
                '  agency=%d  listed=%d  imported=%d  skipped=%d  errored=%d  status=%s%s',
                $cfg->agency_id,
                $sync->files_listed,
                $sync->files_imported,
                $sync->files_skipped,
                $sync->files_errored,
                $sync->status,
                $sync->error ? "  err={$sync->error}" : '',
            ));

            $totalImported += $sync->files_imported;
            $totalSkipped  += $sync->files_skipped;
            $totalErrored  += $sync->files_errored;
        }

        $this->line('');
        $this->info(sprintf(
            'Total: imported=%d  skipped=%d  errored=%d',
            $totalImported, $totalSkipped, $totalErrored,
        ));

        return $totalErrored > 0 ? self::FAILURE : self::SUCCESS;
    }
}

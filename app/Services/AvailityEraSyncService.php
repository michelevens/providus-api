<?php

// Orchestrates a single Availity ERA sync run.
//
// One public entrypoint: ::run($agencyId, $source, $userId, $filters).
// Caller hands us the agency + a "manual" or "scheduled" tag; we
// list files from Availity, skip ones we've seen, download + import
// the new ones via Era835Importer, and write audit rows the entire
// way through. Returns the AvailityEraSync model with attached file
// rows so the caller can render a result summary.
//
// This is deliberately not in the controller — same logic runs from
// the HTTP endpoint (manual sync) AND the scheduled command (cron),
// and the two should never drift.
//
// Why not a queued job: ERA pulls for a single agency are minutes,
// not hours. A synchronous run keeps the operator looking at a "we
// pulled 3 files" toast within a reasonable hold time. If the run
// length grows (multi-tenant batch, big backfills), wrap this in a
// job — the contract here doesn't change.

namespace App\Services;

use App\Models\AgencyConfig;
use App\Models\AvailityEraSync;
use App\Models\AvailityEraSyncFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AvailityEraSyncService
{
    public function __construct(private AvailityService $availity)
    {
    }

    /**
     * Run one ERA sync for an agency.
     *
     * @param int $agencyId
     * @param string $source 'manual' or 'scheduled'
     * @param int|null $userId Operator id if manual; null if scheduled.
     * @param array $filters Optional: 'from' (ISO date), 'to' (ISO date),
     *                       'cursor' (ISO datetime — overrides from/to for nightly).
     */
    public function run(int $agencyId, string $source, ?int $userId, array $filters = []): AvailityEraSync
    {
        $sync = AvailityEraSync::create([
            'agency_id'    => $agencyId,
            'source'       => $source,
            'triggered_by' => $userId,
            'from_date'    => $filters['from'] ?? null,
            'to_date'      => $filters['to'] ?? null,
            'status'       => 'running',
        ]);

        try {
            $config = $this->loadAgencyConfig($agencyId);
            if (!$config) {
                $sync->update([
                    'status'       => 'failed',
                    'error'        => 'Agency config missing or Availity not configured.',
                    'completed_at' => now(),
                ]);
                return $sync->fresh('files');
            }

            // Build filter payload for Availity. Cursor (received_after)
            // takes precedence over from/to so nightly syncs are
            // incremental rather than re-fetching the whole window.
            $listFilters = [];
            if (!empty($filters['cursor'])) {
                $listFilters['received_after'] = $filters['cursor'];
            } else {
                if (!empty($filters['from'])) $listFilters['from'] = $filters['from'];
                if (!empty($filters['to']))   $listFilters['to']   = $filters['to'];
            }

            $list = $this->availity->listEraFiles($config, $listFilters);
            if (!($list['success'] ?? false)) {
                $sync->update([
                    'status'       => 'failed',
                    'error'        => $list['error'] ?? 'Availity list call failed.',
                    'completed_at' => now(),
                ]);
                return $sync->fresh('files');
            }

            $files = $list['files'] ?? [];
            $sync->update(['files_listed' => count($files)]);

            $newestReceivedAt = null;

            foreach ($files as $f) {
                $fileId = $f['file_id'] ?? null;
                if (!$fileId) continue;

                // Track newest received_at across the whole run so we
                // can advance the cursor at the end.
                if (!empty($f['received_at'])) {
                    try {
                        $ts = new \DateTimeImmutable($f['received_at']);
                        if (!$newestReceivedAt || $ts > $newestReceivedAt) {
                            $newestReceivedAt = $ts;
                        }
                    } catch (\Throwable $e) { /* ignore unparseable */ }
                }

                // Dedup by (agency_id, file_id). If we've imported it
                // before, log as skipped and move on. firstOrCreate
                // doesn't fit here because we want the create branch
                // to start in 'listed' state and the existing branch
                // to bypass everything.
                $existing = AvailityEraSyncFile::where('agency_id', $agencyId)
                    ->where('file_id', $fileId)
                    ->whereIn('status', ['imported', 'skipped'])
                    ->first();
                if ($existing) {
                    AvailityEraSyncFile::create([
                        'sync_id'     => $sync->id,
                        'agency_id'   => $agencyId,
                        'file_id'     => $fileId,
                        'received_at' => $f['received_at'] ?? null,
                        'payer_name'  => $f['payer'] ?? null,
                        'size_bytes'  => $f['size_bytes'] ?? null,
                        'claim_count' => $f['claim_count'] ?? null,
                        'status'      => 'skipped',
                    ]);
                    $sync->increment('files_skipped');
                    continue;
                }

                // Wait — we DO have a unique constraint on
                // (agency_id, file_id), so two parallel syncs would
                // collide. The dedup query above catches the common
                // case; the try/catch wrapping ::create handles the
                // race. (We'd see this only if a manual sync runs
                // while a cron sync is also in flight.)
                try {
                    $fileRow = AvailityEraSyncFile::create([
                        'sync_id'     => $sync->id,
                        'agency_id'   => $agencyId,
                        'file_id'     => $fileId,
                        'received_at' => $f['received_at'] ?? null,
                        'payer_name'  => $f['payer'] ?? null,
                        'size_bytes'  => $f['size_bytes'] ?? null,
                        'claim_count' => $f['claim_count'] ?? null,
                        'status'      => 'listed',
                    ]);
                } catch (\Throwable $e) {
                    // Unique-violation — another sync already inserted
                    // the file row. Skip this run's processing.
                    $sync->increment('files_skipped');
                    continue;
                }

                $this->processFile($fileRow, $config, $sync);
            }

            $sync->update([
                'cursor_at'    => $newestReceivedAt,
                'status'       => 'completed',
                'completed_at' => now(),
            ]);

            return $sync->fresh('files');
        } catch (\Throwable $e) {
            Log::error('Availity ERA sync run blew up', [
                'agency_id' => $agencyId,
                'sync_id'   => $sync->id,
                'error'     => $e->getMessage(),
            ]);
            $sync->update([
                'status'       => 'failed',
                'error'        => $e->getMessage(),
                'completed_at' => now(),
            ]);
            return $sync->fresh('files');
        }
    }

    /**
     * Download + import one file. Updates the per-file row and the
     * parent sync's counters. Wrapped in its own try so one bad file
     * doesn't poison the whole run.
     */
    private function processFile(AvailityEraSyncFile $fileRow, array $config, AvailityEraSync $sync): void
    {
        try {
            $download = $this->availity->downloadEraFile($config, $fileRow->file_id);
            if (!($download['success'] ?? false)) {
                $fileRow->update([
                    'status' => 'error',
                    'error'  => $download['error'] ?? 'Download failed',
                ]);
                $sync->increment('files_errored');
                return;
            }

            $content = $download['content'] ?? '';
            if (!$content) {
                $fileRow->update([
                    'status' => 'error',
                    'error'  => 'Availity returned empty file content.',
                ]);
                $sync->increment('files_errored');
                return;
            }

            // Run through the existing 835 importer. It dedupes on
            // TRN02 internally, so even if Availity hands us the same
            // file across two different file_ids, we won't double-post.
            $importer = new Era835Importer(
                $content,
                $sync->agency_id,
                $sync->triggered_by,
                null, // billing_client_id resolved per-claim inside the importer
            );
            $result = $importer->run();

            $hadErrors = !empty($result['errors']);
            $fileRow->update([
                'status'        => $hadErrors ? 'error' : 'imported',
                'import_result' => $result,
                'error'         => $hadErrors ? implode('; ', $result['errors']) : null,
            ]);

            if ($hadErrors) {
                $sync->increment('files_errored');
            } else {
                $sync->increment('files_imported');
                if (!empty($result['posted'])) {
                    $sync->increment('claims_posted', (int) $result['posted']);
                }
                if (!empty($result['total_amount'])) {
                    $sync->total_amount_posted = (float) $sync->total_amount_posted + (float) $result['total_amount'];
                    $sync->save();
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Availity ERA file import failed', [
                'agency_id' => $sync->agency_id,
                'file_id'   => $fileRow->file_id,
                'error'     => $e->getMessage(),
            ]);
            $fileRow->update([
                'status' => 'error',
                'error'  => $e->getMessage(),
            ]);
            $sync->increment('files_errored');
        }
    }

    /**
     * Resolve agency Availity config into the array shape
     * AvailityService methods expect. Returns null if creds aren't
     * configured — caller short-circuits in that case.
     */
    private function loadAgencyConfig(int $agencyId): ?array
    {
        $cfg = AgencyConfig::where('agency_id', $agencyId)->first();
        if (!$cfg || !$cfg->availity_client_id || !$cfg->availity_client_secret) {
            return null;
        }
        return [
            'agency_id'              => $agencyId,
            'availity_client_id'     => $cfg->availity_client_id,
            // Eloquent decrypts the cast on read; service expects
            // plaintext.
            'availity_client_secret' => $cfg->availity_client_secret,
            'availity_customer_id'   => $cfg->availity_customer_id,
            'availity_env'           => $cfg->availity_env ?: 'production',
        ];
    }

    /**
     * Return the cursor (newest received_at) from the agency's most
     * recent successful sync. Scheduled command uses this as the
     * received_after filter for the next run.
     */
    public function cursorForAgency(int $agencyId): ?string
    {
        $last = AvailityEraSync::where('agency_id', $agencyId)
            ->where('status', 'completed')
            ->whereNotNull('cursor_at')
            ->orderByDesc('cursor_at')
            ->first();
        return $last?->cursor_at?->toIso8601String();
    }
}

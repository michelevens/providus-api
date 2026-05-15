<?php

// Audit log of every Availity ERA sync attempt — one row per
// invocation (cron or manual), plus per-file rows for visibility.
//
// Two tables to keep concerns separated:
//
//   availity_era_syncs       — the parent run. One row per
//                              "POST /rcm/era/sync-availity" call or
//                              one scheduled-command tick. Records
//                              who triggered it, the date window, the
//                              outcome counts (files seen / downloaded /
//                              imported / skipped), and any top-level
//                              error message.
//
//   availity_era_sync_files  — the children. One row per file Availity
//                              listed, regardless of whether we
//                              ultimately downloaded it. The status
//                              column tracks the per-file lifecycle:
//                              'listed' → 'downloaded' → 'imported'
//                              (or 'skipped'/'error'). The Availity
//                              file_id is unique per agency so we
//                              never double-process the same file
//                              across runs.
//
// Why store per-file rows: when a nightly sync re-discovers a file
// Availity already listed yesterday, the unique (agency_id, file_id)
// constraint lets the sync command skip-already-imported in O(1).
// Without this table, every nightly run would re-download every file
// in the agency's Availity history.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availity_era_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            // 'manual' (user clicked Sync) or 'scheduled' (cron).
            $table->string('source', 20);
            // Operator id when source='manual'; null when scheduled.
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            // Date window we asked Availity for. Nullable because a
            // cursor-style sync (received_after) supersedes a
            // from/to window and we want both to be representable.
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            // Cursor — the receivedDate of the newest file Availity
            // returned on this run. Becomes the input to the NEXT
            // run's received_after filter.
            $table->timestampTz('cursor_at')->nullable();
            // Outcome counts.
            $table->unsignedInteger('files_listed')->default(0);
            $table->unsignedInteger('files_imported')->default(0);
            $table->unsignedInteger('files_skipped')->default(0);
            $table->unsignedInteger('files_errored')->default(0);
            $table->unsignedInteger('claims_posted')->default(0);
            $table->decimal('total_amount_posted', 12, 2)->default(0);
            // Top-level error (auth failure, network blowup). Per-file
            // errors live on availity_era_sync_files instead.
            $table->text('error')->nullable();
            $table->string('status', 20)->default('running'); // running | completed | failed
            $table->timestampTz('completed_at')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'created_at'], 'avail_era_syncs_agency_idx');
            $table->index('status', 'avail_era_syncs_status_idx');
        });

        Schema::create('availity_era_sync_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sync_id')->constrained('availity_era_syncs')->cascadeOnDelete();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            // Availity's file ID. Unique per agency — re-running the
            // sync sees the same id and skips re-import.
            $table->string('file_id', 100);
            $table->timestampTz('received_at')->nullable();
            $table->string('payer_name')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('claim_count')->nullable();
            // Lifecycle:
            //   listed     — Availity reported it; we haven't fetched yet
            //   downloaded — pulled the 835 bytes (rarely a separate
            //                state in practice; sync downloads then
            //                imports atomically)
            //   imported   — Era835Importer ran successfully
            //   skipped    — already imported by a prior run (dedup hit)
            //   error      — download or import failed; see error col
            $table->string('status', 20)->default('listed');
            // Snapshot of the importer result for this file: imported,
            // posted, matched, unmatched, denials_created, total_amount,
            // trace_number, check_number. Stored verbatim so we can
            // audit later without re-running.
            $table->jsonb('import_result')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            // Prevents re-importing the same Availity file twice.
            $table->unique(['agency_id', 'file_id'], 'avail_era_files_unique');
            $table->index(['sync_id', 'status'], 'avail_era_files_sync_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availity_era_sync_files');
        Schema::dropIfExists('availity_era_syncs');
    }
};

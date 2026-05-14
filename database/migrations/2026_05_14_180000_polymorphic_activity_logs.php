<?php

// Make activity_logs polymorphic.
//
// Today every row links to ONE entity type — applications — via the
// application_id column. We want the same timeline + add-note pattern
// for claims and providers without duplicating the table.
//
// Approach:
//   1. Add subject_type (string) + subject_id (bigint) columns.
//   2. Backfill from application_id so existing rows keep working.
//   3. Index (subject_type, subject_id, created_at DESC) — the only
//      shape the timeline reads.
//
// application_id stays as a nullable column for now. The controller
// keeps a shim for ?application_id=X so V1 callers don't break. A
// follow-up migration can drop it once we're confident nothing reads
// it directly (which would be quick — a single grep on the codebase).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            // Subject pair — names match Laravel's morph convention
            // (subject_type/subject_id) so any future use of MorphTo
            // works out of the box.
            $table->string('subject_type', 32)->nullable()->after('agency_id');
            $table->unsignedBigInteger('subject_id')->nullable()->after('subject_type');
            $table->index(['subject_type', 'subject_id', 'created_at'], 'activity_logs_subject_idx');
        });

        // Backfill: every existing row is an application activity log.
        DB::table('activity_logs')
            ->whereNotNull('application_id')
            ->update([
                'subject_type' => 'application',
                'subject_id' => DB::raw('application_id'),
            ]);
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex('activity_logs_subject_idx');
            $table->dropColumn(['subject_type', 'subject_id']);
        });
    }
};

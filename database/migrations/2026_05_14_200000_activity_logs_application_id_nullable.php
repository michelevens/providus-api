<?php

// activity_logs.application_id is NOT NULL from the original schema.
// That worked when every row WAS an application activity log.
//
// Now that subject_type/subject_id exists, a claim or provider log
// row has no meaningful application_id — the polymorphic migration
// missed this and writes were failing on prod with:
//   "null value in column \"application_id\" ... violates not-null"
//
// Make it nullable. application_id stays as a column so V1 reporting
// that groups on it keeps working for actual application rows; we
// just allow nulls for the new subject types.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('application_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Re-tightening would require backfilling application_id for
        // every claim/provider row, which doesn't make semantic sense.
        // Intentional no-op; rolling back is a code-only decision.
    }
};

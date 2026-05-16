<?php

// Adds collections-handoff fields to patient_statements.
//
// When the agency exhausts soft/firm/final reminders and the patient
// still hasn't paid, the operator hands off to an external collections
// agency. After handoff:
//   - The statement disappears from the active Balance Reminders queue
//     (filter is `status NOT IN ('paid', 'written_off', 'in_collections')`).
//   - A/R aging reports can exclude or separately bucket these so the
//     dashboard doesn't mix "we're still chasing this" with "we gave up."
//   - The handoff timestamp lets us measure days-to-collections-handoff
//     as a workflow metric.
//
// status: existing enum-ish varchar. We add 'in_collections' as a valid
// terminal value alongside 'paid' / 'written_off'. No schema change for
// the column (already varchar(50)+), just a documented additional value.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_statements', function (Blueprint $table) {
            // When the operator hit "Handoff to collections" — terminal stamp.
            $table->timestampTz('handed_off_to_collections_at')->nullable()->after('last_sent_date');
            // Who hit the button (audit + accountability).
            $table->foreignId('handed_off_to_collections_by')->nullable()->after('handed_off_to_collections_at')->constrained('users')->nullOnDelete();
            // Free-text reason / collections-agency name / reference. Optional.
            $table->text('handoff_notes')->nullable()->after('handed_off_to_collections_by');
            // Index status so the Balance Reminders filter is fast — without
            // it the new in_collections filter scans the whole table.
            $table->index('status', 'patient_statements_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('patient_statements', function (Blueprint $table) {
            $table->dropIndex('patient_statements_status_idx');
            $table->dropForeign(['handed_off_to_collections_by']);
            $table->dropColumn(['handed_off_to_collections_at', 'handed_off_to_collections_by', 'handoff_notes']);
        });
    }
};

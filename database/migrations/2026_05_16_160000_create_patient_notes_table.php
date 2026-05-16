<?php

// Patient-scoped operator notes.
//
// V2's PatientDetailPage uses patient_name as the identity (no real
// patients table — claims own the demographic fields). We follow the
// same pattern here: notes are keyed by `patient_key` (lowercased,
// trimmed patient_name) so the same operator can see the same notes
// on every claim/statement for that patient regardless of which
// imported record introduced them.
//
// Scope: per agency. Two different agencies billing for the same
// patient name see different notes (no cross-tenant leak via TenantScope).
//
// `pinned` is a UI flag — pinned notes float to the top. Lets
// operators surface persistent context ("hard of hearing, email
// only") above day-to-day call notes.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            // Lowercased, trimmed patient_name. Same key V2's
            // PatientDetailPage uses for identity matching.
            $table->string('patient_key', 200)->index();
            $table->text('body');
            $table->boolean('pinned')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Composite index — most queries are "this agency's notes
            // for this patient, ordered by pinned desc + created desc."
            $table->index(['agency_id', 'patient_key'], 'patient_notes_agency_patient_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_notes');
    }
};

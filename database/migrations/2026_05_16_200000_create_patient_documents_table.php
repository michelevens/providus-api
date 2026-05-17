<?php

// Patient-scoped document storage. Mirrors provider_documents but keyed
// by patient_key (lowercased + trimmed patient_name) because V2 doesn't
// model patients as first-class rows — claims own the demographic
// fields. Same key convention used by patient_notes / patient_statements
// so the same operator sees the same files across every claim for that
// patient regardless of which imported record introduced them.
//
// Typical types: insurance_card_front, insurance_card_back, intake_form,
// id_photo, signed_consent, prior_auth_doc, eob, eob_paper, statement_copy.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            // Lowercased, trimmed patient_name. Matches patient_notes
            // and the identity convention V2 uses on PatientDetailPage.
            $table->string('patient_key', 200)->index();
            $table->string('document_type', 100);   // e.g. insurance_card_front
            $table->string('document_name', 200);   // operator-supplied label
            $table->string('file_path', 500)->nullable();
            $table->string('file_disk', 20)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->integer('file_size')->nullable();
            $table->string('original_filename', 500)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('received'); // pending, received, verified, expired
            $table->date('received_date')->nullable();
            $table->date('expiration_date')->nullable();       // for insurance cards / prior auths
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'patient_key'], 'patient_documents_lookup');
            $table->index(['agency_id', 'patient_key', 'document_type'], 'patient_documents_type_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_documents');
    }
};

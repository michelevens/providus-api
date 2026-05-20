<?php

// Organization-scoped document storage. Mirrors patient_documents
// and provider_documents shape — same R2 backend, same lifecycle
// fields, same status vocabulary.
//
// Typical types: w9, voided_check, certificate_of_insurance,
// articles_of_incorporation, business_license, hipaa_baa,
// caqh_attestation, signed_contract, doc_request_response.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('document_type', 100);
            $table->string('document_name', 200);
            $table->string('file_path', 500)->nullable();
            $table->string('file_disk', 20)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->integer('file_size')->nullable();
            $table->string('original_filename', 500)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('received'); // pending, received, verified, expired
            $table->date('received_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->text('notes')->nullable();
            // Linkback to a document_request that produced this upload,
            // so the agency can see "this file came in via request #N
            // sent on date X to email Y". Nullable — direct uploads
            // (agency staff dropping in a W-9 themselves) leave it NULL.
            $table->foreignId('document_request_id')->nullable()->constrained('document_requests')->nullOnDelete();
            $table->timestamps();

            $table->index(['agency_id', 'organization_id'], 'org_documents_lookup');
            $table->index(['agency_id', 'organization_id', 'document_type'], 'org_documents_type_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_documents');
    }
};

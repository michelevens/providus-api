<?php

// Link uploads on provider_documents back to the document_requests
// row that asked for them, so the agency can see "this file came in
// via request #N." Mirrors the column added to organization_documents
// in the same batch.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_documents', function (Blueprint $table) {
            $table->foreignId('document_request_id')->nullable()->after('uploaded_by')
                ->constrained('document_requests')->nullOnDelete();
            $table->index('document_request_id', 'provider_documents_request_idx');
        });
    }

    public function down(): void
    {
        Schema::table('provider_documents', function (Blueprint $table) {
            $table->dropForeign(['document_request_id']);
            $table->dropIndex('provider_documents_request_idx');
            $table->dropColumn('document_request_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('provider_documents', function (Blueprint $table) {
            $table->string('file_path', 500)->nullable()->after('file_url');
            $table->string('file_disk', 20)->default('s3')->after('file_path');
            $table->string('mime_type', 100)->nullable()->after('file_disk');
            $table->unsignedBigInteger('file_size')->nullable()->after('mime_type');
            $table->string('original_filename', 300)->nullable()->after('file_size');
            $table->string('uploaded_by')->nullable()->after('original_filename');
        });
    }

    public function down(): void
    {
        Schema::table('provider_documents', function (Blueprint $table) {
            $table->dropColumn(['file_path', 'file_disk', 'mime_type', 'file_size', 'original_filename', 'uploaded_by']);
        });
    }
};

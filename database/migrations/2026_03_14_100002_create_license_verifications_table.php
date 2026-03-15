<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('state', 2);
            $table->string('license_number');
            $table->string('verification_source'); // state_board, nppes, manual
            $table->string('status', 20)->default('pending'); // pending, verified, mismatch, error, expired
            $table->timestamp('verified_at')->nullable();
            $table->jsonb('source_data')->nullable(); // raw API response
            $table->string('source_name')->nullable(); // name from source
            $table->string('source_status')->nullable(); // active/inactive from source
            $table->date('source_expiration')->nullable();
            $table->text('discrepancies')->nullable();
            $table->string('pdf_url')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['agency_id', 'license_id']);
            $table->index(['agency_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_verifications');
    }
};

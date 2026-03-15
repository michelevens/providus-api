<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_work_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('employer_name');
            $table->string('position_title')->nullable();
            $table->string('department')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_current')->default(false);
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('supervisor_name')->nullable();
            $table->string('supervisor_phone')->nullable();
            $table->string('reason_for_leaving')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'provider_id']);
        });

        // CME / Continuing Education
        Schema::create('provider_cme', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('course_name');
            $table->string('provider_org')->nullable(); // CME provider org
            $table->decimal('credit_hours', 6, 2)->nullable();
            $table->string('credit_type')->nullable(); // category_1, category_2, ce, other
            $table->date('completion_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->string('certificate_number')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'provider_id']);
        });

        // Provider references
        Schema::create('provider_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('reference_name');
            $table->string('reference_title')->nullable();
            $table->string('reference_organization')->nullable();
            $table->string('relationship')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('status', 20)->default('pending'); // pending, contacted, received, verified
            $table->date('contacted_at')->nullable();
            $table->date('received_at')->nullable();
            $table->text('response_notes')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'provider_id']);
        });

        // Provider documents tracking
        Schema::create('provider_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('document_type'); // cv, diploma, license_copy, dea, cds, w9, photo_id, etc.
            $table->string('document_name');
            $table->string('file_url')->nullable();
            $table->string('status', 20)->default('pending'); // pending, received, verified, expired, missing
            $table->date('received_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->integer('request_attempts')->default(0);
            $table->date('last_requested_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'provider_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_documents');
        Schema::dropIfExists('provider_references');
        Schema::dropIfExists('provider_cme');
        Schema::dropIfExists('provider_work_history');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_education', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('institution_name');
            $table->string('degree')->nullable(); // MD, DO, PhD, MSN, etc.
            $table->string('field_of_study')->nullable();
            $table->string('education_type')->nullable(); // medical_school, residency, fellowship, graduate, undergraduate
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('graduation_date')->nullable();
            $table->boolean('is_completed')->default(true);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->string('verification_source')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'provider_id']);
        });

        Schema::create('board_certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('board_name');
            $table->string('specialty');
            $table->string('certificate_number')->nullable();
            $table->date('initial_certification_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->date('recertification_date')->nullable();
            $table->string('status', 20)->default('active'); // active, expired, revoked, pending
            $table->boolean('is_lifetime')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'provider_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_certifications');
        Schema::dropIfExists('provider_education');
    }
};

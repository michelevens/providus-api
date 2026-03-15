<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('malpractice_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('carrier_name');
            $table->string('policy_number')->nullable();
            $table->string('coverage_type')->nullable(); // occurrence, claims_made
            $table->decimal('per_incident_amount', 12, 2)->nullable();
            $table->decimal('aggregate_amount', 12, 2)->nullable();
            $table->date('effective_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->string('status', 20)->default('active'); // active, expired, cancelled, pending
            $table->boolean('has_tail_coverage')->default(false);
            $table->boolean('has_claims_history')->default(false);
            $table->integer('claims_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'provider_id']);
            $table->index(['agency_id', 'expiration_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('malpractice_policies');
    }
};

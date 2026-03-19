<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funding_opportunities', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->nullable()->index();
            $table->string('source')->index(); // grants_gov, sam_gov, usaspending, nih, samhsa, hrsa, foundation
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('agency_source')->nullable(); // SAMHSA, HRSA, NIH, DOJ, etc.
            $table->string('cfda_number')->nullable();
            $table->string('funding_type')->nullable(); // grant, contract, cooperative_agreement
            $table->decimal('amount_min', 15, 2)->nullable();
            $table->decimal('amount_max', 15, 2)->nullable();
            $table->string('amount_display')->nullable(); // "$500K–$1M"
            $table->date('open_date')->nullable();
            $table->date('close_date')->nullable()->index();
            $table->string('status')->default('open')->index(); // open, closed, forecasted, archived
            $table->string('eligibility')->nullable();
            $table->string('url')->nullable();
            $table->string('category')->nullable(); // mental_health, substance_use, workforce, etc.
            $table->json('keywords')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('scraped_at')->nullable();
            $table->timestamps();

            $table->unique(['source', 'external_id']);
        });

        Schema::create('funding_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('funding_opportunity_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('stage')->default('identified'); // identified, preparing, submitted, under_review, awarded, denied
            $table->decimal('amount_requested', 15, 2)->nullable();
            $table->decimal('amount_awarded', 15, 2)->nullable();
            $table->date('deadline')->nullable();
            $table->date('submitted_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('agency_id');
            $table->index('stage');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funding_applications');
        Schema::dropIfExists('funding_opportunities');
    }
};

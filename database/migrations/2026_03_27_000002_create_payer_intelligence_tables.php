<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Payer Rules / Intelligence ──
        Schema::create('payer_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agency_id');
            $table->string('payer_name', 100);
            $table->integer('timely_filing_days')->nullable(); // days from DOS to submit
            $table->integer('appeal_filing_days')->nullable(); // days to file appeal
            $table->integer('corrected_claim_days')->nullable(); // days to submit corrected claim
            $table->string('portal_url', 300)->nullable();
            $table->string('provider_phone', 30)->nullable();
            $table->string('claims_address', 300)->nullable();
            $table->string('appeals_address', 300)->nullable();
            $table->string('appeals_fax', 30)->nullable();
            $table->string('electronic_payer_id', 20)->nullable(); // for clearinghouse
            $table->json('auth_required_cpts')->nullable(); // CPT codes that need prior auth
            $table->json('bundling_rules')->nullable(); // e.g. [{"primary":"90837","cannot_bill_with":"90834"}]
            $table->json('medical_necessity_notes')->nullable(); // per-CPT documentation requirements
            $table->json('common_denial_reasons')->nullable(); // top reasons and how to avoid
            $table->json('credentialing_requirements')->nullable(); // what's needed to get credentialed
            $table->text('reimbursement_notes')->nullable(); // general notes about this payer
            $table->text('billing_tips')->nullable(); // agency-specific tips
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['agency_id', 'payer_name']);
        });

        // ── Provider Feedback (coding/documentation issues from denials) ──
        Schema::create('provider_feedback', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agency_id');
            $table->unsignedBigInteger('provider_id')->nullable();
            $table->string('provider_name', 100);
            $table->unsignedBigInteger('claim_id')->nullable();
            $table->unsignedBigInteger('denial_id')->nullable();
            $table->string('feedback_type', 30); // coding_error, documentation, authorization, modifier, medical_necessity
            $table->string('cpt_code', 10)->nullable();
            $table->string('payer_name', 100)->nullable();
            $table->text('issue'); // what went wrong
            $table->text('recommendation'); // what to do differently
            $table->string('status', 20)->default('pending'); // pending, sent, acknowledged, resolved
            $table->date('sent_date')->nullable();
            $table->text('provider_response')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['agency_id', 'provider_name']);
            $table->index(['agency_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_feedback');
        Schema::dropIfExists('payer_rules');
    }
};

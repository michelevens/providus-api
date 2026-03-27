<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Fee Schedules ──
        Schema::create('fee_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agency_id');
            $table->unsignedBigInteger('billing_client_id')->nullable();
            $table->string('payer_name', 100);
            $table->string('cpt_code', 10);
            $table->string('cpt_description', 200)->nullable();
            $table->string('modifier', 10)->nullable();
            $table->decimal('contracted_rate', 10, 2)->default(0);
            $table->decimal('expected_allowed', 10, 2)->nullable();
            $table->date('effective_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->string('plan_type', 50)->nullable(); // commercial, medicare, medicaid
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['agency_id', 'payer_name', 'cpt_code']);
        });

        // ── Payer Follow-Up Logs ──
        Schema::create('payer_followups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agency_id');
            $table->unsignedBigInteger('claim_id');
            $table->string('contact_method', 30)->default('phone'); // phone, portal, fax, email, mail
            $table->string('payer_name', 100)->nullable();
            $table->string('payer_rep', 100)->nullable(); // name of payer representative
            $table->string('reference_number', 50)->nullable(); // call reference / ticket #
            $table->string('outcome', 30)->default('pending'); // pending, resolved, escalated, denied, resubmit, no_answer
            $table->text('notes')->nullable();
            $table->date('followup_date')->nullable(); // when to follow up next
            $table->boolean('followup_completed')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['agency_id', 'claim_id']);
            $table->index(['agency_id', 'followup_date']);
        });

        // ── Appeal Templates ──
        Schema::create('appeal_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agency_id');
            $table->string('name', 100);
            $table->string('denial_category', 30)->nullable(); // matches denial categories
            $table->string('template_type', 30)->default('letter'); // letter, form, checklist
            $table->text('subject')->nullable();
            $table->text('body'); // template body with {{placeholders}}
            $table->json('required_attachments')->nullable(); // e.g. ["medical_records","auth_letter"]
            $table->boolean('is_default')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['agency_id', 'denial_category']);
        });

        // ── Patient Statements ──
        Schema::create('patient_statements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agency_id');
            $table->unsignedBigInteger('billing_client_id')->nullable();
            $table->unsignedBigInteger('claim_id')->nullable();
            $table->string('patient_name', 100);
            $table->string('patient_email', 150)->nullable();
            $table->string('patient_phone', 30)->nullable();
            $table->string('patient_address', 300)->nullable();
            $table->decimal('total_charges', 10, 2)->default(0);
            $table->decimal('insurance_paid', 10, 2)->default(0);
            $table->decimal('adjustments', 10, 2)->default(0);
            $table->decimal('patient_balance', 10, 2)->default(0);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->string('status', 20)->default('draft'); // draft, sent, partial_paid, paid, collections, written_off
            $table->date('statement_date')->nullable();
            $table->date('due_date')->nullable();
            $table->date('last_sent_date')->nullable();
            $table->integer('times_sent')->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['agency_id', 'status']);
        });

        // ── Eligibility Checks (may already exist from earlier migration) ──
        if (!Schema::hasTable('eligibility_checks')) Schema::create('eligibility_checks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agency_id');
            $table->unsignedBigInteger('billing_client_id')->nullable();
            $table->string('patient_name', 100);
            $table->date('patient_dob')->nullable();
            $table->string('member_id', 50)->nullable();
            $table->string('payer_name', 100);
            $table->string('payer_id', 20)->nullable(); // electronic payer ID
            $table->string('provider_npi', 10)->nullable();
            $table->string('status', 20)->default('pending'); // pending, active, inactive, error
            $table->boolean('is_active')->nullable();
            $table->date('coverage_start')->nullable();
            $table->date('coverage_end')->nullable();
            $table->string('plan_name', 150)->nullable();
            $table->string('plan_type', 50)->nullable();
            $table->string('group_number', 50)->nullable();
            $table->decimal('copay', 10, 2)->nullable();
            $table->decimal('deductible', 10, 2)->nullable();
            $table->decimal('deductible_met', 10, 2)->nullable();
            $table->decimal('out_of_pocket_max', 10, 2)->nullable();
            $table->decimal('oop_met', 10, 2)->nullable();
            $table->json('raw_response')->nullable(); // full API response
            $table->string('error_message', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['agency_id', 'patient_name']);
        });

        // ── Underpayment Flags ──
        Schema::create('underpayment_flags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agency_id');
            $table->unsignedBigInteger('claim_id');
            $table->string('cpt_code', 10)->nullable();
            $table->decimal('expected_amount', 10, 2);
            $table->decimal('paid_amount', 10, 2);
            $table->decimal('variance', 10, 2); // expected - paid
            $table->string('status', 20)->default('flagged'); // flagged, reviewed, appealed, resolved, accepted
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['agency_id', 'status']);
        });

        // ── Client Reports ──
        Schema::create('client_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agency_id');
            $table->unsignedBigInteger('billing_client_id');
            $table->string('report_type', 30)->default('monthly'); // monthly, quarterly, annual, custom
            $table->string('period', 10); // e.g. 2026-03
            $table->integer('total_claims')->default(0);
            $table->integer('claims_submitted')->default(0);
            $table->integer('claims_paid')->default(0);
            $table->integer('claims_denied')->default(0);
            $table->decimal('total_charged', 12, 2)->default(0);
            $table->decimal('total_collected', 12, 2)->default(0);
            $table->decimal('total_denied_amount', 12, 2)->default(0);
            $table->decimal('total_adjustments', 12, 2)->default(0);
            $table->decimal('patient_responsibility', 12, 2)->default(0);
            $table->decimal('collection_rate', 5, 1)->default(0);
            $table->decimal('clean_claim_rate', 5, 1)->default(0);
            $table->decimal('denial_rate', 5, 1)->default(0);
            $table->integer('avg_days_to_pay')->default(0);
            $table->json('by_payer')->nullable(); // breakdown by payer
            $table->json('denial_breakdown')->nullable(); // breakdown by denial category
            $table->string('status', 20)->default('draft'); // draft, sent, archived
            $table->date('sent_date')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['agency_id', 'billing_client_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_reports');
        Schema::dropIfExists('underpayment_flags');
        Schema::dropIfExists('eligibility_checks');
        Schema::dropIfExists('patient_statements');
        Schema::dropIfExists('appeal_templates');
        Schema::dropIfExists('payer_followups');
        Schema::dropIfExists('fee_schedules');
    }
};

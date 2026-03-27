<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Claims — track claims submitted via external platforms
        Schema::create('claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('claim_number', 30);
            $table->string('claim_type', 10)->default('837P'); // 837P, 837I, 837D
            $table->string('status', 20)->default('draft'); // draft, submitted, acknowledged, pending, paid, partial_paid, denied, rejected, appealed, voided, written_off
            $table->foreignId('provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider_name')->nullable();
            $table->string('patient_name')->nullable();
            $table->string('patient_dob')->nullable();
            $table->string('patient_member_id')->nullable();
            $table->string('payer_name')->nullable();
            $table->string('payer_id_number')->nullable(); // payer's claim ID
            $table->date('date_of_service');
            $table->date('date_of_service_end')->nullable();
            $table->string('place_of_service', 5)->nullable();
            $table->string('facility_name')->nullable();
            $table->string('referring_provider')->nullable();
            $table->string('authorization_number')->nullable();
            $table->decimal('total_charges', 12, 2)->default(0);
            $table->decimal('total_allowed', 12, 2)->nullable();
            $table->decimal('total_paid', 12, 2)->default(0);
            $table->decimal('patient_responsibility', 12, 2)->default(0);
            $table->decimal('adjustments', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->string('submission_method', 20)->nullable(); // electronic, paper, portal
            $table->string('clearinghouse')->nullable();
            $table->date('submitted_date')->nullable();
            $table->date('acknowledged_date')->nullable();
            $table->date('adjudicated_date')->nullable();
            $table->date('paid_date')->nullable();
            $table->string('check_number')->nullable();
            $table->string('denial_reason')->nullable();
            $table->string('denial_codes')->nullable();
            $table->date('appeal_deadline')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['agency_id', 'status']);
            $table->index(['billing_client_id', 'status']);
            $table->index(['agency_id', 'date_of_service']);
        });

        // Claim service lines
        Schema::create('claim_service_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->constrained()->cascadeOnDelete();
            $table->integer('line_number')->default(1);
            $table->string('cpt_code', 10);
            $table->string('cpt_description')->nullable();
            $table->string('modifiers', 20)->nullable(); // comma-separated
            $table->string('icd_codes')->nullable(); // comma-separated diagnosis pointers
            $table->decimal('units', 8, 2)->default(1);
            $table->decimal('charges', 10, 2)->default(0);
            $table->decimal('allowed_amount', 10, 2)->nullable();
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('adjustment', 10, 2)->default(0);
            $table->decimal('patient_resp', 10, 2)->default(0);
            $table->string('status', 20)->default('pending'); // pending, paid, denied, adjusted
            $table->string('denial_reason')->nullable();
            $table->timestamps();
        });

        // Denials — track and manage denied claims
        Schema::create('claim_denials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('claim_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('denial_category', 30); // eligibility, authorization, coding, medical_necessity, timely_filing, duplicate, bundling, coordination_of_benefits, credentialing, documentation, other
            $table->string('denial_code')->nullable();
            $table->string('denial_reason');
            $table->decimal('denied_amount', 12, 2)->default(0);
            $table->string('status', 20)->default('new'); // new, in_review, appeal_in_progress, pending_response, resolved_won, resolved_lost, resolved_partial, written_off
            $table->string('priority', 10)->default('normal'); // low, normal, high, urgent
            $table->date('denial_date')->nullable();
            $table->date('appeal_deadline')->nullable();
            $table->integer('appeal_level')->default(0); // 0=no appeal, 1=first, 2=second, etc
            $table->date('appeal_submitted_date')->nullable();
            $table->decimal('recovered_amount', 12, 2)->default(0);
            $table->text('appeal_notes')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['agency_id', 'status']);
            $table->index(['agency_id', 'denial_category']);
            $table->index(['billing_client_id', 'status']);
        });

        // Payments — record payments received against claims
        Schema::create('claim_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payer_name')->nullable();
            $table->string('payment_type', 20)->default('check'); // check, eft, virtual_card, patient, ach
            $table->string('check_number')->nullable();
            $table->string('trace_number')->nullable();
            $table->date('payment_date');
            $table->date('deposit_date')->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('posted_amount', 12, 2)->default(0);
            $table->decimal('remaining_amount', 12, 2)->default(0);
            $table->string('status', 20)->default('unposted'); // unposted, partial, posted, reconciled
            $table->text('notes')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['agency_id', 'status']);
            $table->index(['billing_client_id', 'payment_date']);
        });

        // Payment allocations — link payments to specific claims
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('claim_id')->constrained()->cascadeOnDelete();
            $table->integer('service_line_number')->nullable();
            $table->decimal('charged_amount', 10, 2)->default(0);
            $table->decimal('allowed_amount', 10, 2)->default(0);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('adjustment_amount', 10, 2)->default(0);
            $table->decimal('patient_responsibility', 10, 2)->default(0);
            $table->decimal('copay', 10, 2)->default(0);
            $table->decimal('coinsurance', 10, 2)->default(0);
            $table->decimal('deductible', 10, 2)->default(0);
            $table->string('adjustment_codes')->nullable(); // JSON or comma-separated
            $table->string('remark_codes')->nullable();
            $table->timestamps();
        });

        // Charge capture — CPT/ICD entries before claim creation
        Schema::create('charge_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider_name')->nullable();
            $table->string('patient_name')->nullable();
            $table->string('payer_name')->nullable();
            $table->date('date_of_service');
            $table->string('cpt_code', 10);
            $table->string('cpt_description')->nullable();
            $table->string('modifiers', 20)->nullable();
            $table->string('icd_codes')->nullable(); // comma-separated
            $table->string('icd_descriptions')->nullable();
            $table->decimal('units', 8, 2)->default(1);
            $table->decimal('charge_amount', 10, 2)->default(0);
            $table->decimal('allowed_amount', 10, 2)->nullable();
            $table->string('place_of_service', 5)->nullable();
            $table->string('facility_name')->nullable();
            $table->string('authorization_number')->nullable();
            $table->string('status', 20)->default('pending'); // pending, reviewed, submitted, billed
            $table->foreignId('claim_id')->nullable()->constrained()->nullOnDelete(); // linked after claim created
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['agency_id', 'status']);
            $table->index(['billing_client_id', 'date_of_service']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charge_entries');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('claim_payments');
        Schema::dropIfExists('claim_denials');
        Schema::dropIfExists('claim_service_lines');
        Schema::dropIfExists('claims');
    }
};

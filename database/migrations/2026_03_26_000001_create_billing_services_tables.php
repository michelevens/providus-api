<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Billing clients — orgs that the agency manages billing for
        Schema::create('billing_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('organization_name');
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('billing_platform', 50)->nullable(); // Office Ally, Availity, etc.
            $table->decimal('monthly_fee', 10, 2)->default(0);
            $table->string('fee_structure', 20)->default('flat'); // flat, per_provider, percentage, per_claim
            $table->string('status', 20)->default('onboarding'); // onboarding, active, paused, cancelled
            $table->date('start_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['agency_id', 'status']);
        });

        // Billing tasks — charge entry, claim follow-up, denial mgmt, etc.
        Schema::create('billing_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_client_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('provider_name')->nullable();
            $table->string('category', 30)->default('other'); // charge_entry, claim_submission, claim_followup, denial_management, payment_posting, eligibility_verification, patient_billing, reporting, other
            $table->string('priority', 10)->default('normal'); // low, normal, high, urgent
            $table->string('status', 20)->default('pending'); // pending, in_progress, completed, on_hold, cancelled
            $table->date('due_date')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['agency_id', 'status']);
            $table->index(['billing_client_id', 'status']);
        });

        // Billing activities — log of work done (claims submitted, denials worked, payments posted)
        Schema::create('billing_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_client_id')->constrained()->cascadeOnDelete();
            $table->string('activity_type', 30); // claim_submitted, claim_followup, denial_worked, payment_posted, eligibility_check, report_generated, note
            $table->string('provider_name')->nullable();
            $table->string('payer_name')->nullable();
            $table->date('activity_date');
            $table->decimal('amount', 12, 2)->default(0);
            $table->integer('quantity')->default(1);
            $table->string('reference')->nullable(); // claim #, check #, etc.
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['agency_id', 'activity_date']);
            $table->index(['billing_client_id', 'activity_type']);
        });

        // Billing financials — monthly summary per client (claims, billed, collected, denied)
        Schema::create('billing_financials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_client_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7); // YYYY-MM
            $table->integer('claims_submitted')->default(0);
            $table->decimal('amount_billed', 12, 2)->default(0);
            $table->decimal('amount_collected', 12, 2)->default(0);
            $table->integer('denial_count')->default(0);
            $table->decimal('denied_amount', 12, 2)->default(0);
            $table->decimal('adjustments', 12, 2)->default(0);
            $table->decimal('patient_responsibility', 12, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['agency_id', 'billing_client_id', 'period']);
            $table->index(['billing_client_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_financials');
        Schema::dropIfExists('billing_activities');
        Schema::dropIfExists('billing_tasks');
        Schema::dropIfExists('billing_clients');
    }
};

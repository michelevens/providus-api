<?php
// New tables to back V2 endpoints that were 404'ing on the Health Check:
//
//   - prior_authorizations : tracks payer auth#s per patient/CPT (V2's
//     /rcm/authorizations endpoint)
//   - clearinghouse_configs : per-agency Availity OAuth credentials + state
//     (V2's /rcm/clearinghouse/config endpoint, also drives Availity import/pull)
//   - payment_links : Stripe Checkout sessions tied to a patient or invoice
//     (V2's /payments/checkout endpoint + 'Send Pay Link' UI)
//
// All three were referenced by V2 UI but had no backend behind them.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── Prior authorizations ───────────────────────────────────────
        Schema::create('prior_authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('claim_id')->nullable()->constrained()->nullOnDelete();
            $table->string('patient_name')->nullable();
            $table->string('patient_member_id')->nullable();
            $table->string('payer_name')->nullable();
            $table->string('authorization_number');
            $table->string('cpt_code', 10)->nullable();
            $table->string('cpt_codes')->nullable(); // comma-separated when multiple
            $table->decimal('units_authorized', 8, 2)->nullable();
            $table->decimal('units_used', 8, 2)->default(0);
            $table->date('effective_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->string('status', 20)->default('active'); // active, expired, exhausted, denied, cancelled
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['agency_id', 'status']);
            $table->index(['agency_id', 'expiration_date']);
            $table->index('authorization_number');
        });

        // ── Clearinghouse config (Availity / Change Healthcare / etc) ──
        Schema::create('clearinghouse_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('clearinghouse_name', 30)->default('availity');
            $table->string('client_id')->nullable();
            $table->text('client_secret_encrypted')->nullable(); // Laravel Crypt
            $table->string('submitter_id')->nullable();
            $table->string('organization_name')->nullable();
            $table->timestamp('last_pulled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('connected')->default(false);
            $table->timestamps();
        });

        // ── Stripe payment links (Checkout sessions) ───────────────────
        Schema::create('payment_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_client_id')->nullable()->constrained()->nullOnDelete();
            // What this payment is for. One of:
            // patient_balance, patient_statement, invoice
            $table->string('target_type', 30);
            // Polymorphic-ish — id of patient, statement, or invoice referenced
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('patient_name')->nullable();
            $table->string('patient_email')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('usd');
            // Public token in the URL — distinct from the Stripe session id so
            // we can rotate it independently if leaked
            $table->string('public_token', 64)->unique();
            $table->string('stripe_session_id')->nullable();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('checkout_url', 1000)->nullable();
            $table->string('status', 20)->default('pending'); // pending, paid, expired, refunded, cancelled
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['agency_id', 'status']);
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_links');
        Schema::dropIfExists('clearinghouse_configs');
        Schema::dropIfExists('prior_authorizations');
    }
};

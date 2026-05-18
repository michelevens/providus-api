<?php

// Extend billing_clients with the rate fields needed to compute
// monthly RCM + credentialing invoices automatically from activity.
//
// The existing fee_structure + monthly_fee columns describe ONE pricing
// dimension (RCM-only, single model). Real agencies stack a credentialing
// rate on top of RCM and need percent / per-claim / per-app primitives,
// not a single enum. The new columns keep the old ones in place for
// backward compatibility but the auto-invoice generator reads the new
// ones exclusively.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_clients', function (Blueprint $table) {
            // RCM pricing
            //   percentage      — % of collections / billed / adjudicated
            //   per_claim       — flat $ per claim submitted in period
            //   flat_monthly    — fixed retainer
            //   hybrid          — base retainer + % of collections
            //   none            — agency does not bill this client for RCM
            $table->string('rcm_pricing_model', 20)->default('none')->after('fee_structure');
            $table->decimal('rcm_percentage_rate', 6, 4)->default(0)->after('rcm_pricing_model');           // 0.0600 = 6%
            $table->string('rcm_percentage_basis', 20)->default('collections')->after('rcm_percentage_rate'); // collections / billed / adjudicated
            $table->decimal('rcm_per_claim_rate', 8, 2)->default(0)->after('rcm_percentage_basis');         // $/claim
            $table->decimal('rcm_monthly_base', 10, 2)->default(0)->after('rcm_per_claim_rate');            // base retainer for hybrid/flat

            // Credentialing pricing
            //   per_app                — flat $ per application submitted
            //   per_provider_monthly   — flat $ per active provider per month
            //   included               — bundled into RCM, no separate line
            //   none                   — not provided
            $table->string('credentialing_pricing_model', 30)->default('none');
            $table->decimal('credentialing_per_app_rate', 8, 2)->default(0);
            $table->decimal('credentialing_per_provider_rate', 8, 2)->default(0);

            // One-time setup
            $table->decimal('setup_fee', 10, 2)->default(0);
            $table->boolean('setup_fee_billed')->default(false); // tracks whether we've already invoiced it

            // Add-on rates — line items the generator adds when activity occurs
            $table->decimal('statement_send_rate', 6, 2)->default(0);        // $/statement
            $table->decimal('eligibility_check_rate', 6, 2)->default(0);     // $/check
            $table->decimal('denial_appeal_rate', 8, 2)->default(0);         // $/appeal

            // Optional contract metadata
            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->string('billing_day', 5)->default('1');                  // day of month to generate invoices
        });
    }

    public function down(): void
    {
        Schema::table('billing_clients', function (Blueprint $table) {
            $table->dropColumn([
                'rcm_pricing_model', 'rcm_percentage_rate', 'rcm_percentage_basis',
                'rcm_per_claim_rate', 'rcm_monthly_base',
                'credentialing_pricing_model', 'credentialing_per_app_rate', 'credentialing_per_provider_rate',
                'setup_fee', 'setup_fee_billed',
                'statement_send_rate', 'eligibility_check_rate', 'denial_appeal_rate',
                'contract_start_date', 'contract_end_date', 'billing_day',
            ]);
        });
    }
};

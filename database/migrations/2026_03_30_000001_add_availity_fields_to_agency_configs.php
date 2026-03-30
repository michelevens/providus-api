<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agency_configs', function (Blueprint $table) {
            $table->text('availity_client_id')->nullable()->after('caqh_environment');
            $table->text('availity_client_secret')->nullable()->after('availity_client_id');
            $table->string('availity_customer_id')->nullable()->after('availity_client_secret');
            $table->string('availity_env', 20)->default('production')->after('availity_customer_id');
        });

        // Add EDI/ERA/EFT fields to payers table
        if (Schema::hasTable('payers')) {
            Schema::table('payers', function (Blueprint $table) {
                $table->string('edi_status', 20)->nullable()->after('notes');
                $table->date('edi_effective_date')->nullable()->after('edi_status');
                $table->string('era_status', 20)->nullable()->after('edi_effective_date');
                $table->date('era_effective_date')->nullable()->after('era_status');
                $table->string('eft_status', 20)->nullable()->after('era_effective_date');
                $table->date('eft_effective_date')->nullable()->after('eft_status');
                $table->string('clearinghouse')->nullable()->after('eft_effective_date');
                $table->string('edi_payer_id')->nullable()->after('clearinghouse');
                $table->text('edi_notes')->nullable()->after('edi_payer_id');
            });
        }

        // Add provider column to eligibility_checks if missing
        if (Schema::hasTable('eligibility_checks') && !Schema::hasColumn('eligibility_checks', 'provider')) {
            Schema::table('eligibility_checks', function (Blueprint $table) {
                $table->string('provider', 20)->default('stedi')->after('agency_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('agency_configs', function (Blueprint $table) {
            $table->dropColumn(['availity_client_id', 'availity_client_secret', 'availity_customer_id', 'availity_env']);
        });

        if (Schema::hasTable('payers')) {
            Schema::table('payers', function (Blueprint $table) {
                $table->dropColumn(['edi_status', 'edi_effective_date', 'era_status', 'era_effective_date', 'eft_status', 'eft_effective_date', 'clearinghouse', 'edi_payer_id', 'edi_notes']);
            });
        }

        if (Schema::hasTable('eligibility_checks') && Schema::hasColumn('eligibility_checks', 'provider')) {
            Schema::table('eligibility_checks', function (Blueprint $table) {
                $table->dropColumn('provider');
            });
        }
    }
};

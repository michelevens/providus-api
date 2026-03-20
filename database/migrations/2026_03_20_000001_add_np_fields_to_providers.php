<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            // NP-specific credentialing fields
            $table->date('date_of_birth')->nullable()->after('last_name');
            $table->string('ssn_last4', 4)->nullable()->after('date_of_birth');
            $table->string('gender', 20)->nullable()->after('ssn_last4');

            // Address
            $table->string('address_street')->nullable()->after('phone');
            $table->string('address_city', 100)->nullable()->after('address_street');
            $table->string('address_state', 2)->nullable()->after('address_city');
            $table->string('address_zip', 10)->nullable()->after('address_state');

            // Collaborative Practice (required in many states for NPs)
            $table->string('supervising_physician')->nullable()->after('caqh_id');
            $table->string('supervising_physician_npi', 10)->nullable()->after('supervising_physician');
            $table->string('collaborative_agreement_status', 30)->nullable()->after('supervising_physician_npi');
            $table->date('collaborative_agreement_expiry')->nullable()->after('collaborative_agreement_status');

            // Scope of Practice
            $table->string('practice_authority', 30)->nullable()->after('collaborative_agreement_expiry');
            $table->boolean('prescriptive_authority')->default(false)->after('practice_authority');
            $table->boolean('controlled_substance_authority')->default(false)->after('prescriptive_authority');
            $table->string('cs_schedule_authority', 50)->nullable()->after('controlled_substance_authority');

            // Professional
            $table->string('state_of_primary_license', 2)->nullable()->after('cs_schedule_authority');
            $table->string('medicaid_id', 30)->nullable()->after('state_of_primary_license');
            $table->string('medicare_ptan', 30)->nullable()->after('medicaid_id');
            $table->text('languages_spoken')->nullable()->after('medicare_ptan');
            $table->text('bio')->nullable()->after('languages_spoken');

            // Onboarding tracking
            $table->string('onboarding_status', 30)->default('pending')->after('is_active');
            $table->timestamp('onboarding_completed_at')->nullable()->after('onboarding_status');
        });
    }

    public function down(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->dropColumn([
                'date_of_birth', 'ssn_last4', 'gender',
                'address_street', 'address_city', 'address_state', 'address_zip',
                'supervising_physician', 'supervising_physician_npi',
                'collaborative_agreement_status', 'collaborative_agreement_expiry',
                'practice_authority', 'prescriptive_authority',
                'controlled_substance_authority', 'cs_schedule_authority',
                'state_of_primary_license', 'medicaid_id', 'medicare_ptan',
                'languages_spoken', 'bio',
                'onboarding_status', 'onboarding_completed_at',
            ]);
        });
    }
};

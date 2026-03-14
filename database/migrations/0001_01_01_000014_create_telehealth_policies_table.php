<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Global reference data — not tenant-scoped
        Schema::create('telehealth_policies', function (Blueprint $table) {
            $table->id();
            $table->string('state', 2)->unique();
            $table->string('practice_authority', 20);
            $table->text('cpa_notes')->nullable();
            $table->boolean('telehealth_parity')->default(false);
            $table->string('controlled_substances', 20)->nullable();
            $table->text('cs_notes')->nullable();
            $table->string('consent_required', 10)->nullable();
            $table->text('consent_notes')->nullable();
            $table->boolean('in_person_required')->default(false);
            $table->text('in_person_notes')->nullable();
            $table->string('originating_site', 10)->nullable();
            $table->boolean('aprn_compact')->default(false);
            $table->boolean('nlc_member')->default(false);
            $table->string('medicaid_telehealth', 20)->nullable();
            $table->text('medicaid_notes')->nullable();
            $table->boolean('audio_only')->default(false);
            $table->string('cross_state_license', 30)->nullable();
            $table->boolean('ryan_haight_exemption')->default(false);
            $table->smallInteger('readiness_score')->nullable();
            $table->text('notes')->nullable();
            $table->date('last_updated')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telehealth_policies');
    }
};

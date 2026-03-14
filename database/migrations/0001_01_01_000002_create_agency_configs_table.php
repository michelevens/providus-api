<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agency_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('stedi_api_key')->nullable();
            $table->string('stedi_npi', 10)->nullable();
            $table->string('stedi_org_name')->nullable();
            $table->string('caqh_org_id', 20)->nullable();
            $table->string('caqh_username', 100)->nullable();
            $table->text('caqh_password')->nullable();
            $table->string('caqh_environment', 20)->default('production');
            $table->string('google_calendar_id')->nullable();
            $table->string('notification_email')->nullable();
            $table->string('provider_name')->nullable();
            $table->integer('elig_monthly_limit')->default(100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_configs');
    }
};

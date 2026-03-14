<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caqh_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('caqh_id', 20)->nullable();
            $table->string('profile_status', 50)->nullable();
            $table->date('profile_status_date')->nullable();
            $table->string('roster_status', 50)->nullable();
            $table->date('attestation_date')->nullable();
            $table->date('attestation_expires')->nullable();
            $table->date('next_attestation')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['agency_id', 'provider_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caqh_tracking');
    }
};

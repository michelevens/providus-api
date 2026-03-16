<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dea_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('dea_number', 20);
            $table->jsonb('schedules')->nullable(); // ["II","III","IV","V"]
            $table->string('state', 2)->nullable();
            $table->string('business_activity', 100)->nullable(); // practitioner, mid-level, etc.
            $table->string('drug_category', 50)->nullable(); // controlled substances category
            $table->string('status', 20)->default('active'); // active, expired, revoked, surrendered
            $table->date('expiration_date')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->jsonb('source_data')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'provider_id']);
            $table->index(['agency_id', 'expiration_date']);
            $table->unique(['agency_id', 'dea_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dea_registrations');
    }
};

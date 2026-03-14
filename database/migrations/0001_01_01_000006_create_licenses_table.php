<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('state', 2);
            $table->string('license_number', 50)->nullable();
            $table->string('license_type', 20)->nullable();
            $table->string('status', 20)->default('pending');
            $table->date('issue_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->date('renewal_date')->nullable();
            $table->boolean('compact_state')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('agency_id');
            $table->index('provider_id');
            $table->index('state');
            $table->index('expiration_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};

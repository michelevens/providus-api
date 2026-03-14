<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('legacy_id', 50)->nullable();
            $table->string('name');
            $table->string('npi', 10)->nullable();
            $table->string('tax_id', 20)->nullable();
            $table->string('address_street')->nullable();
            $table->string('address_city', 100)->nullable();
            $table->string('address_state', 2)->nullable();
            $table->string('address_zip', 10)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('taxonomy', 20)->nullable();
            $table->timestamps();

            $table->index('agency_id');
            $table->index('npi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};

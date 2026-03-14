<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('legacy_id', 50)->nullable();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('credentials', 100)->nullable();
            $table->string('npi', 10)->nullable();
            $table->string('taxonomy', 20)->nullable();
            $table->string('specialty', 100)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('caqh_id', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('agency_id');
            $table->index('organization_id');
            $table->index('npi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Global reference data
        Schema::create('taxonomy_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('type', 50);
            $table->string('specialty', 100);
            $table->string('classification', 100);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomy_codes');
    }
};

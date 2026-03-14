<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('strategy_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('slug', 50)->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->jsonb('target_states')->default('[]');
            $table->jsonb('wave_rules')->default('[]');
            $table->decimal('revenue_threshold', 10, 2)->default(0);
            $table->boolean('auto_wave_assignment')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('agency_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('strategy_profiles');
    }
};

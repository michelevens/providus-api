<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('day_of_week'); // 0=Sun, 6=Sat
            $table->decimal('start_hour', 4, 2)->nullable();
            $table->decimal('end_hour', 4, 2)->nullable();
            $table->boolean('is_closed')->default(false);
            $table->timestamp('updated_at')->useCurrent();

            $table->unique(['agency_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_hours');
    }
};

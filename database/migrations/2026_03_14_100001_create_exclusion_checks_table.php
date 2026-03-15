<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exclusion_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('check_type', 20); // oig, sam, leie, state
            $table->string('status', 20)->default('pending'); // pending, clear, excluded, error
            $table->boolean('is_excluded')->default(false);
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('next_check_at')->nullable();
            $table->jsonb('result_data')->nullable();
            $table->string('exclusion_type')->nullable();
            $table->date('exclusion_date')->nullable();
            $table->date('reinstatement_date')->nullable();
            $table->string('waiver_state')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['agency_id', 'provider_id', 'check_type']);
            $table->index(['agency_id', 'status']);
            $table->index('next_check_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exclusion_checks');
    }
};

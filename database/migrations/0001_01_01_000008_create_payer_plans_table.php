<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payer_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 30)->nullable();
            $table->string('state', 2)->nullable();
            $table->decimal('reimbursement_rate', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('agency_id');
            $table->index('payer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payer_plans');
    }
};

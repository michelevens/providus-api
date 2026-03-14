<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eligibility_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('insurance', 100)->nullable();
            $table->string('member_id', 100)->nullable();
            $table->date('patient_dob')->nullable();
            $table->string('patient_first_name', 100)->nullable();
            $table->string('patient_last_name', 100)->nullable();
            $table->jsonb('stedi_response')->nullable();
            $table->string('status', 20)->nullable();
            $table->string('plan_name')->nullable();
            $table->string('network', 20)->nullable();
            $table->decimal('copay', 10, 2)->nullable();
            $table->integer('coinsurance')->nullable();
            $table->decimal('deductible', 10, 2)->nullable();
            $table->decimal('oop_max', 10, 2)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('agency_id');
            $table->index(['agency_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eligibility_checks');
    }
};

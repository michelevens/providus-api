<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('patient_email')->nullable();
            $table->string('patient_name')->nullable();
            $table->string('display_name', 100)->nullable();
            $table->smallInteger('rating')->nullable();
            $table->text('text')->nullable();
            $table->string('status', 20)->default('requested');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('agency_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};

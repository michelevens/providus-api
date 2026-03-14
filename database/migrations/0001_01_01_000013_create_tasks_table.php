<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('title', 500);
            $table->string('category', 30)->nullable();
            $table->string('priority', 10)->default('normal');
            $table->date('due_date')->nullable();
            $table->foreignId('linked_application_id')->nullable()->constrained('applications')->nullOnDelete();
            $table->string('recurrence', 20)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->timestamps();

            $table->index('agency_id');
            $table->index(['due_date', 'is_completed']);
            $table->index('assigned_to');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};

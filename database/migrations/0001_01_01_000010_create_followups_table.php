<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('followups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->date('due_date');
            $table->date('completed_date')->nullable();
            $table->string('method', 20)->nullable();
            $table->string('contact_name', 100)->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('contact_email')->nullable();
            $table->text('outcome')->nullable();
            $table->text('next_action')->nullable();
            $table->timestamps();

            $table->index('agency_id');
            $table->index('application_id');
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('followups');
    }
};

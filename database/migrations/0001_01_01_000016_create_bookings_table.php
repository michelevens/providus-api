<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('confirmation_code', 20);
            $table->date('appointment_date');
            $table->time('appointment_time');
            $table->integer('duration_minutes')->default(30);
            $table->string('service_type', 100)->nullable();
            $table->string('patient_first_name', 100)->nullable();
            $table->string('patient_last_name', 100)->nullable();
            $table->string('patient_email')->nullable();
            $table->string('patient_phone', 20)->nullable();
            $table->date('patient_dob')->nullable();
            $table->string('insurance', 100)->nullable();
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('calendar_event_id')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('reminder_sent')->default(false);
            $table->timestamps();

            $table->index('agency_id');
            $table->index('confirmation_code');
            $table->index('appointment_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};

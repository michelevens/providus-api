<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('default_recipient_email')->nullable();
            $table->boolean('status_changes')->default(true);
            $table->boolean('license_expiration')->default(true);
            $table->integer('license_expiration_days')->default(30);
            $table->boolean('document_requests')->default(true);
            $table->boolean('weekly_summary')->default(false);
            $table->timestamps();
            $table->unique('agency_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};

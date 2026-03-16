<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('communication_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction', 10); // inbound, outbound
            $table->string('channel', 20); // email, phone, fax, portal, mail
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_info')->nullable(); // email or phone
            $table->string('outcome', 50)->nullable(); // connected, voicemail, no_answer, sent, received, bounced
            $table->integer('duration_seconds')->nullable(); // for phone calls
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['agency_id', 'application_id']);
            $table->index(['agency_id', 'provider_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_logs');
    }
};

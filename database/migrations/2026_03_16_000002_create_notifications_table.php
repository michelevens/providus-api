<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 50); // license_expiring, task_due, app_status, followup_overdue, booking_new, etc.
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('icon', 30)->nullable(); // emoji or icon key
            $table->string('link')->nullable(); // internal route to navigate to
            $table->string('linkable_type', 50)->nullable(); // polymorphic reference
            $table->unsignedBigInteger('linkable_id')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'user_id', 'read_at']);
            $table->index(['linkable_type', 'linkable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};

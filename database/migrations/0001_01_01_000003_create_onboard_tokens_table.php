<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboard_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('provider_email')->nullable();
            $table->string('provider_name')->nullable();
            $table->string('role', 20)->default('provider');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('used_by_user_id')->nullable()->constrained('users');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index('agency_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboard_tokens');
    }
};

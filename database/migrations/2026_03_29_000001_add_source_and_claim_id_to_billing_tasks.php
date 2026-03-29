<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_tasks', function (Blueprint $table) {
            $table->string('source', 20)->default('manual')->after('created_by'); // manual, system
            $table->string('source_key')->nullable()->after('source'); // unique key to prevent duplicate system tasks
            $table->foreignId('claim_id')->nullable()->after('billing_client_id');
            $table->boolean('dismissed')->default(false)->after('source_key');
        });
    }

    public function down(): void
    {
        Schema::table('billing_tasks', function (Blueprint $table) {
            $table->dropColumn(['source', 'source_key', 'claim_id', 'dismissed']);
        });
    }
};

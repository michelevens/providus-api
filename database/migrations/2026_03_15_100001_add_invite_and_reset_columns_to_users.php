<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('invite_token', 64)->nullable()->after('last_login_at');
            $table->timestamp('invite_expires')->nullable()->after('invite_token');
            $table->string('password_reset_token', 64)->nullable()->after('invite_expires');
            $table->timestamp('password_reset_expires')->nullable()->after('password_reset_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['invite_token', 'invite_expires', 'password_reset_token', 'password_reset_expires']);
        });
    }
};

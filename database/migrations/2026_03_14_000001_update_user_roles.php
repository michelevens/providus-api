<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('agency_id')
                ->constrained('organizations')->nullOnDelete();
            $table->foreignId('provider_id')->nullable()->after('organization_id')
                ->constrained('providers')->nullOnDelete();
        });

        // Migrate existing roles to new role system
        DB::table('users')->where('role', 'owner')->update(['role' => 'agency']);
        DB::table('users')->where('role', 'admin')->update(['role' => 'agency']);
        DB::table('users')->where('role', 'staff')->update(['role' => 'agency']);
        DB::table('users')->where('role', 'readonly')->update(['role' => 'agency']);

        // Update default value via raw SQL (avoids doctrine/dbal dependency)
        DB::statement("ALTER TABLE users ALTER COLUMN role SET DEFAULT 'agency'");
    }

    public function down(): void
    {
        // Revert roles back
        DB::table('users')->where('role', 'agency')->update(['role' => 'admin']);
        DB::table('users')->where('role', 'superadmin')->update(['role' => 'owner']);
        DB::table('users')->where('role', 'organization')->update(['role' => 'staff']);
        DB::table('users')->where('role', 'provider')->update(['role' => 'readonly']);

        DB::statement("ALTER TABLE users ALTER COLUMN role SET DEFAULT 'staff'");

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['provider_id']);
            $table->dropColumn(['organization_id', 'provider_id']);
        });
    }
};

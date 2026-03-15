<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop the existing foreign key + index
            $table->dropForeign(['agency_id']);
            $table->dropIndex(['agency_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            // Re-add as nullable (superadmins are platform-level, not agency-bound)
            $table->unsignedBigInteger('agency_id')->nullable()->change();
            $table->foreign('agency_id')->references('id')->on('agencies')->cascadeOnDelete();
            $table->index('agency_id');
        });

        // Detach existing superadmin from agency
        DB::table('users')->where('role', 'superadmin')->update(['agency_id' => null]);
    }

    public function down(): void
    {
        // Re-attach superadmins to agency 1 (or first agency)
        $firstAgencyId = DB::table('agencies')->value('id');
        if ($firstAgencyId) {
            DB::table('users')->where('role', 'superadmin')->whereNull('agency_id')->update(['agency_id' => $firstAgencyId]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['agency_id']);
            $table->dropIndex(['agency_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('agency_id')->nullable(false)->change();
            $table->foreign('agency_id')->references('id')->on('agencies')->cascadeOnDelete();
            $table->index('agency_id');
        });
    }
};

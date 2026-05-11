<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Add impersonator_user_id to audit_logs so we can trace which
// superadmin operator made a change while impersonating a tenant.
//
// When the column is null: the change was made by user_id directly
// (no impersonation). When non-null: user_id is the impersonated
// owner-equivalent (effectively the tenant's identity), and
// impersonator_user_id points to the superadmin who was operating
// the session. The pair gives compliance the full who-did-what trail.

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('impersonator_user_id')->nullable()->after('user_id');
            $table->index('impersonator_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['impersonator_user_id']);
            $table->dropColumn('impersonator_user_id');
        });
    }
};

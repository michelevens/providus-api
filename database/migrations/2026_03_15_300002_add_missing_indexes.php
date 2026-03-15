<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->index(['agency_id', 'status']);
        });

        Schema::table('testimonials', function (Blueprint $table) {
            $table->index(['agency_id', 'created_at']);
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('created_by');
        });

        if (Schema::hasTable('taxonomy_codes')) {
            Schema::table('taxonomy_codes', function (Blueprint $table) {
                $table->index('code');
            });
        }

        if (Schema::hasTable('telehealth_policies')) {
            Schema::table('telehealth_policies', function (Blueprint $table) {
                $table->index('state');
            });
        }
    }

    public function down(): void
    {
        Schema::table('bookings', fn(Blueprint $t) => $t->dropIndex(['agency_id', 'status']));
        Schema::table('testimonials', fn(Blueprint $t) => $t->dropIndex(['agency_id', 'created_at']));
        Schema::table('activity_logs', function (Blueprint $t) {
            $t->dropIndex(['created_at']);
            $t->dropIndex(['created_by']);
        });
        if (Schema::hasTable('taxonomy_codes')) {
            Schema::table('taxonomy_codes', fn(Blueprint $t) => $t->dropIndex(['code']));
        }
        if (Schema::hasTable('telehealth_policies')) {
            Schema::table('telehealth_policies', fn(Blueprint $t) => $t->dropIndex(['state']));
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['providers', 'applications', 'licenses', 'organizations'] as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, fn(Blueprint $t) => $t->softDeletes());
            }
        }
    }

    public function down(): void
    {
        foreach (['providers', 'applications', 'licenses', 'organizations'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, fn(Blueprint $t) => $t->dropSoftDeletes());
            }
        }
    }
};

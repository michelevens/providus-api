<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'invoices', 'invoice_items', 'payments', 'dea_registrations',
            'malpractice_policies', 'board_certifications', 'provider_education',
            'provider_documents', 'facilities', 'provider_cme', 'provider_work_history',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, fn(Blueprint $t) => $t->softDeletes());
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'invoices', 'invoice_items', 'payments', 'dea_registrations',
            'malpractice_policies', 'board_certifications', 'provider_education',
            'provider_documents', 'facilities', 'provider_cme', 'provider_work_history',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, fn(Blueprint $t) => $t->dropSoftDeletes());
            }
        }
    }
};

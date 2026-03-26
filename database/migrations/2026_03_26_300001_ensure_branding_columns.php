<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Force-add branding columns if they don't exist (fixes partial migration failures)
        $columns = collect(DB::select("SELECT column_name FROM information_schema.columns WHERE table_name = 'agencies'"))->pluck('column_name')->toArray();

        if (!in_array('company_display_name', $columns)) {
            DB::statement("ALTER TABLE agencies ADD COLUMN company_display_name VARCHAR(255) NULL");
        }
        if (!in_array('email_footer', $columns)) {
            DB::statement("ALTER TABLE agencies ADD COLUMN email_footer TEXT NULL");
        }
        if (!in_array('custom_domain', $columns)) {
            DB::statement("ALTER TABLE agencies ADD COLUMN custom_domain VARCHAR(255) NULL");
        }
    }

    public function down(): void {}
};

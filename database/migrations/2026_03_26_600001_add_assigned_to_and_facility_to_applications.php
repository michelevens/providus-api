<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $columns = collect(DB::select("SELECT column_name FROM information_schema.columns WHERE table_name = 'applications'"))->pluck('column_name')->toArray();

        if (!in_array('assigned_to', $columns)) {
            DB::statement('ALTER TABLE applications ADD COLUMN assigned_to INTEGER NULL');
        }
        if (!in_array('facility_id', $columns)) {
            DB::statement('ALTER TABLE applications ADD COLUMN facility_id INTEGER NULL');
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE applications DROP COLUMN IF EXISTS assigned_to');
        DB::statement('ALTER TABLE applications DROP COLUMN IF EXISTS facility_id');
    }
};

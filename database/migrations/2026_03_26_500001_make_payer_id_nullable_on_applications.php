<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Drop the foreign key constraint first
        DB::statement('ALTER TABLE applications DROP CONSTRAINT IF EXISTS applications_payer_id_foreign');
        // Make payer_id nullable
        DB::statement('ALTER TABLE applications ALTER COLUMN payer_id DROP NOT NULL');
        DB::statement('ALTER TABLE applications ALTER COLUMN payer_id SET DEFAULT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE applications ALTER COLUMN payer_id SET NOT NULL');
    }
};

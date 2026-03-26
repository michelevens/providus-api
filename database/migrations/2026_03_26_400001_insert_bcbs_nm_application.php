<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('applications')->insert([
            'agency_id' => 1,
            'provider_id' => 1,
            'state' => 'NM',
            'payer_name' => 'BCBS of New Mexico',
            'status' => 'approved',
            'submitted_date' => '2025-01-30',
            'effective_date' => '2025-03-15',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('applications')
            ->where('payer_name', 'BCBS of New Mexico')
            ->where('provider_id', 1)
            ->where('state', 'NM')
            ->delete();
    }
};

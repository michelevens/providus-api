<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Clean up any stuck migration entry from the removed file
        DB::table('migrations')
            ->where('migration', 'like', '%change_logo_url_to_text%')
            ->delete();
    }

    public function down(): void
    {
        // Nothing to undo
    }
};

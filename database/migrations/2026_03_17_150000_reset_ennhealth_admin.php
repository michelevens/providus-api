<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Change email for EnnHealth admin — password must be reset via forgot-password flow
        DB::table('users')
            ->where('email', 'admin@ennhealth.com')
            ->update(['email' => 'emichel@ennhealth.com']);
    }

    public function down(): void
    {
        DB::table('users')
            ->where('email', 'emichel@ennhealth.com')
            ->update([
                'email' => 'admin@ennhealth.com',
            ]);
    }
};

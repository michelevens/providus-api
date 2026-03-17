<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        // Change email and reset password for EnnHealth admin (user id 1)
        DB::table('users')
            ->where('email', 'admin@ennhealth.com')
            ->update([
                'email' => 'emichel@ennhealth.com',
                'password' => Hash::make('EnnHealth2026!'),
            ]);
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

<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Make agency_id nullable so superadmins can exist without an agency
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('agency_id')->nullable()->change();
        });

        // Create superadmin if not exists
        if (!User::where('email', 'superadmin@credentik.com')->exists()) {
            User::create([
                'email' => 'superadmin@credentik.com',
                'password' => Hash::make('Credentik2026!'),
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'role' => 'superadmin',
                'agency_id' => null,
                'is_active' => true,
            ]);
        }
    }

    public function down(): void
    {
        User::where('email', 'superadmin@credentik.com')->delete();
    }
};

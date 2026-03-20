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

        // Create superadmin if not exists — password from SUPERADMIN_PASSWORD env var
        if (!User::where('email', 'superadmin@credentik.com')->exists()) {
            $password = env('SUPERADMIN_PASSWORD');
            if (!$password) {
                throw new \RuntimeException('SUPERADMIN_PASSWORD env var is required to create the superadmin account.');
            }
            User::create([
                'email' => 'superadmin@credentik.com',
                'password' => Hash::make($password),
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

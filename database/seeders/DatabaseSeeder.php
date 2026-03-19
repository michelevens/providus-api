<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // Global reference data (no tenant scope)
            StateSeeder::class,
            PayerSeeder::class,
            TaxonomyCodeSeeder::class,
            TelehealthPolicySeeder::class,
            DocumentTypeSeeder::class,
            CptCodeSeeder::class,
            PlaceOfServiceSeeder::class,
            BillingModifierSeeder::class,
            LicenseTypeSeeder::class,
            BoardCertificationTypeSeeder::class,
            DenialReasonSeeder::class,
            InsurancePlanTypeSeeder::class,

            // Default strategy profiles
            StrategyProfileSeeder::class,

            // Default credentialing service catalog
            ServiceCatalogSeeder::class,

            // EnnHealth demo data (tenant-scoped)
            EnnHealthDataSeeder::class,

            // Demo user accounts for all role levels
            DemoUserSeeder::class,
        ]);

        // Ensure superadmin account exists
        $superadmin = User::where('email', 'superadmin@credentik.com')->first();
        if (!$superadmin) {
            User::create([
                'email' => 'superadmin@credentik.com',
                'password' => bcrypt('Credentik2026!'),
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'role' => 'superadmin',
                'agency_id' => null,
                'is_active' => true,
            ]);
        } elseif ($superadmin->role !== 'superadmin') {
            $superadmin->update(['role' => 'superadmin']);
        }

        // Ensure EnnHealth admin has a known password (raw DB to bypass hashed cast)
        DB::table('users')
            ->where('email', 'emichel@ennhealth.com')
            ->update(['password' => Hash::make('EnnHealth2026!')]);
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
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
            // 253 BH-relevant payers (IDs 100+) that used to live as a
            // client-side JS file; now sourced from the DB so V1 + V2
            // see the same catalog without merge-on-boot drift.
            SupplementalPayerSeeder::class,
            TaxonomyCodeSeeder::class,
            TelehealthPolicySeeder::class,
            DocumentTypeSeeder::class,
            CptCodeSeeder::class,
            MedicareRateSeeder::class,
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

        // Ensure superadmin accounts exist — always syncs password from SUPERADMIN_PASSWORD env var
        $saPassword = env('SUPERADMIN_PASSWORD');
        if (!$saPassword) {
            $this->command->error('SUPERADMIN_PASSWORD env var is required for superadmins. Skipping.');
        } else {
            $superadmins = [
                ['email' => 'superadmin@credentik.com', 'first_name' => 'Super', 'last_name' => 'Admin'],
                ['email' => 'contact+credentiksuper1@ennhealth.com', 'first_name' => 'Super', 'last_name' => 'Admin 1'],
                ['email' => 'contact+credentiksuper2@ennhealth.com', 'first_name' => 'Super', 'last_name' => 'Admin 2'],
            ];

            foreach ($superadmins as $sa) {
                User::updateOrCreate(
                    ['email' => $sa['email']],
                    [
                        'password' => Hash::make($saPassword),
                        'first_name' => $sa['first_name'],
                        'last_name' => $sa['last_name'],
                        'role' => 'superadmin',
                        'agency_id' => null,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}

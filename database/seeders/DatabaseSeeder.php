<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

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

            // EnnHealth demo data (tenant-scoped)
            EnnHealthDataSeeder::class,
        ]);

        // Promote admin@ennhealth.com to superadmin
        $admin = User::where('email', 'admin@ennhealth.com')->first();
        if ($admin && $admin->role !== 'superadmin') {
            $admin->update(['role' => 'superadmin']);
        }
    }
}

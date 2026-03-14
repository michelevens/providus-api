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
            PayerSeeder::class,
            TelehealthPolicySeeder::class,
            TaxonomyCodeSeeder::class,
            StrategyProfileSeeder::class,
            EnnHealthDataSeeder::class,
        ]);

        // Promote admin@ennhealth.com to superadmin
        $admin = User::where('email', 'admin@ennhealth.com')->first();
        if ($admin && $admin->role !== 'superadmin') {
            $admin->update(['role' => 'superadmin']);
        }
    }
}

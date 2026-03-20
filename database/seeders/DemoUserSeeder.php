<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        // Find EnnHealth agency for scoped demo users
        $agency = Agency::where('slug', 'ennhealth-psychiatry')->first();
        if (!$agency) {
            $this->command->warn('EnnHealth agency not found — creating demo accounts without agency scope.');
        }

        $agencyId = $agency?->id;

        // Find first organization under this agency (for org-level user)
        $orgId = null;
        if ($agencyId) {
            $org = \App\Models\Organization::where('agency_id', $agencyId)->first();
            $orgId = $org?->id;
        }

        // Find first provider under this agency (for provider-level user)
        $providerId = null;
        if ($agencyId) {
            $provider = \App\Models\Provider::where('agency_id', $agencyId)->first();
            $providerId = $provider?->id;
        }

        $demoPassword = env('DEMO_USER_PASSWORD');
        if (!$demoPassword) {
            $this->command->error('DEMO_USER_PASSWORD env var is required. Skipping demo user seeding.');
            return;
        }

        $demoUsers = [
            [
                'email' => 'owner@demo.credentik.com',
                'first_name' => 'Dana',
                'last_name' => 'Owner',
                'role' => 'owner',
                'agency_id' => $agencyId,
            ],
            [
                'email' => 'agency@demo.credentik.com',
                'first_name' => 'Alex',
                'last_name' => 'Agency',
                'role' => 'agency',
                'agency_id' => $agencyId,
            ],
            [
                'email' => 'org@demo.credentik.com',
                'first_name' => 'Olivia',
                'last_name' => 'Org',
                'role' => 'organization',
                'agency_id' => $agencyId,
                'organization_id' => $orgId,
            ],
            [
                'email' => 'provider@demo.credentik.com',
                'first_name' => 'Pat',
                'last_name' => 'Provider',
                'role' => 'provider',
                'agency_id' => $agencyId,
                'provider_id' => $providerId,
            ],
        ];

        foreach ($demoUsers as $userData) {
            $user = User::where('email', $userData['email'])->first();
            if ($user) {
                $user->update(array_merge($userData, [
                    'password' => Hash::make($demoPassword),
                    'is_active' => true,
                ]));
                $this->command->info("Updated: {$userData['email']} ({$userData['role']})");
            } else {
                User::create(array_merge($userData, [
                    'password' => Hash::make($demoPassword),
                    'is_active' => true,
                ]));
                $this->command->info("Created: {$userData['email']} ({$userData['role']})");
            }
        }

        $this->command->info('Demo users ready.');
    }
}

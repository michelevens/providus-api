<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\AgencyConfig;
use App\Models\Organization;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $demoPassword = env('DEMO_USER_PASSWORD', 'Demo@2026!');

        // ── Create or find the Demo Agency (separate from real agencies) ──
        $agency = Agency::firstOrCreate(
            ['slug' => 'demo-agency'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Demo Credentialing Agency',
                'slug' => 'demo-agency',
                'npi' => '1234567890',
                'tax_id' => '12-3456789',
                'address_street' => '100 Demo Boulevard, Suite 200',
                'address_city' => 'Orlando',
                'address_state' => 'FL',
                'address_zip' => '32801',
                'phone' => '(555) 123-4567',
                'email' => 'demo@credentik.com',
                'taxonomy' => '2084P0800X',
                'plan_tier' => 'professional',
                'is_active' => true,
            ]
        );
        $this->command->info("Demo agency: {$agency->name} (id={$agency->id})");

        // Create agency config if not exists
        AgencyConfig::firstOrCreate(['agency_id' => $agency->id]);

        // ── Create Demo Organization ──
        $org = Organization::firstOrCreate(
            ['agency_id' => $agency->id, 'name' => 'Demo Psychiatry Group'],
            [
                'npi' => '1234567891',
                'tax_id' => '12-3456790',
                'phone' => '(555) 234-5678',
                'email' => 'office@demopsych.com',
                'address_street' => '200 Mental Health Ave',
                'address_city' => 'Orlando',
                'address_state' => 'FL',
                'address_zip' => '32801',
                'taxonomy' => '2084P0800X',
            ]
        );
        $this->command->info("Demo org: {$org->name} (id={$org->id})");

        // ── Create Demo Provider ──
        $provider = Provider::firstOrCreate(
            ['agency_id' => $agency->id, 'npi' => '1122334455'],
            [
                'organization_id' => $org->id,
                'first_name' => 'Sarah',
                'last_name' => 'Demo',
                'credentials' => 'DNP, PMHNP-BC',
                'specialty' => 'Psychiatric Mental Health',
                'taxonomy_code' => '363LP0808X',
                'email' => 'sarah.demo@demopsych.com',
                'phone' => '(555) 345-6789',
                'state' => 'FL',
                'active' => true,
            ]
        );
        $this->command->info("Demo provider: {$provider->first_name} {$provider->last_name} (id={$provider->id})");

        // ── Create Demo Users ──
        $demoUsers = [
            [
                'email' => 'agency@demo.credentik.com',
                'first_name' => 'Alex',
                'last_name' => 'Agency',
                'role' => 'agency',
                'agency_id' => $agency->id,
            ],
            [
                'email' => 'staff@demo.credentik.com',
                'first_name' => 'Sam',
                'last_name' => 'Staff',
                'role' => 'agency',  // Backend role is agency; frontend treats as staff via ui_role
                'agency_id' => $agency->id,
            ],
            [
                'email' => 'org@demo.credentik.com',
                'first_name' => 'Olivia',
                'last_name' => 'Org',
                'role' => 'organization',
                'agency_id' => $agency->id,
                'organization_id' => $org->id,
            ],
            [
                'email' => 'provider@demo.credentik.com',
                'first_name' => 'Pat',
                'last_name' => 'Provider',
                'role' => 'provider',
                'agency_id' => $agency->id,
                'provider_id' => $provider->id,
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

        $this->command->info('');
        $this->command->info('Demo accounts ready! Login at app.credentik.com');
        $this->command->info('  Agency:   agency@demo.credentik.com');
        $this->command->info('  Staff:    staff@demo.credentik.com');
        $this->command->info('  Org:      org@demo.credentik.com');
        $this->command->info('  Provider: provider@demo.credentik.com');
    }
}

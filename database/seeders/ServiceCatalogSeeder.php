<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServiceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            ['name' => 'Initial Credentialing', 'code' => 'CRED-INIT', 'category' => 'credentialing', 'default_price' => 350.00, 'description' => 'Full initial credentialing for a new provider with a payer'],
            ['name' => 'Re-Credentialing', 'code' => 'CRED-RE', 'category' => 'credentialing', 'default_price' => 200.00, 'description' => 'Periodic re-credentialing with existing payer'],
            ['name' => 'Payer Enrollment', 'code' => 'ENROLL', 'category' => 'enrollment', 'default_price' => 300.00, 'description' => 'New payer enrollment application and follow-up'],
            ['name' => 'CAQH Profile Setup', 'code' => 'CAQH-NEW', 'category' => 'credentialing', 'default_price' => 250.00, 'description' => 'Initial CAQH ProView profile creation and attestation'],
            ['name' => 'CAQH Profile Update', 'code' => 'CAQH-UPD', 'category' => 'credentialing', 'default_price' => 100.00, 'description' => 'Quarterly CAQH re-attestation and profile updates'],
            ['name' => 'License Renewal', 'code' => 'LIC-RENEW', 'category' => 'licensing', 'default_price' => 150.00, 'description' => 'State license renewal application and tracking'],
            ['name' => 'New State License', 'code' => 'LIC-NEW', 'category' => 'licensing', 'default_price' => 275.00, 'description' => 'New state license application for provider'],
            ['name' => 'DEA Registration', 'code' => 'DEA-REG', 'category' => 'licensing', 'default_price' => 175.00, 'description' => 'DEA registration or renewal processing'],
            ['name' => 'NPI Registration', 'code' => 'NPI-REG', 'category' => 'enrollment', 'default_price' => 100.00, 'description' => 'Individual or organizational NPI application'],
            ['name' => 'Medicare Enrollment', 'code' => 'ENROLL-MCR', 'category' => 'enrollment', 'default_price' => 400.00, 'description' => 'Medicare PECOS enrollment and follow-up'],
            ['name' => 'Medicaid Enrollment', 'code' => 'ENROLL-MCD', 'category' => 'enrollment', 'default_price' => 350.00, 'description' => 'State Medicaid enrollment application'],
            ['name' => 'Provider Onboarding', 'code' => 'ONBOARD', 'category' => 'consulting', 'default_price' => 500.00, 'description' => 'Full provider onboarding — documents, applications, and setup'],
            ['name' => 'Compliance Audit', 'code' => 'AUDIT', 'category' => 'consulting', 'default_price' => 750.00, 'description' => 'Credentialing file audit and compliance review'],
            ['name' => 'Privileging Application', 'code' => 'PRIV', 'category' => 'credentialing', 'default_price' => 300.00, 'description' => 'Hospital or facility privileging application'],
            ['name' => 'Monthly Maintenance', 'code' => 'MAINT', 'category' => 'other', 'default_price' => 150.00, 'description' => 'Ongoing monthly credentialing maintenance and monitoring'],
        ];

        foreach ($services as $svc) {
            // Insert as global (agency_id null) so all agencies get them as defaults
            DB::table('service_catalog')->updateOrInsert(
                ['agency_id' => null, 'code' => $svc['code']],
                array_merge($svc, ['agency_id' => null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}

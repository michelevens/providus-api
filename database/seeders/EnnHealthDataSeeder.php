<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\License;
use App\Models\Organization;
use App\Models\Provider;
use Illuminate\Database\Seeder;

class EnnHealthDataSeeder extends Seeder
{
    public function run(): void
    {
        $agency = Agency::where('slug', 'ennhealth-psychiatry')->first();
        if (!$agency) {
            $this->command->warn('EnnHealth Psychiatry agency not found — skipping.');
            return;
        }

        // Update agency with full details
        $agency->update([
            'npi' => '1861107849',
            'tax_id' => '92-1746886',
            'address_street' => '1230 Okaley Seaver Dr, Suite 101',
            'address_city' => 'Clermont',
            'address_state' => 'FL',
            'address_zip' => '34711',
            'phone' => '(407) 796-2406',
            'taxonomy' => '2084P0800X',
        ]);

        $agencyId = $agency->id;

        // ── Organization ──
        $org = Organization::updateOrCreate(
            ['agency_id' => $agencyId, 'npi' => '1861107849'],
            [
                'name' => 'EnnHealth Psychiatry',
                'tax_id' => '92-1746886',
                'address_street' => '1230 Okaley Seaver Dr, Suite 101',
                'address_city' => 'Clermont',
                'address_state' => 'FL',
                'address_zip' => '34711',
                'phone' => '(407) 796-2406',
                'email' => 'contact@ennhealth.com',
                'taxonomy' => '2084P0800X',
            ]
        );

        // ── Provider ──
        $provider = Provider::updateOrCreate(
            ['agency_id' => $agencyId, 'npi' => '1093258642'],
            [
                'organization_id' => $org->id,
                'first_name' => 'Nageley',
                'last_name' => 'Michel',
                'credentials' => 'DNP, PMHNP-BC, FNP-BC',
                'taxonomy' => '363LP0808X',
                'specialty' => 'Psychiatric Mental Health',
                'email' => 'nageleymichel@gmail.com',
                'phone' => '(407) 796-2406',
                'is_active' => true,
            ]
        );

        // ── Licenses (30 states) ──
        $licenses = [
            ['state'=>'AK','status'=>'active','license_type'=>'CNP','license_number'=>'241960','issue_date'=>'2025-08-07','expiration_date'=>'2026-11-30','compact_state'=>false,'notes'=>'Focus: Family + Psych/MH. Rx: YES.'],
            ['state'=>'AZ','status'=>'active','license_type'=>'CNP','license_number'=>'298306','issue_date'=>'2023-09-29','expiration_date'=>'2026-07-31','compact_state'=>false,'notes'=>'Focus: Family Psych/MH. Rx: YES. Cert exp 09/14/2030.'],
            ['state'=>'CA','status'=>'active','license_type'=>'CNP','license_number'=>'NPF95033893','issue_date'=>'2025-02-05','expiration_date'=>null,'compact_state'=>false,'notes'=>'NP Furnishing Number. CA Dept of Consumer Affairs. Rx: YES.'],
            ['state'=>'CO','status'=>'active','license_type'=>'CNP','license_number'=>'C-RXN.0101891-C-NP','issue_date'=>'2024-09-12','expiration_date'=>'2026-09-30','compact_state'=>true,'notes'=>'Compact NP - C-RXN. Psych/MH. CO Dept of Regulatory Agencies.'],
            ['state'=>'CT','status'=>'active','license_type'=>'CNP','license_number'=>'14541','issue_date'=>'2025-02-23','expiration_date'=>'2025-10-31','compact_state'=>false,'notes'=>'APRN. Dept of Public Health. Exp 10/31/2025 — may need renewal verification.'],
            ['state'=>'DC','status'=>'active','license_type'=>'CNP','license_number'=>'NP500327215','issue_date'=>'2025-10-07','expiration_date'=>'2027-10-31','compact_state'=>false,'notes'=>'Nurse Practitioner. DC Dept of Health, Board of Medicine.'],
            ['state'=>'FL','status'=>'active','license_type'=>'CNP','license_number'=>'APRN9245433','issue_date'=>'2016-04-15','expiration_date'=>'2026-07-31','compact_state'=>false,'notes'=>'Home state. Focus: Psych/MH + Family. Rx: YES. RN is MULTISTATE.'],
            ['state'=>'IA','status'=>'active','license_type'=>'CNP','license_number'=>'G184558','issue_date'=>'2025-05-19','expiration_date'=>'2026-07-31','compact_state'=>false,'notes'=>'Focus: Psych/MH. Rx: YES.'],
            ['state'=>'ID','status'=>'active','license_type'=>'CNP','license_number'=>'7871669','issue_date'=>'2025-07-17','expiration_date'=>'2027-08-31','compact_state'=>false,'notes'=>'Focus: NP. Rx: YES.'],
            ['state'=>'KS','status'=>'active','license_type'=>'CNP','license_number'=>'53-84266-101','issue_date'=>'2025-04-15','expiration_date'=>'2027-10-31','compact_state'=>false,'notes'=>'Focus: Psych/MH.'],
            ['state'=>'MA','status'=>'active','license_type'=>'CNP','license_number'=>'RN10023994','issue_date'=>null,'expiration_date'=>null,'compact_state'=>false,'notes'=>'Certified NP Authorization. MA Dept of Public Health, Board of Registration in Nursing.'],
            ['state'=>'MD','status'=>'active','license_type'=>'CNP','license_number'=>'AC007801','issue_date'=>'2025-06-24','expiration_date'=>'2027-10-28','compact_state'=>true,'notes'=>'AC-CRNP-PMH. Compact State Additional Cert. Original state FL. MD Board of Nursing.'],
            ['state'=>'MN','status'=>'active','license_type'=>'CNP','license_number'=>'13207','issue_date'=>'2025-08-07','expiration_date'=>null,'compact_state'=>false,'notes'=>'Provider confirmed active. Nursys showed "CONTACT BOARD" — may be resolved.'],
            ['state'=>'MT','status'=>'active','license_type'=>'CNP','license_number'=>'APRN218560','issue_date'=>null,'expiration_date'=>'2026-12-31','compact_state'=>false,'notes'=>'Focus: Family. Rx: YES. Cert exp 09/14/2030.'],
            ['state'=>'ND','status'=>'active','license_type'=>'CNP','license_number'=>'202622','issue_date'=>'2025-07-07','expiration_date'=>null,'compact_state'=>false,'notes'=>'Provider confirmed active. Focus: Psych/MH.'],
            ['state'=>'NH','status'=>'active','license_type'=>'CNP','license_number'=>'AC007801','issue_date'=>'2025-06-24','expiration_date'=>'2027-10-28','compact_state'=>true,'notes'=>'AC-CRNP-PMH. Compact State Additional Cert. Original state FL.'],
            ['state'=>'NM','status'=>'active','license_type'=>'CNP','license_number'=>'74998','issue_date'=>'2023-08-18','expiration_date'=>'2026-03-15','compact_state'=>false,'notes'=>'Focus: Psych/MH. Rx: YES. Specialty exp 03/15/2026.'],
            ['state'=>'NV','status'=>'active','license_type'=>'CNP','license_number'=>'883632','issue_date'=>'2024-12-13','expiration_date'=>'2026-10-12','compact_state'=>false,'notes'=>'Focus: Psych/MH.'],
            ['state'=>'NY','status'=>'active','license_type'=>'CNP','license_number'=>'P00670','issue_date'=>'2025-01-24','expiration_date'=>null,'compact_state'=>false,'notes'=>'Nurse Practitioner in Psychiatry. Cert issued by NY Education Dept.'],
            ['state'=>'OR','status'=>'active','license_type'=>'CNP','license_number'=>'10032628','issue_date'=>'2024-09-16','expiration_date'=>'2027-10-12','compact_state'=>false,'notes'=>'Focus: Psych/MH. Rx: YES. Cert exp 09/14/2030.'],
            ['state'=>'SD','status'=>'active','license_type'=>'CNP','license_number'=>'CP003807','issue_date'=>'2025-09-04','expiration_date'=>'2030-09-14','compact_state'=>false,'notes'=>'Focus: Family + Psych/MH. Rx: YES.'],
            ['state'=>'TX','status'=>'active','license_type'=>'CNP','license_number'=>'1185411','issue_date'=>'2025-01-06','expiration_date'=>'2027-10-31','compact_state'=>false,'notes'=>'Focus: Psych/MH. Rx: YES. Cert exp 09/14/2030.'],
            ['state'=>'UT','status'=>'active','license_type'=>'CNP','license_number'=>'14229038-4405','issue_date'=>'2025-07-08','expiration_date'=>'2028-01-31','compact_state'=>false,'notes'=>'APRN. Psych/MH PMHNP-BC. UT Dept of Commerce.'],
            ['state'=>'VA','status'=>'active','license_type'=>'CNP','license_number'=>'0024195579','issue_date'=>'2025-12-04','expiration_date'=>'2027-10-31','compact_state'=>false,'notes'=>'Focus: Psych/MH. Rx: YES.'],
            ['state'=>'VT','status'=>'active','license_type'=>'CNP','license_number'=>'101.0138011','issue_date'=>'2025-06-23','expiration_date'=>'2026-03-15','compact_state'=>false,'notes'=>'Focus: Family + Psych/MH. Rx: YES.'],
            ['state'=>'WA','status'=>'active','license_type'=>'CNP','license_number'=>'AP61476277','issue_date'=>'2024-09-20','expiration_date'=>'2027-10-12','compact_state'=>false,'notes'=>'Focus: Family + Psych/MH.'],
            ['state'=>'WV','status'=>'active','license_type'=>'CNP','license_number'=>'123920','issue_date'=>'2025-08-11','expiration_date'=>'2027-06-30','compact_state'=>false,'notes'=>'Focus: Psych/MH. Rx: YES. No collaborative agreement required.'],
            ['state'=>'WY','status'=>'active','license_type'=>'CNP','license_number'=>'56391','issue_date'=>'2025-06-26','expiration_date'=>null,'compact_state'=>false,'notes'=>'Provider confirmed active. Focus: Psych/MH.'],
            // Pending
            ['state'=>'IL','status'=>'pending','license_type'=>'CNP','license_number'=>'','issue_date'=>null,'expiration_date'=>null,'compact_state'=>false,'notes'=>'Pending licensure.'],
            ['state'=>'OK','status'=>'pending','license_type'=>'CNP','license_number'=>'226657','issue_date'=>'2025-12-18','expiration_date'=>'2026-10-31','compact_state'=>false,'notes'=>'Pending per provider. Nursys shows APRN-only license active.'],
        ];

        foreach ($licenses as $lic) {
            License::updateOrCreate(
                [
                    'agency_id' => $agencyId,
                    'provider_id' => $provider->id,
                    'state' => $lic['state'],
                ],
                $lic
            );
        }

        $this->command->info("Seeded EnnHealth data: 1 org, 1 provider, " . count($licenses) . " licenses.");
    }
}

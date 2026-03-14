<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\Application;
use App\Models\Followup;
use App\Models\License;
use App\Models\Organization;
use App\Models\Payer;
use App\Models\Provider;
use App\Models\Task;
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

        // ── Applications (credentialing across payers & states) ──
        $payers = Payer::all()->keyBy('name');
        $getPayerId = fn(string $name) => ($payers[$name] ?? null)?->id;

        $applications = [
            // Wave 1 — FL home state (approved/credentialed)
            ['state'=>'FL','payer_name'=>'Aetna','status'=>'approved','wave'=>1,'type'=>'individual','submitted_date'=>'2025-06-15','effective_date'=>'2025-09-01','est_monthly_revenue'=>4200,'notes'=>'Home state. Credentialed via CAQH ProView.'],
            ['state'=>'FL','payer_name'=>'UnitedHealthcare','status'=>'approved','wave'=>1,'type'=>'individual','submitted_date'=>'2025-06-20','effective_date'=>'2025-09-15','est_monthly_revenue'=>5100,'notes'=>'UHC Optum portal. Active.'],
            ['state'=>'FL','payer_name'=>'Cigna','status'=>'approved','wave'=>1,'type'=>'individual','submitted_date'=>'2025-07-01','effective_date'=>'2025-10-01','est_monthly_revenue'=>3800,'notes'=>'Cigna credentialing complete.'],
            ['state'=>'FL','payer_name'=>'Humana','status'=>'approved','wave'=>1,'type'=>'individual','submitted_date'=>'2025-07-10','effective_date'=>'2025-10-15','est_monthly_revenue'=>2900,'notes'=>'Humana FL panel.'],
            ['state'=>'FL','payer_name'=>'Anthem/Elevance','status'=>'approved','wave'=>1,'type'=>'individual','submitted_date'=>'2025-08-01','effective_date'=>'2025-11-01','est_monthly_revenue'=>3500,'notes'=>'Anthem BCBS FL.'],

            // Wave 1 — TX (approved + in progress)
            ['state'=>'TX','payer_name'=>'Aetna','status'=>'approved','wave'=>1,'type'=>'individual','submitted_date'=>'2025-08-15','effective_date'=>'2025-12-01','est_monthly_revenue'=>4800,'notes'=>'TX credentialed.'],
            ['state'=>'TX','payer_name'=>'UnitedHealthcare','status'=>'in_review','wave'=>1,'type'=>'individual','submitted_date'=>'2025-09-10','est_monthly_revenue'=>5500,'notes'=>'Under review. Expected 60-90 days.'],
            ['state'=>'TX','payer_name'=>'Cigna','status'=>'submitted','wave'=>1,'type'=>'individual','submitted_date'=>'2025-10-01','est_monthly_revenue'=>4000,'notes'=>'Submitted via CAQH.'],

            // Wave 1 — NY
            ['state'=>'NY','payer_name'=>'Aetna','status'=>'approved','wave'=>1,'type'=>'individual','submitted_date'=>'2025-07-20','effective_date'=>'2025-11-01','est_monthly_revenue'=>6200,'notes'=>'NY high-volume market. Active.'],
            ['state'=>'NY','payer_name'=>'UnitedHealthcare','status'=>'in_review','wave'=>1,'type'=>'individual','submitted_date'=>'2025-09-15','est_monthly_revenue'=>7100,'notes'=>'UHC NY under review.'],
            ['state'=>'NY','payer_name'=>'Anthem/Elevance','status'=>'pending_info','wave'=>1,'type'=>'individual','submitted_date'=>'2025-10-05','est_monthly_revenue'=>5400,'notes'=>'Anthem requesting additional malpractice documentation.'],

            // Wave 2 — CO, AZ, VA, WA
            ['state'=>'CO','payer_name'=>'Aetna','status'=>'submitted','wave'=>2,'type'=>'individual','submitted_date'=>'2025-11-01','est_monthly_revenue'=>3200,'notes'=>'Compact state. Submitted.'],
            ['state'=>'CO','payer_name'=>'UnitedHealthcare','status'=>'submitted','wave'=>2,'type'=>'individual','submitted_date'=>'2025-11-05','est_monthly_revenue'=>3600,'notes'=>'UHC CO submitted.'],
            ['state'=>'AZ','payer_name'=>'Aetna','status'=>'in_review','wave'=>2,'type'=>'individual','submitted_date'=>'2025-10-20','est_monthly_revenue'=>3400,'notes'=>'AZ under review.'],
            ['state'=>'AZ','payer_name'=>'UnitedHealthcare','status'=>'submitted','wave'=>2,'type'=>'individual','submitted_date'=>'2025-11-10','est_monthly_revenue'=>3800,'notes'=>'Submitted.'],
            ['state'=>'VA','payer_name'=>'Aetna','status'=>'submitted','wave'=>2,'type'=>'individual','submitted_date'=>'2025-11-15','est_monthly_revenue'=>3100,'notes'=>'VA submitted.'],
            ['state'=>'VA','payer_name'=>'Anthem/Elevance','status'=>'submitted','wave'=>2,'type'=>'individual','submitted_date'=>'2025-11-20','est_monthly_revenue'=>3300,'notes'=>'Anthem VA submitted.'],
            ['state'=>'WA','payer_name'=>'Aetna','status'=>'submitted','wave'=>2,'type'=>'individual','submitted_date'=>'2025-12-01','est_monthly_revenue'=>3500,'notes'=>'WA submitted.'],

            // Wave 3 — expansion states (new/gathering_docs)
            ['state'=>'OR','payer_name'=>'Aetna','status'=>'not_started','wave'=>3,'type'=>'individual','est_monthly_revenue'=>2800,'notes'=>'Planned for Q1 2026.'],
            ['state'=>'MA','payer_name'=>'Aetna','status'=>'not_started','wave'=>3,'type'=>'individual','est_monthly_revenue'=>5600,'notes'=>'MA high reimbursement. Planned.'],
            ['state'=>'MD','payer_name'=>'Aetna','status'=>'submitted','wave'=>3,'type'=>'individual','est_monthly_revenue'=>3000,'notes'=>'Compact state. Gathering documents.'],
            ['state'=>'CT','payer_name'=>'UnitedHealthcare','status'=>'not_started','wave'=>3,'type'=>'individual','est_monthly_revenue'=>3400,'notes'=>'CT planned.'],

            // Denied example
            ['state'=>'NV','payer_name'=>'Humana','status'=>'denied','wave'=>2,'type'=>'individual','submitted_date'=>'2025-09-01','est_monthly_revenue'=>2200,'denial_reason'=>'Panel currently closed in NV for PMHNP.','notes'=>'Will resubmit when panel opens.'],
        ];

        $appRecords = [];
        foreach ($applications as $appData) {
            $payerId = $getPayerId($appData['payer_name']);
            $appRecords[] = Application::updateOrCreate(
                [
                    'agency_id' => $agencyId,
                    'provider_id' => $provider->id,
                    'state' => $appData['state'],
                    'payer_name' => $appData['payer_name'],
                ],
                array_merge($appData, [
                    'agency_id' => $agencyId,
                    'provider_id' => $provider->id,
                    'organization_id' => $org->id,
                    'payer_id' => $payerId,
                ])
            );
        }

        // ── Follow-ups ──
        $inReviewApps = Application::where('agency_id', $agencyId)
            ->whereIn('status', ['submitted', 'in_review', 'pending_info'])
            ->get();

        foreach ($inReviewApps as $app) {
            Followup::updateOrCreate(
                ['agency_id' => $agencyId, 'application_id' => $app->id, 'type' => 'status_check'],
                [
                    'agency_id' => $agencyId,
                    'application_id' => $app->id,
                    'type' => 'status_check',
                    'due_date' => now()->addDays(rand(3, 21)),
                    'method' => collect(['phone', 'email', 'portal'])->random(),
                ]
            );
        }

        // ── Tasks ──
        $taskData = [
            ['title'=>'Submit CAQH attestation for Q1 2026','category'=>'credentialing','priority'=>'high','due_date'=>'2026-03-20','is_completed'=>false],
            ['title'=>'Follow up on TX UHC application','category'=>'follow_up','priority'=>'urgent','due_date'=>'2026-03-10','is_completed'=>false],
            ['title'=>'Renew CT license (exp 10/31/2025)','category'=>'licensing','priority'=>'urgent','due_date'=>'2025-10-01','is_completed'=>false],
            ['title'=>'Upload malpractice cert for NY Anthem','category'=>'documentation','priority'=>'high','due_date'=>'2026-03-18','is_completed'=>false],
            ['title'=>'Review NM license renewal (exp 03/15/2026)','category'=>'licensing','priority'=>'high','due_date'=>'2026-02-15','is_completed'=>false],
            ['title'=>'Verify VT license renewal status','category'=>'licensing','priority'=>'medium','due_date'=>'2026-02-28','is_completed'=>false],
            ['title'=>'Set up Stedi eligibility checking','category'=>'setup','priority'=>'medium','due_date'=>'2026-04-01','is_completed'=>false],
            ['title'=>'Initial FL Aetna credentialing submitted','category'=>'credentialing','priority'=>'low','due_date'=>'2025-06-15','is_completed'=>true],
            ['title'=>'FL UHC credentialing complete','category'=>'credentialing','priority'=>'low','due_date'=>'2025-09-15','is_completed'=>true],
        ];

        foreach ($taskData as $task) {
            Task::updateOrCreate(
                ['agency_id' => $agencyId, 'title' => $task['title']],
                array_merge($task, ['agency_id' => $agencyId])
            );
        }

        $appCount = count($applications);
        $followupCount = $inReviewApps->count();
        $taskCount = count($taskData);
        $this->command->info("Seeded EnnHealth data: 1 org, 1 provider, " . count($licenses) . " licenses, {$appCount} applications, {$followupCount} follow-ups, {$taskCount} tasks.");
    }
}

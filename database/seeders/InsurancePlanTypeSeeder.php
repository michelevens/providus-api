<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InsurancePlanTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['slug' => 'commercial',          'name' => 'Commercial / Employer',           'description' => 'Employer-sponsored group health insurance plans. Largest segment of US insurance market. Includes HMO, PPO, POS, and EPO plan types.', 'sort_order' => 1],
            ['slug' => 'medicare',            'name' => 'Medicare (Original)',              'description' => 'Federal health insurance for people 65+ and certain disabilities. Part A (hospital) and Part B (medical). Claims processed by MACs (Medicare Administrative Contractors).', 'sort_order' => 2],
            ['slug' => 'medicare_advantage',  'name' => 'Medicare Advantage (Part C)',      'description' => 'Medicare benefits through private insurance companies. Includes all Part A/B coverage plus often Part D and supplemental benefits. Credentialing with each MA plan required separately.', 'sort_order' => 3],
            ['slug' => 'medicaid',            'name' => 'Medicaid',                         'description' => 'Joint federal-state program for low-income individuals. Managed by state Medicaid agencies, often through managed care organizations (MCOs). Enrollment varies by state.', 'sort_order' => 4],
            ['slug' => 'marketplace',         'name' => 'Marketplace / ACA Exchange',       'description' => 'Individual health insurance plans sold on federal or state exchanges under the Affordable Care Act. Includes subsidized plans (Silver, Gold, etc.).', 'sort_order' => 5],
            ['slug' => 'tricare',             'name' => 'TRICARE',                          'description' => 'Health program for uniformed service members, retirees, and dependents. Managed by Defense Health Agency (DHA). Separate credentialing through TRICARE regional contractors.', 'sort_order' => 6],
            ['slug' => 'workers_comp',        'name' => 'Workers\' Compensation',           'description' => 'State-mandated insurance for workplace injuries and illnesses. Each state has its own system and fee schedules. Provider must be authorized per state rules.', 'sort_order' => 7],
            ['slug' => 'va',                  'name' => 'Veterans Affairs (VA)',             'description' => 'Healthcare through the VA system for eligible veterans. Community Care Network (CCN) enables credentialing with VA-contracted networks (Optum, TriWest).', 'sort_order' => 8],
            ['slug' => 'chip',                'name' => 'CHIP (Children\'s Health)',         'description' => 'Children\'s Health Insurance Program for uninsured children in families that earn too much for Medicaid. Typically administered through Medicaid MCOs.', 'sort_order' => 9],
            ['slug' => 'eap',                 'name' => 'Employee Assistance Program',      'description' => 'Short-term counseling/behavioral health services provided through employer EAP contracts. Typically 3-8 sessions. Separate credentialing from medical insurance.', 'sort_order' => 10],
            ['slug' => 'self_pay',            'name' => 'Self-Pay / Cash Pay',              'description' => 'Direct patient payment without insurance. No credentialing required but may still need provider enrollment for superbills and out-of-network claims.', 'sort_order' => 11],
            ['slug' => 'auto_pip',            'name' => 'Auto / PIP (No-Fault)',            'description' => 'Personal Injury Protection from auto insurance for accident-related medical care. Required in no-fault states (FL, MI, NJ, NY, etc.).', 'sort_order' => 12],
        ];

        foreach ($types as $type) {
            DB::table('insurance_plan_types')->updateOrInsert(
                ['slug' => $type['slug']],
                $type
            );
        }
    }
}

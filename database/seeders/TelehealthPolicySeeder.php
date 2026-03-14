<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TelehealthPolicySeeder extends Seeder
{
    public function run(): void
    {
        $policies = [
            ['state' => 'AK', 'practice_authority' => 'full',       'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 7],
            ['state' => 'AL', 'practice_authority' => 'restricted', 'telehealth_parity' => false, 'controlled_substances' => 'restricted', 'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 4],
            ['state' => 'AR', 'practice_authority' => 'reduced',    'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 6],
            ['state' => 'AZ', 'practice_authority' => 'full',       'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 9],
            ['state' => 'CA', 'practice_authority' => 'reduced',    'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => false, 'nlc_member' => false, 'readiness_score' => 8],
            ['state' => 'CO', 'practice_authority' => 'full',       'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 9],
            ['state' => 'CT', 'practice_authority' => 'full',       'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => false, 'nlc_member' => false, 'readiness_score' => 8],
            ['state' => 'DC', 'practice_authority' => 'full',       'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'no',   'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 8],
            ['state' => 'DE', 'practice_authority' => 'full',       'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 8],
            ['state' => 'FL', 'practice_authority' => 'restricted', 'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => false, 'nlc_member' => false, 'readiness_score' => 7],
            ['state' => 'GA', 'practice_authority' => 'restricted', 'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 6],
            ['state' => 'HI', 'practice_authority' => 'full',       'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => false, 'nlc_member' => false, 'readiness_score' => 7],
            ['state' => 'IA', 'practice_authority' => 'full',       'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 8],
            ['state' => 'ID', 'practice_authority' => 'full',       'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 7],
            ['state' => 'IL', 'practice_authority' => 'reduced',    'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 7],
            ['state' => 'IN', 'practice_authority' => 'reduced',    'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 6],
            ['state' => 'KS', 'practice_authority' => 'reduced',    'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 7],
            ['state' => 'KY', 'practice_authority' => 'reduced',    'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 6],
            ['state' => 'LA', 'practice_authority' => 'restricted', 'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 5],
            ['state' => 'MA', 'practice_authority' => 'reduced',    'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => false, 'nlc_member' => false, 'readiness_score' => 8],
            ['state' => 'MD', 'practice_authority' => 'full',       'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 9],
            ['state' => 'ME', 'practice_authority' => 'full',       'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 8],
            ['state' => 'MI', 'practice_authority' => 'reduced',    'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 7],
            ['state' => 'MN', 'practice_authority' => 'full',       'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 9],
            ['state' => 'MO', 'practice_authority' => 'restricted', 'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 5],
            ['state' => 'MS', 'practice_authority' => 'restricted', 'telehealth_parity' => true,  'controlled_substances' => 'restricted', 'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 4],
            ['state' => 'MT', 'practice_authority' => 'full',       'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 8],
            ['state' => 'NC', 'practice_authority' => 'restricted', 'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 6],
            ['state' => 'ND', 'practice_authority' => 'full',       'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 7],
            ['state' => 'NE', 'practice_authority' => 'full',       'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 8],
            ['state' => 'NH', 'practice_authority' => 'full',       'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 8],
            ['state' => 'NJ', 'practice_authority' => 'reduced',    'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => false, 'nlc_member' => false, 'readiness_score' => 7],
            ['state' => 'NM', 'practice_authority' => 'full',       'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 8],
            ['state' => 'NV', 'practice_authority' => 'full',       'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 8],
            ['state' => 'NY', 'practice_authority' => 'reduced',    'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => false, 'nlc_member' => false, 'readiness_score' => 7],
            ['state' => 'OH', 'practice_authority' => 'reduced',    'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 7],
            ['state' => 'OK', 'practice_authority' => 'restricted', 'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 5],
            ['state' => 'OR', 'practice_authority' => 'full',       'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 9],
            ['state' => 'PA', 'practice_authority' => 'reduced',    'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 7],
            ['state' => 'RI', 'practice_authority' => 'full',       'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => false, 'nlc_member' => false, 'readiness_score' => 7],
            ['state' => 'SC', 'practice_authority' => 'restricted', 'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 5],
            ['state' => 'SD', 'practice_authority' => 'full',       'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 7],
            ['state' => 'TN', 'practice_authority' => 'restricted', 'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 6],
            ['state' => 'TX', 'practice_authority' => 'restricted', 'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 6],
            ['state' => 'UT', 'practice_authority' => 'reduced',    'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 7],
            ['state' => 'VA', 'practice_authority' => 'full',       'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 8],
            ['state' => 'VT', 'practice_authority' => 'full',       'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 8],
            ['state' => 'WA', 'practice_authority' => 'full',       'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 9],
            ['state' => 'WI', 'practice_authority' => 'reduced',    'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 7],
            ['state' => 'WV', 'practice_authority' => 'full',       'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 6],
            ['state' => 'WY', 'practice_authority' => 'full',       'telehealth_parity' => true,  'controlled_substances' => 'allowed',    'consent_required' => 'yes',  'aprn_compact' => true,  'nlc_member' => true,  'readiness_score' => 7],
        ];

        $now = now();

        foreach ($policies as $policy) {
            DB::table('telehealth_policies')->updateOrInsert(
                ['state' => $policy['state']],
                array_merge($policy, [
                    'created_at' => $now,
                ])
            );
        }
    }
}

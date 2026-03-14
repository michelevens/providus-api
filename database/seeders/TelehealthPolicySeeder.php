<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TelehealthPolicySeeder extends Seeder
{
    /**
     * Seed the telehealth_policies table with all 50 states + DC.
     *
     * Data reflects real-world telehealth regulatory landscape:
     * - practice_authority: full, reduced, restricted
     * - parity_law: whether state has telehealth parity law
     * - prescribe_controlled: whether controlled substances can be prescribed via telehealth
     * - consent_required: whether informed consent is required before telehealth visit
     * - compact_member: whether state is a member of the Interstate Medical Licensure Compact
     * - compact_privilege: whether compact privilege practice is allowed
     * - readiness_score: 1-10 composite score for telehealth-friendliness
     */
    public function run(): void
    {
        $policies = [
            // Full practice authority states (NP independence) with strong telehealth
            ['state' => 'AK', 'practice_authority' => 'full',       'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 7],
            ['state' => 'AL', 'practice_authority' => 'restricted', 'parity_law' => false, 'prescribe_controlled' => false, 'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 4],
            ['state' => 'AR', 'practice_authority' => 'reduced',    'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 6],
            ['state' => 'AZ', 'practice_authority' => 'full',       'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 9],
            ['state' => 'CA', 'practice_authority' => 'reduced',    'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => false, 'compact_privilege' => false, 'readiness_score' => 8],
            ['state' => 'CO', 'practice_authority' => 'full',       'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 9],
            ['state' => 'CT', 'practice_authority' => 'full',       'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => false, 'compact_privilege' => false, 'readiness_score' => 8],
            ['state' => 'DC', 'practice_authority' => 'full',       'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => false, 'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 8],
            ['state' => 'DE', 'practice_authority' => 'full',       'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 8],
            ['state' => 'FL', 'practice_authority' => 'restricted', 'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => false, 'compact_privilege' => false, 'readiness_score' => 7],
            ['state' => 'GA', 'practice_authority' => 'restricted', 'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 6],
            ['state' => 'HI', 'practice_authority' => 'full',       'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => false, 'compact_privilege' => false, 'readiness_score' => 7],
            ['state' => 'IA', 'practice_authority' => 'full',       'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 8],
            ['state' => 'ID', 'practice_authority' => 'full',       'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 7],
            ['state' => 'IL', 'practice_authority' => 'reduced',    'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 7],
            ['state' => 'IN', 'practice_authority' => 'reduced',    'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 6],
            ['state' => 'KS', 'practice_authority' => 'reduced',    'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 7],
            ['state' => 'KY', 'practice_authority' => 'reduced',    'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 6],
            ['state' => 'LA', 'practice_authority' => 'restricted', 'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 5],
            ['state' => 'MA', 'practice_authority' => 'reduced',    'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => false, 'compact_privilege' => false, 'readiness_score' => 8],
            ['state' => 'MD', 'practice_authority' => 'full',       'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 9],
            ['state' => 'ME', 'practice_authority' => 'full',       'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 8],
            ['state' => 'MI', 'practice_authority' => 'reduced',    'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 7],
            ['state' => 'MN', 'practice_authority' => 'full',       'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 9],
            ['state' => 'MO', 'practice_authority' => 'restricted', 'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 5],
            ['state' => 'MS', 'practice_authority' => 'restricted', 'parity_law' => true,  'prescribe_controlled' => false, 'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 4],
            ['state' => 'MT', 'practice_authority' => 'full',       'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 8],
            ['state' => 'NC', 'practice_authority' => 'restricted', 'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 6],
            ['state' => 'ND', 'practice_authority' => 'full',       'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 7],
            ['state' => 'NE', 'practice_authority' => 'full',       'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 8],
            ['state' => 'NH', 'practice_authority' => 'full',       'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 8],
            ['state' => 'NJ', 'practice_authority' => 'reduced',    'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => false, 'compact_privilege' => false, 'readiness_score' => 7],
            ['state' => 'NM', 'practice_authority' => 'full',       'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 8],
            ['state' => 'NV', 'practice_authority' => 'full',       'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 8],
            ['state' => 'NY', 'practice_authority' => 'reduced',    'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => false, 'compact_privilege' => false, 'readiness_score' => 7],
            ['state' => 'OH', 'practice_authority' => 'reduced',    'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 7],
            ['state' => 'OK', 'practice_authority' => 'restricted', 'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 5],
            ['state' => 'OR', 'practice_authority' => 'full',       'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 9],
            ['state' => 'PA', 'practice_authority' => 'reduced',    'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 7],
            ['state' => 'RI', 'practice_authority' => 'full',       'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => false, 'compact_privilege' => false, 'readiness_score' => 7],
            ['state' => 'SC', 'practice_authority' => 'restricted', 'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 5],
            ['state' => 'SD', 'practice_authority' => 'full',       'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 7],
            ['state' => 'TN', 'practice_authority' => 'restricted', 'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 6],
            ['state' => 'TX', 'practice_authority' => 'restricted', 'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 6],
            ['state' => 'UT', 'practice_authority' => 'reduced',    'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 7],
            ['state' => 'VA', 'practice_authority' => 'full',       'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 8],
            ['state' => 'VT', 'practice_authority' => 'full',       'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 8],
            ['state' => 'WA', 'practice_authority' => 'full',       'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 9],
            ['state' => 'WI', 'practice_authority' => 'reduced',    'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 7],
            ['state' => 'WV', 'practice_authority' => 'full',       'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 6],
            ['state' => 'WY', 'practice_authority' => 'full',       'parity_law' => true,  'prescribe_controlled' => true,  'consent_required' => true,  'compact_member' => true,  'compact_privilege' => true,  'readiness_score' => 7],
        ];

        $now = now();

        foreach ($policies as $policy) {
            DB::table('telehealth_policies')->updateOrInsert(
                ['state' => $policy['state']],
                array_merge($policy, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StrategyProfileSeeder extends Seeder
{
    /**
     * Seed the strategy_profiles table with 6 default profiles.
     */
    public function run(): void
    {
        $profiles = [
            [
                'agency_id'         => null,
                'name'              => 'Quick Start',
                'description'       => 'Launch fast in 3 high-revenue states with strong telehealth policies and payer density. Ideal for new practices seeking immediate revenue.',
                'target_states'     => json_encode(['CA', 'NY', 'TX']),
                'wave_rules'        => json_encode([
                    ['wave' => 1, 'states' => ['CA', 'NY', 'TX'], 'priority' => 'high', 'target_days' => 60],
                ]),
                'revenue_threshold' => 5000,
            ],
            [
                'agency_id'         => null,
                'name'              => 'Regional Expansion',
                'description'       => 'Establish a strong presence across the Northeast corridor. Two-wave rollout targeting the most populated states first, then filling in adjacent markets.',
                'target_states'     => json_encode(['NY', 'NJ', 'PA', 'CT', 'MA', 'MD', 'VA', 'DC']),
                'wave_rules'        => json_encode([
                    ['wave' => 1, 'states' => ['NY', 'NJ', 'PA', 'MA'], 'priority' => 'high', 'target_days' => 60],
                    ['wave' => 2, 'states' => ['CT', 'MD', 'VA', 'DC'], 'priority' => 'medium', 'target_days' => 90],
                ]),
                'revenue_threshold' => 3000,
            ],
            [
                'agency_id'         => null,
                'name'              => 'National Coverage',
                'description'       => 'Full 50-state credentialing in four waves. Prioritizes highest-population and highest-reimbursement states first, expanding outward to complete national coverage.',
                'target_states'     => json_encode([
                    'AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA',
                    'HI','ID','IL','IN','IA','KS','KY','LA','ME','MD',
                    'MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ',
                    'NM','NY','NC','ND','OH','OK','OR','PA','RI','SC',
                    'SD','TN','TX','UT','VT','VA','WA','WV','WI','WY',
                ]),
                'wave_rules'        => json_encode([
                    ['wave' => 1, 'states' => ['CA', 'NY', 'TX', 'FL', 'PA', 'IL', 'OH', 'GA', 'NC', 'MI', 'NJ', 'VA'], 'priority' => 'critical', 'target_days' => 45],
                    ['wave' => 2, 'states' => ['MA', 'AZ', 'WA', 'CO', 'MN', 'MD', 'TN', 'IN', 'MO', 'CT', 'OR', 'SC'], 'priority' => 'high', 'target_days' => 60],
                    ['wave' => 3, 'states' => ['AL', 'KY', 'LA', 'OK', 'IA', 'UT', 'NV', 'AR', 'KS', 'MS', 'NE', 'NM', 'WV'], 'priority' => 'medium', 'target_days' => 90],
                    ['wave' => 4, 'states' => ['NH', 'ME', 'RI', 'HI', 'ID', 'DE', 'MT', 'SD', 'ND', 'AK', 'VT', 'WY', 'DC', 'WI'], 'priority' => 'standard', 'target_days' => 120],
                ]),
                'revenue_threshold' => 2000,
            ],
            [
                'agency_id'         => null,
                'name'              => 'Telehealth Focus',
                'description'       => 'Target states with the most favorable telehealth regulations — full practice authority, parity laws, compact membership, and high readiness scores. Optimized for 100% virtual practices.',
                'target_states'     => json_encode(['AZ', 'CO', 'MD', 'MN', 'OR', 'WA', 'VA', 'NE', 'NV', 'ME', 'NH', 'VT', 'DC', 'MT', 'IA']),
                'wave_rules'        => json_encode([
                    ['wave' => 1, 'states' => ['AZ', 'CO', 'MD', 'MN', 'OR', 'WA', 'VA', 'DC'], 'priority' => 'high', 'target_days' => 45],
                    ['wave' => 2, 'states' => ['NE', 'NV', 'ME', 'NH', 'VT', 'MT', 'IA'],       'priority' => 'medium', 'target_days' => 75],
                ]),
                'revenue_threshold' => 4000,
            ],
            [
                'agency_id'         => null,
                'name'              => 'High Revenue',
                'description'       => 'Focus on the top 10 states by behavioral health reimbursement rates. These states offer the highest per-session revenue for psychiatric and therapy services.',
                'target_states'     => json_encode(['CA', 'NY', 'MA', 'CT', 'NJ', 'AK', 'WA', 'CO', 'MD', 'MN']),
                'wave_rules'        => json_encode([
                    ['wave' => 1, 'states' => ['CA', 'NY', 'MA', 'CT', 'NJ'], 'priority' => 'critical', 'target_days' => 45],
                    ['wave' => 2, 'states' => ['AK', 'WA', 'CO', 'MD', 'MN'], 'priority' => 'high', 'target_days' => 60],
                ]),
                'revenue_threshold' => 8000,
            ],
            [
                'agency_id'         => null,
                'name'              => 'Medicaid Priority',
                'description'       => 'Target states with the highest Medicaid enrollment and managed care penetration. Three-wave rollout prioritizing states with Centene, Molina, and other large MCO presence for high patient volume.',
                'target_states'     => json_encode(['CA', 'NY', 'TX', 'FL', 'IL', 'PA', 'OH', 'GA', 'NC', 'MI', 'NJ', 'AZ', 'LA', 'TN', 'WA']),
                'wave_rules'        => json_encode([
                    ['wave' => 1, 'states' => ['CA', 'NY', 'TX', 'FL', 'IL'],            'priority' => 'critical', 'target_days' => 45],
                    ['wave' => 2, 'states' => ['PA', 'OH', 'GA', 'NC', 'MI'],            'priority' => 'high', 'target_days' => 60],
                    ['wave' => 3, 'states' => ['NJ', 'AZ', 'LA', 'TN', 'WA'],           'priority' => 'medium', 'target_days' => 90],
                ]),
                'revenue_threshold' => 1500,
            ],
        ];

        $now = now();

        foreach ($profiles as $profile) {
            DB::table('strategy_profiles')->updateOrInsert(
                ['name' => $profile['name'], 'agency_id' => $profile['agency_id']],
                array_merge($profile, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }
    }
}

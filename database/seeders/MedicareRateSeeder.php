<?php

namespace Database\Seeders;

use App\Models\MedicareRate;
use Illuminate\Database\Seeder;

/**
 * Seeds the medicare_rates table with the 42 CPTs the practice
 * actively bills, for Florida, effective 2026-01-01.
 *
 * Same numbers that the data/import-medicare-fl.py script loaded into
 * fee_schedules — repeated here so the medicare_rates table is
 * self-sufficient and a fresh database boots with usable benchmark
 * data. Idempotent via the (cpt, modifier, state, locality_code,
 * year, effective_date) unique index.
 *
 * Rates are non_facility (POS 11 = office). facility_rate left null
 * for now; can be backfilled when we load the full CMS schedule.
 *
 * Run: php artisan db:seed --class=MedicareRateSeeder
 */
class MedicareRateSeeder extends Seeder
{
    public function run(): void
    {
        $year = 2026;
        $eff = '2026-01-01';
        $state = 'FL';

        $rows = [
            ['90791', 'Psychiatric Diagnostic Evaluation', 181.54],
            ['90792', 'Psychiatric Diagnostic Eval w/ Medical Services', 205.72],
            ['90832', 'Psychotherapy 30 min', 75.62],
            ['90833', 'Psychotherapy 30 min (add-on to E/M)', 68.18],
            ['90834', 'Psychotherapy 45 min', 101.30],
            ['90836', 'Psychotherapy 45 min (add-on to E/M)', 91.36],
            ['90837', 'Psychotherapy 60 min', 149.84],
            ['90838', 'Psychotherapy 60 min (add-on to E/M)', 118.52],
            ['90839', 'Psychotherapy for Crisis, first 60 min', 155.16],
            ['90840', 'Psychotherapy for Crisis, each addl 30 min', 76.36],
            ['90845', 'Psychoanalysis', 109.12],
            ['90846', 'Family Psychotherapy w/o Patient 50 min', 116.18],
            ['90847', 'Family Psychotherapy w/ Patient 50 min', 125.38],
            ['90853', 'Group Psychotherapy', 34.32],
            ['90785', 'Interactive Complexity (add-on)', 14.40],
            ['99202', 'Office Visit - New Patient Level 2', 76.36],
            ['99203', 'Office Visit - New Patient Level 3', 117.68],
            ['99204', 'Office Visit - New Patient Level 4', 176.52],
            ['99205', 'Office Visit - New Patient Level 5', 222.66],
            ['99211', 'Office Visit - Established Patient Level 1', 23.34],
            ['99212', 'Office Visit - Established Patient Level 2', 56.96],
            ['99213', 'Office Visit - Established Patient Level 3', 96.80],
            ['99214', 'Office Visit - Established Patient Level 4', 138.86],
            ['99215', 'Office Visit - Established Patient Level 5', 186.66],
            ['99354', 'Prolonged Service first 60 min (add-on)', 113.04],
            ['99417', 'Prolonged Outpatient E/M each 15 min (add-on)', 29.30],
            ['96130', 'Psychological Testing Eval first 60 min', 119.92],
            ['96131', 'Psychological Testing Eval each addl 60 min', 104.04],
            ['96136', 'Psych/Neuropsych Testing first 30 min', 69.68],
            ['96137', 'Psych/Neuropsych Testing each addl 30 min', 55.46],
            ['96156', 'Health Behavior Assessment/Intervention', 51.26],
            ['96158', 'Health Behavior Intervention first 30 min', 49.02],
            ['96159', 'Health Behavior Intervention each addl 15 min', 24.10],
            ['96127', 'Brief Emotional/Behavioral Assessment', 5.54],
            ['99441', 'Telephone E/M 5-10 min', 30.04],
            ['99442', 'Telephone E/M 11-20 min', 56.22],
            ['99443', 'Telephone E/M 21-30 min', 81.64],
            ['99457', 'Remote Physiologic Monitoring 20 min', 50.52],
            ['99458', 'Remote Physiologic Monitoring each addl 20 min', 41.32],
            ['90867', 'TMS Treatment Delivery - Initial', 167.54],
            ['90868', 'TMS Treatment Delivery - Subsequent', 87.38],
            ['90869', 'TMS Treatment Re-evaluation', 152.40],
        ];

        foreach ($rows as [$cpt, $desc, $rate]) {
            MedicareRate::updateOrCreate(
                [
                    'cpt_code' => $cpt,
                    'modifier' => null,
                    'state' => $state,
                    'locality_code' => null,
                    'year' => $year,
                    'effective_date' => $eff,
                ],
                [
                    'cpt_description' => $desc,
                    'non_facility_rate' => $rate,
                    'source' => 'manual',
                ]
            );
        }

        $this->command?->info('Seeded ' . count($rows) . " Medicare rates for {$state} {$year}.");
    }
}

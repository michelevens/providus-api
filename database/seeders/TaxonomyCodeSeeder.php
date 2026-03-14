<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TaxonomyCodeSeeder extends Seeder
{
    public function run(): void
    {
        $codes = [
            ['code' => '101Y00000X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Counselor'],
            ['code' => '101YA0400X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Counselor — Addiction'],
            ['code' => '101YM0800X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Counselor — Mental Health'],
            ['code' => '101YP1600X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Counselor — Pastoral'],
            ['code' => '101YP2500X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Counselor — Professional'],
            ['code' => '101YS0200X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Counselor — School'],
            ['code' => '103T00000X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Psychologist'],
            ['code' => '103TA0400X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Psychologist — Addiction'],
            ['code' => '103TA0700X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Psychologist — Adult Development & Aging'],
            ['code' => '103TC0700X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Psychologist — Clinical'],
            ['code' => '103TC2200X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Psychologist — Clinical Child & Adolescent'],
            ['code' => '103TF0000X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Psychologist — Family'],
            ['code' => '103TH0100X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Psychologist — Health Service'],
            ['code' => '103TP2701X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Psychologist — Prescribing'],
            ['code' => '104100000X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Social Worker'],
            ['code' => '1041C0700X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Social Worker — Clinical'],
            ['code' => '1041S0200X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Social Worker — School'],
            ['code' => '106H00000X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Marriage & Family Therapist'],
            ['code' => '163W00000X', 'type' => 'Individual', 'classification' => 'Nursing Service Providers',                    'specialty' => 'Registered Nurse'],
            ['code' => '163WP0808X', 'type' => 'Individual', 'classification' => 'Nursing Service Providers',                    'specialty' => 'Registered Nurse — Psychiatric/Mental Health'],
            ['code' => '261QM0801X', 'type' => 'Organization', 'classification' => 'Ambulatory Health Care Facilities',          'specialty' => 'Mental Health Clinic'],
            ['code' => '2084P0800X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians',          'specialty' => 'Psychiatry'],
            ['code' => '2084P0802X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians',          'specialty' => 'Psychiatry — Addiction Medicine'],
            ['code' => '2084P0804X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians',          'specialty' => 'Psychiatry — Child & Adolescent'],
            ['code' => '2084P0805X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians',          'specialty' => 'Psychiatry — Geriatric'],
            ['code' => '251S00000X', 'type' => 'Organization', 'classification' => 'Agencies',                                   'specialty' => 'Community/Behavioral Health'],
            ['code' => '320600000X', 'type' => 'Organization', 'classification' => 'Residential Treatment Facilities',           'specialty' => 'Residential Treatment — Mental Illness'],
            ['code' => '320900000X', 'type' => 'Organization', 'classification' => 'Residential Treatment Facilities',           'specialty' => 'Residential Treatment — Substance Abuse'],
            ['code' => '323P00000X', 'type' => 'Organization', 'classification' => 'Residential Treatment Facilities',           'specialty' => 'Psychiatric Residential Treatment'],
            ['code' => '3104A0630X', 'type' => 'Organization', 'classification' => 'Home Health / HCBS Waiver Providers',        'specialty' => 'Behavioral Health & Social Service (HCBS)'],
            ['code' => '364SP0808X', 'type' => 'Individual', 'classification' => 'Physician Assistants & Advanced Practice Nursing', 'specialty' => 'Clinical Nurse Specialist — Psych/MH'],
            ['code' => '363LP0808X', 'type' => 'Individual', 'classification' => 'Physician Assistants & Advanced Practice Nursing', 'specialty' => 'Nurse Practitioner — Psych/MH'],
            ['code' => '390200000X', 'type' => 'Individual', 'classification' => 'Student, Health Care',                         'specialty' => 'Student Health'],
            ['code' => '171M00000X', 'type' => 'Individual', 'classification' => 'Other Service Providers',                      'specialty' => 'Case Manager/Care Coordinator'],
            ['code' => '172V00000X', 'type' => 'Individual', 'classification' => 'Other Service Providers',                      'specialty' => 'Community Health Worker'],
            ['code' => '176B00000X', 'type' => 'Individual', 'classification' => 'Other Service Providers',                      'specialty' => 'Midlevel Practitioner'],
            ['code' => '225X00000X', 'type' => 'Individual', 'classification' => 'Respiratory, Developmental, Rehabilitative',   'specialty' => 'Occupational Therapist — Mental Health'],
        ];

        foreach ($codes as $code) {
            DB::table('taxonomy_codes')->updateOrInsert(
                ['code' => $code['code']],
                $code
            );
        }
    }
}

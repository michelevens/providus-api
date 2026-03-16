<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlaceOfServiceSeeder extends Seeder
{
    public function run(): void
    {
        $codes = [
            ['code' => '02', 'name' => 'Telehealth — Provided Other than in Patient\'s Home', 'description' => 'Synchronous telecommunications technology (real-time audio/video) at a location other than patient home', 'is_facility' => false],
            ['code' => '03', 'name' => 'School', 'description' => 'School-based health center or clinic', 'is_facility' => true],
            ['code' => '04', 'name' => 'Homeless Shelter', 'description' => 'Facility or location whose primary purpose is to provide temporary housing to homeless individuals', 'is_facility' => false],
            ['code' => '05', 'name' => 'Indian Health Service Free-Standing', 'description' => 'IHS free-standing facility', 'is_facility' => true],
            ['code' => '06', 'name' => 'Indian Health Service Provider-Based', 'description' => 'IHS provider-based facility', 'is_facility' => true],
            ['code' => '07', 'name' => 'Tribal 638 Free-Standing', 'description' => 'Tribal 638 free-standing facility', 'is_facility' => true],
            ['code' => '08', 'name' => 'Tribal 638 Provider-Based', 'description' => 'Tribal 638 provider-based facility', 'is_facility' => true],
            ['code' => '09', 'name' => 'Prison / Correctional Facility', 'description' => 'State or local correctional facility', 'is_facility' => true],
            ['code' => '10', 'name' => 'Telehealth — Provided in Patient\'s Home', 'description' => 'Synchronous telecommunications technology delivered to patient at home', 'is_facility' => false],
            ['code' => '11', 'name' => 'Office', 'description' => 'Provider\'s office or clinic (most common for outpatient visits)', 'is_facility' => false],
            ['code' => '12', 'name' => 'Home', 'description' => 'Patient\'s private residence or home', 'is_facility' => false],
            ['code' => '13', 'name' => 'Assisted Living Facility', 'description' => 'Congregate residential facility providing room, board, and personal care services', 'is_facility' => false],
            ['code' => '14', 'name' => 'Group Home', 'description' => 'Residence with shared living areas for clients with similar needs', 'is_facility' => false],
            ['code' => '15', 'name' => 'Mobile Unit', 'description' => 'Facility/unit that moves to provide preventive, screening, diagnostic, and/or treatment services', 'is_facility' => false],
            ['code' => '17', 'name' => 'Walk-in Retail Health Clinic', 'description' => 'Walk-in clinic located within a retail operation (pharmacy, supermarket)', 'is_facility' => false],
            ['code' => '19', 'name' => 'Off Campus — Outpatient Hospital', 'description' => 'Off-campus outpatient department of a hospital', 'is_facility' => true],
            ['code' => '20', 'name' => 'Urgent Care Facility', 'description' => 'Urgent care clinic providing immediate unscheduled outpatient care', 'is_facility' => false],
            ['code' => '21', 'name' => 'Inpatient Hospital', 'description' => 'Hospital inpatient setting for acute care', 'is_facility' => true],
            ['code' => '22', 'name' => 'On Campus — Outpatient Hospital', 'description' => 'Hospital-based outpatient department on campus', 'is_facility' => true],
            ['code' => '23', 'name' => 'Emergency Room — Hospital', 'description' => 'Hospital emergency department for emergency services', 'is_facility' => true],
            ['code' => '24', 'name' => 'Ambulatory Surgical Center', 'description' => 'Free-standing facility for surgical procedures not requiring overnight stay', 'is_facility' => true],
            ['code' => '25', 'name' => 'Birthing Center', 'description' => 'Facility for labor, delivery, and immediate postpartum care', 'is_facility' => true],
            ['code' => '26', 'name' => 'Military Treatment Facility', 'description' => 'Medical facility operated by one or more uniformed services', 'is_facility' => true],
            ['code' => '31', 'name' => 'Skilled Nursing Facility', 'description' => 'Facility providing inpatient skilled nursing care and rehabilitation', 'is_facility' => true],
            ['code' => '32', 'name' => 'Nursing Facility', 'description' => 'Facility providing custodial, intermediate, or skilled nursing care', 'is_facility' => true],
            ['code' => '33', 'name' => 'Custodial Care Facility', 'description' => 'Facility for non-medical custodial care', 'is_facility' => false],
            ['code' => '34', 'name' => 'Hospice', 'description' => 'Facility providing palliative and supportive care for terminally ill', 'is_facility' => true],
            ['code' => '41', 'name' => 'Ambulance — Land', 'description' => 'Ground transport ambulance vehicle', 'is_facility' => false],
            ['code' => '42', 'name' => 'Ambulance — Air or Water', 'description' => 'Air or water transport ambulance', 'is_facility' => false],
            ['code' => '49', 'name' => 'Independent Clinic', 'description' => 'Free-standing clinic providing diagnostic or therapeutic services', 'is_facility' => false],
            ['code' => '50', 'name' => 'Federally Qualified Health Center', 'description' => 'FQHC providing comprehensive primary and preventive care', 'is_facility' => false],
            ['code' => '51', 'name' => 'Inpatient Psychiatric Facility', 'description' => 'Facility providing inpatient psychiatric care', 'is_facility' => true],
            ['code' => '52', 'name' => 'Psychiatric Facility — Partial Hospitalization', 'description' => 'Partial hospitalization for psychiatric services', 'is_facility' => true],
            ['code' => '53', 'name' => 'Community Mental Health Center', 'description' => 'Community-based facility for outpatient mental health services', 'is_facility' => false],
            ['code' => '54', 'name' => 'Intermediate Care Facility / Intellectually Disabled', 'description' => 'ICF/IID for individuals with intellectual disabilities', 'is_facility' => true],
            ['code' => '55', 'name' => 'Residential Substance Abuse Treatment', 'description' => 'Residential facility for substance use disorder treatment', 'is_facility' => true],
            ['code' => '56', 'name' => 'Psychiatric Residential Treatment Center', 'description' => 'Facility for psychiatric residential treatment under age 21', 'is_facility' => true],
            ['code' => '57', 'name' => 'Non-Residential Substance Abuse Treatment', 'description' => 'Outpatient substance use disorder treatment facility', 'is_facility' => false],
            ['code' => '58', 'name' => 'Non-Residential Opioid Treatment', 'description' => 'Outpatient opioid treatment program (methadone, buprenorphine)', 'is_facility' => false],
            ['code' => '60', 'name' => 'Mass Immunization Center', 'description' => 'Facility for mass immunization services', 'is_facility' => false],
            ['code' => '61', 'name' => 'Comprehensive Inpatient Rehabilitation', 'description' => 'Inpatient rehabilitation hospital or unit', 'is_facility' => true],
            ['code' => '62', 'name' => 'Comprehensive Outpatient Rehabilitation', 'description' => 'Outpatient rehabilitation facility', 'is_facility' => false],
            ['code' => '65', 'name' => 'End-Stage Renal Disease Treatment', 'description' => 'Dialysis facility for ESRD treatment', 'is_facility' => true],
            ['code' => '71', 'name' => 'Public Health Clinic', 'description' => 'State or local public health clinic', 'is_facility' => false],
            ['code' => '72', 'name' => 'Rural Health Clinic', 'description' => 'CMS-certified rural health clinic', 'is_facility' => false],
            ['code' => '81', 'name' => 'Independent Laboratory', 'description' => 'Independent clinical laboratory', 'is_facility' => false],
            ['code' => '99', 'name' => 'Other Place of Service', 'description' => 'Other unlisted place of service', 'is_facility' => false],
        ];

        foreach ($codes as $code) {
            DB::table('place_of_service_codes')->updateOrInsert(
                ['code' => $code['code']],
                array_merge($code, ['is_active' => true])
            );
        }
    }
}

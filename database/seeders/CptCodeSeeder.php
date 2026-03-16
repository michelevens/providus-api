<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CptCodeSeeder extends Seeder
{
    public function run(): void
    {
        $codes = [
            // ── BEHAVIORAL HEALTH — Psychiatric Evaluation ──
            ['code' => '90791', 'short_description' => 'Psychiatric Diagnostic Evaluation',           'category' => 'evaluation',     'specialty_group' => 'behavioral_health', 'avg_medicare_rate' => 172.00, 'time_unit' => 'minutes', 'typical_minutes' => 60, 'telehealth_eligible' => true],
            ['code' => '90792', 'short_description' => 'Psych Diagnostic Eval with Medical Services', 'category' => 'evaluation',     'specialty_group' => 'behavioral_health', 'avg_medicare_rate' => 185.00, 'time_unit' => 'minutes', 'typical_minutes' => 60, 'telehealth_eligible' => true],

            // ── BEHAVIORAL HEALTH — Psychotherapy ──
            ['code' => '90832', 'short_description' => 'Psychotherapy, 30 min',                      'category' => 'psychotherapy',  'specialty_group' => 'behavioral_health', 'avg_medicare_rate' => 68.00,  'time_unit' => 'minutes', 'typical_minutes' => 30, 'telehealth_eligible' => true],
            ['code' => '90834', 'short_description' => 'Psychotherapy, 45 min',                      'category' => 'psychotherapy',  'specialty_group' => 'behavioral_health', 'avg_medicare_rate' => 102.00, 'time_unit' => 'minutes', 'typical_minutes' => 45, 'telehealth_eligible' => true],
            ['code' => '90837', 'short_description' => 'Psychotherapy, 60 min',                      'category' => 'psychotherapy',  'specialty_group' => 'behavioral_health', 'avg_medicare_rate' => 135.00, 'time_unit' => 'minutes', 'typical_minutes' => 60, 'telehealth_eligible' => true],
            ['code' => '90838', 'short_description' => 'Psychotherapy for Crisis, 60+ min',          'category' => 'psychotherapy',  'specialty_group' => 'behavioral_health', 'avg_medicare_rate' => 145.00, 'time_unit' => 'minutes', 'typical_minutes' => 60, 'telehealth_eligible' => true],
            ['code' => '90839', 'short_description' => 'Psychotherapy for Crisis, first 60 min',     'category' => 'psychotherapy',  'specialty_group' => 'behavioral_health', 'avg_medicare_rate' => 150.00, 'time_unit' => 'minutes', 'typical_minutes' => 60, 'telehealth_eligible' => true],
            ['code' => '90840', 'short_description' => 'Psychotherapy for Crisis, each addl 30 min', 'category' => 'psychotherapy',  'specialty_group' => 'behavioral_health', 'avg_medicare_rate' => 75.00,  'time_unit' => 'minutes', 'typical_minutes' => 30, 'telehealth_eligible' => true],
            ['code' => '90845', 'short_description' => 'Psychoanalysis',                             'category' => 'psychotherapy',  'specialty_group' => 'behavioral_health', 'avg_medicare_rate' => 112.00, 'time_unit' => 'minutes', 'typical_minutes' => 50, 'telehealth_eligible' => true],
            ['code' => '90846', 'short_description' => 'Family Psychotherapy without Patient',       'category' => 'psychotherapy',  'specialty_group' => 'behavioral_health', 'avg_medicare_rate' => 110.00, 'time_unit' => 'minutes', 'typical_minutes' => 50, 'telehealth_eligible' => true],
            ['code' => '90847', 'short_description' => 'Family Psychotherapy with Patient',          'category' => 'psychotherapy',  'specialty_group' => 'behavioral_health', 'avg_medicare_rate' => 115.00, 'time_unit' => 'minutes', 'typical_minutes' => 50, 'telehealth_eligible' => true],
            ['code' => '90849', 'short_description' => 'Multiple-Family Group Psychotherapy',        'category' => 'psychotherapy',  'specialty_group' => 'behavioral_health', 'avg_medicare_rate' => 38.00,  'time_unit' => 'minutes', 'typical_minutes' => 90, 'telehealth_eligible' => true],
            ['code' => '90853', 'short_description' => 'Group Psychotherapy',                        'category' => 'psychotherapy',  'specialty_group' => 'behavioral_health', 'avg_medicare_rate' => 32.00,  'time_unit' => 'minutes', 'typical_minutes' => 90, 'telehealth_eligible' => true],

            // ── BEHAVIORAL HEALTH — Medication Management ──
            ['code' => '90833', 'short_description' => 'Psychotherapy Add-On, 30 min (with E/M)',    'category' => 'psychotherapy',  'specialty_group' => 'behavioral_health', 'avg_medicare_rate' => 55.00,  'time_unit' => 'minutes', 'typical_minutes' => 30, 'telehealth_eligible' => true],
            ['code' => '90836', 'short_description' => 'Psychotherapy Add-On, 45 min (with E/M)',    'category' => 'psychotherapy',  'specialty_group' => 'behavioral_health', 'avg_medicare_rate' => 82.00,  'time_unit' => 'minutes', 'typical_minutes' => 45, 'telehealth_eligible' => true],
            ['code' => '90838', 'short_description' => 'Psychotherapy Add-On, 60 min (with E/M)',    'category' => 'psychotherapy',  'specialty_group' => 'behavioral_health', 'avg_medicare_rate' => 110.00, 'time_unit' => 'minutes', 'typical_minutes' => 60, 'telehealth_eligible' => true],

            // ── BEHAVIORAL HEALTH — Psychological Testing ──
            ['code' => '96130', 'short_description' => 'Psych Testing Eval by Professional, first hr', 'category' => 'testing',     'specialty_group' => 'behavioral_health', 'avg_medicare_rate' => 108.00, 'time_unit' => 'minutes', 'typical_minutes' => 60, 'telehealth_eligible' => true],
            ['code' => '96131', 'short_description' => 'Psych Testing Eval by Professional, addl hr',  'category' => 'testing',     'specialty_group' => 'behavioral_health', 'avg_medicare_rate' => 105.00, 'time_unit' => 'minutes', 'typical_minutes' => 60, 'telehealth_eligible' => true],
            ['code' => '96136', 'short_description' => 'Psych Test Admin by Professional, first 30m',  'category' => 'testing',     'specialty_group' => 'behavioral_health', 'avg_medicare_rate' => 55.00,  'time_unit' => 'minutes', 'typical_minutes' => 30, 'telehealth_eligible' => true],
            ['code' => '96137', 'short_description' => 'Psych Test Admin by Professional, addl 30m',   'category' => 'testing',     'specialty_group' => 'behavioral_health', 'avg_medicare_rate' => 48.00,  'time_unit' => 'minutes', 'typical_minutes' => 30, 'telehealth_eligible' => true],

            // ── BEHAVIORAL HEALTH — Substance Abuse ──
            ['code' => '99408', 'short_description' => 'Alcohol/Substance Screening, 15-30 min',     'category' => 'screening',     'specialty_group' => 'behavioral_health', 'avg_medicare_rate' => 35.00,  'time_unit' => 'minutes', 'typical_minutes' => 20, 'telehealth_eligible' => true],
            ['code' => '99409', 'short_description' => 'Alcohol/Substance Screening, 30+ min',       'category' => 'screening',     'specialty_group' => 'behavioral_health', 'avg_medicare_rate' => 65.00,  'time_unit' => 'minutes', 'typical_minutes' => 35, 'telehealth_eligible' => true],

            // ── BEHAVIORAL HEALTH — Applied Behavior Analysis ──
            ['code' => '97151', 'short_description' => 'ABA Assessment by Qualified Professional',   'category' => 'aba',           'specialty_group' => 'behavioral_health', 'avg_medicare_rate' => 72.00,  'time_unit' => 'units',   'typical_minutes' => 15, 'telehealth_eligible' => true],
            ['code' => '97153', 'short_description' => 'ABA Treatment by Technician',                'category' => 'aba',           'specialty_group' => 'behavioral_health', 'avg_medicare_rate' => 18.00,  'time_unit' => 'units',   'typical_minutes' => 15, 'telehealth_eligible' => false],
            ['code' => '97155', 'short_description' => 'ABA Treatment by Qualified Professional',    'category' => 'aba',           'specialty_group' => 'behavioral_health', 'avg_medicare_rate' => 42.00,  'time_unit' => 'units',   'typical_minutes' => 15, 'telehealth_eligible' => true],

            // ── E/M — Office/Outpatient (New Patient) ──
            ['code' => '99202', 'short_description' => 'Office Visit, New Patient, Level 2',         'category' => 'em_visit',      'specialty_group' => 'primary_care',      'avg_medicare_rate' => 75.00,  'time_unit' => 'minutes', 'typical_minutes' => 15, 'telehealth_eligible' => true],
            ['code' => '99203', 'short_description' => 'Office Visit, New Patient, Level 3',         'category' => 'em_visit',      'specialty_group' => 'primary_care',      'avg_medicare_rate' => 112.00, 'time_unit' => 'minutes', 'typical_minutes' => 30, 'telehealth_eligible' => true],
            ['code' => '99204', 'short_description' => 'Office Visit, New Patient, Level 4',         'category' => 'em_visit',      'specialty_group' => 'primary_care',      'avg_medicare_rate' => 167.00, 'time_unit' => 'minutes', 'typical_minutes' => 45, 'telehealth_eligible' => true],
            ['code' => '99205', 'short_description' => 'Office Visit, New Patient, Level 5',         'category' => 'em_visit',      'specialty_group' => 'primary_care',      'avg_medicare_rate' => 211.00, 'time_unit' => 'minutes', 'typical_minutes' => 60, 'telehealth_eligible' => true],

            // ── E/M — Office/Outpatient (Established Patient) ──
            ['code' => '99211', 'short_description' => 'Office Visit, Established, Level 1',         'category' => 'em_visit',      'specialty_group' => 'primary_care',      'avg_medicare_rate' => 24.00,  'time_unit' => 'minutes', 'typical_minutes' => 5,  'telehealth_eligible' => true],
            ['code' => '99212', 'short_description' => 'Office Visit, Established, Level 2',         'category' => 'em_visit',      'specialty_group' => 'primary_care',      'avg_medicare_rate' => 56.00,  'time_unit' => 'minutes', 'typical_minutes' => 10, 'telehealth_eligible' => true],
            ['code' => '99213', 'short_description' => 'Office Visit, Established, Level 3',         'category' => 'em_visit',      'specialty_group' => 'primary_care',      'avg_medicare_rate' => 93.00,  'time_unit' => 'minutes', 'typical_minutes' => 15, 'telehealth_eligible' => true],
            ['code' => '99214', 'short_description' => 'Office Visit, Established, Level 4',         'category' => 'em_visit',      'specialty_group' => 'primary_care',      'avg_medicare_rate' => 130.00, 'time_unit' => 'minutes', 'typical_minutes' => 25, 'telehealth_eligible' => true],
            ['code' => '99215', 'short_description' => 'Office Visit, Established, Level 5',         'category' => 'em_visit',      'specialty_group' => 'primary_care',      'avg_medicare_rate' => 176.00, 'time_unit' => 'minutes', 'typical_minutes' => 40, 'telehealth_eligible' => true],

            // ── E/M — Hospital Inpatient ──
            ['code' => '99221', 'short_description' => 'Initial Hospital Inpatient, Level 1',        'category' => 'em_visit',      'specialty_group' => 'hospital',          'avg_medicare_rate' => 115.00, 'time_unit' => 'minutes', 'typical_minutes' => 40, 'telehealth_eligible' => false],
            ['code' => '99222', 'short_description' => 'Initial Hospital Inpatient, Level 2',        'category' => 'em_visit',      'specialty_group' => 'hospital',          'avg_medicare_rate' => 157.00, 'time_unit' => 'minutes', 'typical_minutes' => 55, 'telehealth_eligible' => false],
            ['code' => '99223', 'short_description' => 'Initial Hospital Inpatient, Level 3',        'category' => 'em_visit',      'specialty_group' => 'hospital',          'avg_medicare_rate' => 210.00, 'time_unit' => 'minutes', 'typical_minutes' => 70, 'telehealth_eligible' => false],
            ['code' => '99231', 'short_description' => 'Subsequent Hospital Visit, Level 1',         'category' => 'em_visit',      'specialty_group' => 'hospital',          'avg_medicare_rate' => 48.00,  'time_unit' => 'minutes', 'typical_minutes' => 15, 'telehealth_eligible' => false],
            ['code' => '99232', 'short_description' => 'Subsequent Hospital Visit, Level 2',         'category' => 'em_visit',      'specialty_group' => 'hospital',          'avg_medicare_rate' => 85.00,  'time_unit' => 'minutes', 'typical_minutes' => 25, 'telehealth_eligible' => false],
            ['code' => '99233', 'short_description' => 'Subsequent Hospital Visit, Level 3',         'category' => 'em_visit',      'specialty_group' => 'hospital',          'avg_medicare_rate' => 122.00, 'time_unit' => 'minutes', 'typical_minutes' => 35, 'telehealth_eligible' => false],

            // ── E/M — Emergency Department ──
            ['code' => '99281', 'short_description' => 'Emergency Dept Visit, Level 1',              'category' => 'em_visit',      'specialty_group' => 'emergency',         'avg_medicare_rate' => 22.00,  'time_unit' => 'minutes', 'typical_minutes' => 10, 'telehealth_eligible' => false],
            ['code' => '99282', 'short_description' => 'Emergency Dept Visit, Level 2',              'category' => 'em_visit',      'specialty_group' => 'emergency',         'avg_medicare_rate' => 43.00,  'time_unit' => 'minutes', 'typical_minutes' => 15, 'telehealth_eligible' => false],
            ['code' => '99283', 'short_description' => 'Emergency Dept Visit, Level 3',              'category' => 'em_visit',      'specialty_group' => 'emergency',         'avg_medicare_rate' => 75.00,  'time_unit' => 'minutes', 'typical_minutes' => 25, 'telehealth_eligible' => false],
            ['code' => '99284', 'short_description' => 'Emergency Dept Visit, Level 4',              'category' => 'em_visit',      'specialty_group' => 'emergency',         'avg_medicare_rate' => 135.00, 'time_unit' => 'minutes', 'typical_minutes' => 40, 'telehealth_eligible' => false],
            ['code' => '99285', 'short_description' => 'Emergency Dept Visit, Level 5',              'category' => 'em_visit',      'specialty_group' => 'emergency',         'avg_medicare_rate' => 205.00, 'time_unit' => 'minutes', 'typical_minutes' => 60, 'telehealth_eligible' => false],

            // ── E/M — Consultations ──
            ['code' => '99242', 'short_description' => 'Office Consultation, Level 2',               'category' => 'consultation',  'specialty_group' => 'primary_care',      'avg_medicare_rate' => 98.00,  'time_unit' => 'minutes', 'typical_minutes' => 20, 'telehealth_eligible' => true],
            ['code' => '99243', 'short_description' => 'Office Consultation, Level 3',               'category' => 'consultation',  'specialty_group' => 'primary_care',      'avg_medicare_rate' => 140.00, 'time_unit' => 'minutes', 'typical_minutes' => 30, 'telehealth_eligible' => true],
            ['code' => '99244', 'short_description' => 'Office Consultation, Level 4',               'category' => 'consultation',  'specialty_group' => 'primary_care',      'avg_medicare_rate' => 200.00, 'time_unit' => 'minutes', 'typical_minutes' => 40, 'telehealth_eligible' => true],
            ['code' => '99245', 'short_description' => 'Office Consultation, Level 5',               'category' => 'consultation',  'specialty_group' => 'primary_care',      'avg_medicare_rate' => 255.00, 'time_unit' => 'minutes', 'typical_minutes' => 55, 'telehealth_eligible' => true],

            // ── PREVENTIVE MEDICINE ──
            ['code' => '99381', 'short_description' => 'Preventive Visit, New, Infant (< 1 yr)',     'category' => 'preventive',    'specialty_group' => 'primary_care',      'avg_medicare_rate' => 95.00,  'time_unit' => 'minutes', 'typical_minutes' => 30, 'telehealth_eligible' => false],
            ['code' => '99385', 'short_description' => 'Preventive Visit, New, 18-39 yr',            'category' => 'preventive',    'specialty_group' => 'primary_care',      'avg_medicare_rate' => 115.00, 'time_unit' => 'minutes', 'typical_minutes' => 30, 'telehealth_eligible' => false],
            ['code' => '99386', 'short_description' => 'Preventive Visit, New, 40-64 yr',            'category' => 'preventive',    'specialty_group' => 'primary_care',      'avg_medicare_rate' => 130.00, 'time_unit' => 'minutes', 'typical_minutes' => 40, 'telehealth_eligible' => false],
            ['code' => '99387', 'short_description' => 'Preventive Visit, New, 65+ yr',              'category' => 'preventive',    'specialty_group' => 'primary_care',      'avg_medicare_rate' => 140.00, 'time_unit' => 'minutes', 'typical_minutes' => 40, 'telehealth_eligible' => false],
            ['code' => '99395', 'short_description' => 'Preventive Visit, Established, 18-39 yr',    'category' => 'preventive',    'specialty_group' => 'primary_care',      'avg_medicare_rate' => 100.00, 'time_unit' => 'minutes', 'typical_minutes' => 25, 'telehealth_eligible' => false],
            ['code' => '99396', 'short_description' => 'Preventive Visit, Established, 40-64 yr',    'category' => 'preventive',    'specialty_group' => 'primary_care',      'avg_medicare_rate' => 110.00, 'time_unit' => 'minutes', 'typical_minutes' => 30, 'telehealth_eligible' => false],
            ['code' => '99397', 'short_description' => 'Preventive Visit, Established, 65+ yr',      'category' => 'preventive',    'specialty_group' => 'primary_care',      'avg_medicare_rate' => 120.00, 'time_unit' => 'minutes', 'typical_minutes' => 35, 'telehealth_eligible' => false],

            // ── CARE MANAGEMENT ──
            ['code' => '99490', 'short_description' => 'Chronic Care Management, first 20 min/mo',   'category' => 'care_management', 'specialty_group' => 'primary_care',   'avg_medicare_rate' => 42.00,  'time_unit' => 'minutes', 'typical_minutes' => 20, 'telehealth_eligible' => true],
            ['code' => '99491', 'short_description' => 'Chronic Care Mgmt by Physician, 30 min/mo',  'category' => 'care_management', 'specialty_group' => 'primary_care',   'avg_medicare_rate' => 83.00,  'time_unit' => 'minutes', 'typical_minutes' => 30, 'telehealth_eligible' => true],
            ['code' => '99457', 'short_description' => 'Remote Physiologic Monitoring, first 20 min', 'category' => 'care_management', 'specialty_group' => 'primary_care',  'avg_medicare_rate' => 50.00,  'time_unit' => 'minutes', 'typical_minutes' => 20, 'telehealth_eligible' => true],
            ['code' => '99458', 'short_description' => 'Remote Physiologic Monitoring, addl 20 min', 'category' => 'care_management', 'specialty_group' => 'primary_care',   'avg_medicare_rate' => 38.00,  'time_unit' => 'minutes', 'typical_minutes' => 20, 'telehealth_eligible' => true],

            // ── SURGERY — General ──
            ['code' => '10021', 'short_description' => 'Fine Needle Aspiration, without imaging',    'category' => 'surgical',      'specialty_group' => 'surgery',           'avg_medicare_rate' => 72.00,  'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => '10060', 'short_description' => 'Incision & Drainage, Abscess — Simple',     'category' => 'surgical',      'specialty_group' => 'surgery',           'avg_medicare_rate' => 122.00, 'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => '10120', 'short_description' => 'Incision, Removal Foreign Body — Simple',    'category' => 'surgical',      'specialty_group' => 'surgery',           'avg_medicare_rate' => 110.00, 'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => '11102', 'short_description' => 'Tangential Biopsy, Skin, Single Lesion',     'category' => 'surgical',      'specialty_group' => 'dermatology',       'avg_medicare_rate' => 82.00,  'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => '11104', 'short_description' => 'Punch Biopsy, Skin, Single Lesion',          'category' => 'surgical',      'specialty_group' => 'dermatology',       'avg_medicare_rate' => 90.00,  'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],

            // ── ORTHOPEDIC ──
            ['code' => '20610', 'short_description' => 'Arthrocentesis, Major Joint (Knee, Shoulder)', 'category' => 'surgical',    'specialty_group' => 'orthopedics',       'avg_medicare_rate' => 67.00,  'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => '27447', 'short_description' => 'Total Knee Arthroplasty',                    'category' => 'surgical',      'specialty_group' => 'orthopedics',       'avg_medicare_rate' => 1380.00,'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => '27130', 'short_description' => 'Total Hip Arthroplasty',                     'category' => 'surgical',      'specialty_group' => 'orthopedics',       'avg_medicare_rate' => 1410.00,'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => '29881', 'short_description' => 'Knee Arthroscopy, Meniscectomy',             'category' => 'surgical',      'specialty_group' => 'orthopedics',       'avg_medicare_rate' => 490.00, 'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => '23472', 'short_description' => 'Total Shoulder Arthroplasty',                'category' => 'surgical',      'specialty_group' => 'orthopedics',       'avg_medicare_rate' => 1250.00,'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],

            // ── CARDIOLOGY ──
            ['code' => '93000', 'short_description' => 'Electrocardiogram (ECG/EKG), 12-Lead',       'category' => 'diagnostic',    'specialty_group' => 'cardiology',        'avg_medicare_rate' => 17.00,  'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => '93306', 'short_description' => 'Echocardiography, Transthoracic, Complete',  'category' => 'diagnostic',    'specialty_group' => 'cardiology',        'avg_medicare_rate' => 130.00, 'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => '93458', 'short_description' => 'Left Heart Catheterization',                 'category' => 'surgical',      'specialty_group' => 'cardiology',        'avg_medicare_rate' => 350.00, 'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => '92928', 'short_description' => 'Percutaneous Coronary Stent, Single Vessel', 'category' => 'surgical',      'specialty_group' => 'cardiology',        'avg_medicare_rate' => 780.00, 'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => '93350', 'short_description' => 'Stress Echocardiography',                    'category' => 'diagnostic',    'specialty_group' => 'cardiology',        'avg_medicare_rate' => 85.00,  'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],

            // ── GASTROENTEROLOGY ──
            ['code' => '43239', 'short_description' => 'Upper GI Endoscopy with Biopsy',            'category' => 'surgical',      'specialty_group' => 'gastroenterology',  'avg_medicare_rate' => 255.00, 'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => '45378', 'short_description' => 'Colonoscopy, Diagnostic',                    'category' => 'surgical',      'specialty_group' => 'gastroenterology',  'avg_medicare_rate' => 268.00, 'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => '45380', 'short_description' => 'Colonoscopy with Biopsy',                    'category' => 'surgical',      'specialty_group' => 'gastroenterology',  'avg_medicare_rate' => 305.00, 'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => '45385', 'short_description' => 'Colonoscopy with Polyp Removal',             'category' => 'surgical',      'specialty_group' => 'gastroenterology',  'avg_medicare_rate' => 330.00, 'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],

            // ── RADIOLOGY ──
            ['code' => '71046', 'short_description' => 'Chest X-Ray, 2 Views',                      'category' => 'radiology',     'specialty_group' => 'radiology',         'avg_medicare_rate' => 24.00,  'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => '70553', 'short_description' => 'Brain MRI without & with Contrast',          'category' => 'radiology',     'specialty_group' => 'radiology',         'avg_medicare_rate' => 195.00, 'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => '74177', 'short_description' => 'CT Abdomen & Pelvis with Contrast',          'category' => 'radiology',     'specialty_group' => 'radiology',         'avg_medicare_rate' => 145.00, 'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => '73721', 'short_description' => 'MRI Joint Lower Extremity without Contrast', 'category' => 'radiology',     'specialty_group' => 'radiology',         'avg_medicare_rate' => 155.00, 'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => '77067', 'short_description' => 'Screening Mammography, Bilateral',           'category' => 'radiology',     'specialty_group' => 'radiology',         'avg_medicare_rate' => 72.00,  'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => '76856', 'short_description' => 'Ultrasound, Pelvic, Non-OB, Complete',       'category' => 'radiology',     'specialty_group' => 'radiology',         'avg_medicare_rate' => 68.00,  'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],

            // ── LABORATORY ──
            ['code' => '80053', 'short_description' => 'Comprehensive Metabolic Panel',              'category' => 'laboratory',    'specialty_group' => 'laboratory',        'avg_medicare_rate' => 14.00,  'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => '85025', 'short_description' => 'Complete Blood Count (CBC) with Diff',       'category' => 'laboratory',    'specialty_group' => 'laboratory',        'avg_medicare_rate' => 10.00,  'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => '84443', 'short_description' => 'Thyroid Stimulating Hormone (TSH)',          'category' => 'laboratory',    'specialty_group' => 'laboratory',        'avg_medicare_rate' => 20.00,  'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => '83036', 'short_description' => 'Hemoglobin A1C (Glycated Hemoglobin)',       'category' => 'laboratory',    'specialty_group' => 'laboratory',        'avg_medicare_rate' => 13.00,  'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => '80061', 'short_description' => 'Lipid Panel',                                'category' => 'laboratory',    'specialty_group' => 'laboratory',        'avg_medicare_rate' => 18.00,  'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => '81001', 'short_description' => 'Urinalysis, Automated with Microscopy',      'category' => 'laboratory',    'specialty_group' => 'laboratory',        'avg_medicare_rate' => 4.00,   'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],

            // ── OB/GYN ──
            ['code' => '59400', 'short_description' => 'Obstetric Care, Vaginal Delivery (Global)',  'category' => 'obstetrics',    'specialty_group' => 'ob_gyn',            'avg_medicare_rate' => 2100.00,'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => '59510', 'short_description' => 'Cesarean Delivery (Global)',                 'category' => 'obstetrics',    'specialty_group' => 'ob_gyn',            'avg_medicare_rate' => 2400.00,'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => '58558', 'short_description' => 'Hysteroscopy with Biopsy',                   'category' => 'surgical',      'specialty_group' => 'ob_gyn',            'avg_medicare_rate' => 310.00, 'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => '58661', 'short_description' => 'Laparoscopy with Removal of Adnexal Structure', 'category' => 'surgical',   'specialty_group' => 'ob_gyn',            'avg_medicare_rate' => 520.00, 'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],

            // ── OPHTHALMOLOGY ──
            ['code' => '92014', 'short_description' => 'Ophthalmological Exam, Comprehensive',       'category' => 'evaluation',    'specialty_group' => 'ophthalmology',     'avg_medicare_rate' => 95.00,  'time_unit' => 'minutes', 'typical_minutes' => 25, 'telehealth_eligible' => false],
            ['code' => '66984', 'short_description' => 'Cataract Surgery, Phacoemulsification',      'category' => 'surgical',      'specialty_group' => 'ophthalmology',     'avg_medicare_rate' => 580.00, 'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => '92134', 'short_description' => 'Retinal OCT (Optical Coherence Tomography)', 'category' => 'diagnostic',    'specialty_group' => 'ophthalmology',     'avg_medicare_rate' => 32.00,  'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],

            // ── DERMATOLOGY ──
            ['code' => '17000', 'short_description' => 'Destruction, Premalignant Lesion, First',    'category' => 'surgical',      'specialty_group' => 'dermatology',       'avg_medicare_rate' => 55.00,  'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => '17110', 'short_description' => 'Destruction, Benign Lesions, up to 14',      'category' => 'surgical',      'specialty_group' => 'dermatology',       'avg_medicare_rate' => 72.00,  'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],

            // ── PHYSICAL THERAPY ──
            ['code' => '97110', 'short_description' => 'Therapeutic Exercise, each 15 min',          'category' => 'therapy',       'specialty_group' => 'physical_therapy',  'avg_medicare_rate' => 33.00,  'time_unit' => 'units',   'typical_minutes' => 15, 'telehealth_eligible' => false],
            ['code' => '97112', 'short_description' => 'Neuromuscular Re-Education, each 15 min',    'category' => 'therapy',       'specialty_group' => 'physical_therapy',  'avg_medicare_rate' => 33.00,  'time_unit' => 'units',   'typical_minutes' => 15, 'telehealth_eligible' => false],
            ['code' => '97140', 'short_description' => 'Manual Therapy, each 15 min',                'category' => 'therapy',       'specialty_group' => 'physical_therapy',  'avg_medicare_rate' => 30.00,  'time_unit' => 'units',   'typical_minutes' => 15, 'telehealth_eligible' => false],
            ['code' => '97161', 'short_description' => 'PT Evaluation, Low Complexity',              'category' => 'evaluation',    'specialty_group' => 'physical_therapy',  'avg_medicare_rate' => 86.00,  'time_unit' => 'minutes', 'typical_minutes' => 20, 'telehealth_eligible' => true],
            ['code' => '97162', 'short_description' => 'PT Evaluation, Moderate Complexity',         'category' => 'evaluation',    'specialty_group' => 'physical_therapy',  'avg_medicare_rate' => 86.00,  'time_unit' => 'minutes', 'typical_minutes' => 30, 'telehealth_eligible' => true],
            ['code' => '97163', 'short_description' => 'PT Evaluation, High Complexity',             'category' => 'evaluation',    'specialty_group' => 'physical_therapy',  'avg_medicare_rate' => 86.00,  'time_unit' => 'minutes', 'typical_minutes' => 45, 'telehealth_eligible' => true],
            ['code' => '97530', 'short_description' => 'Therapeutic Activities, each 15 min',        'category' => 'therapy',       'specialty_group' => 'physical_therapy',  'avg_medicare_rate' => 34.00,  'time_unit' => 'units',   'typical_minutes' => 15, 'telehealth_eligible' => false],

            // ── OCCUPATIONAL THERAPY ──
            ['code' => '97165', 'short_description' => 'OT Evaluation, Low Complexity',              'category' => 'evaluation',    'specialty_group' => 'occupational_therapy', 'avg_medicare_rate' => 86.00, 'time_unit' => 'minutes', 'typical_minutes' => 20, 'telehealth_eligible' => true],
            ['code' => '97166', 'short_description' => 'OT Evaluation, Moderate Complexity',         'category' => 'evaluation',    'specialty_group' => 'occupational_therapy', 'avg_medicare_rate' => 86.00, 'time_unit' => 'minutes', 'typical_minutes' => 30, 'telehealth_eligible' => true],
            ['code' => '97167', 'short_description' => 'OT Evaluation, High Complexity',             'category' => 'evaluation',    'specialty_group' => 'occupational_therapy', 'avg_medicare_rate' => 86.00, 'time_unit' => 'minutes', 'typical_minutes' => 45, 'telehealth_eligible' => true],

            // ── SPEECH-LANGUAGE PATHOLOGY ──
            ['code' => '92507', 'short_description' => 'Speech/Language Treatment, Individual',      'category' => 'therapy',       'specialty_group' => 'speech_therapy',    'avg_medicare_rate' => 60.00,  'time_unit' => 'minutes', 'typical_minutes' => 30, 'telehealth_eligible' => true],
            ['code' => '92521', 'short_description' => 'Evaluation of Speech Fluency',               'category' => 'evaluation',    'specialty_group' => 'speech_therapy',    'avg_medicare_rate' => 90.00,  'time_unit' => 'minutes', 'typical_minutes' => 45, 'telehealth_eligible' => true],
            ['code' => '92523', 'short_description' => 'Eval of Speech Sound Production & Language', 'category' => 'evaluation',    'specialty_group' => 'speech_therapy',    'avg_medicare_rate' => 120.00, 'time_unit' => 'minutes', 'typical_minutes' => 60, 'telehealth_eligible' => true],

            // ── ANESTHESIA ──
            ['code' => '00100', 'short_description' => 'Anesthesia, Salivary Glands',               'category' => 'anesthesia',    'specialty_group' => 'anesthesiology',    'avg_medicare_rate' => null,   'time_unit' => 'units',   'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => '00142', 'short_description' => 'Anesthesia, Lens Surgery',                   'category' => 'anesthesia',    'specialty_group' => 'anesthesiology',    'avg_medicare_rate' => null,   'time_unit' => 'units',   'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => '00300', 'short_description' => 'Anesthesia, Head/Neck/Upper Back',           'category' => 'anesthesia',    'specialty_group' => 'anesthesiology',    'avg_medicare_rate' => null,   'time_unit' => 'units',   'typical_minutes' => null, 'telehealth_eligible' => false],

            // ── DENTAL ──
            ['code' => 'D0120', 'short_description' => 'Periodic Oral Evaluation',                   'category' => 'dental',        'specialty_group' => 'dental',            'avg_medicare_rate' => null,   'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => 'D0150', 'short_description' => 'Comprehensive Oral Evaluation, New Patient', 'category' => 'dental',        'specialty_group' => 'dental',            'avg_medicare_rate' => null,   'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => 'D1110', 'short_description' => 'Prophylaxis, Adult',                         'category' => 'dental',        'specialty_group' => 'dental',            'avg_medicare_rate' => null,   'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => 'D2740', 'short_description' => 'Crown — Porcelain/Ceramic',                  'category' => 'dental',        'specialty_group' => 'dental',            'avg_medicare_rate' => null,   'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => 'D7140', 'short_description' => 'Extraction, Erupted Tooth',                  'category' => 'dental',        'specialty_group' => 'dental',            'avg_medicare_rate' => null,   'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => 'D7210', 'short_description' => 'Surgical Extraction with Bone Removal',      'category' => 'dental',        'specialty_group' => 'dental',            'avg_medicare_rate' => null,   'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],

            // ── CHIROPRACTIC ──
            ['code' => '98940', 'short_description' => 'Chiropractic Manipulation, 1-2 Regions',     'category' => 'therapy',       'specialty_group' => 'chiropractic',      'avg_medicare_rate' => 28.00,  'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => '98941', 'short_description' => 'Chiropractic Manipulation, 3-4 Regions',     'category' => 'therapy',       'specialty_group' => 'chiropractic',      'avg_medicare_rate' => 38.00,  'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],
            ['code' => '98942', 'short_description' => 'Chiropractic Manipulation, 5 Regions',       'category' => 'therapy',       'specialty_group' => 'chiropractic',      'avg_medicare_rate' => 48.00,  'time_unit' => null,      'typical_minutes' => null, 'telehealth_eligible' => false],

            // ── NUTRITION / DIETETICS ──
            ['code' => '97802', 'short_description' => 'Medical Nutrition Therapy, Initial, 15 min', 'category' => 'therapy',       'specialty_group' => 'nutrition',         'avg_medicare_rate' => 28.00,  'time_unit' => 'units',   'typical_minutes' => 15, 'telehealth_eligible' => true],
            ['code' => '97803', 'short_description' => 'Medical Nutrition Therapy, Re-assess, 15 min','category' => 'therapy',      'specialty_group' => 'nutrition',         'avg_medicare_rate' => 25.00,  'time_unit' => 'units',   'typical_minutes' => 15, 'telehealth_eligible' => true],
        ];

        foreach ($codes as $code) {
            DB::table('cpt_codes')->updateOrInsert(
                ['code' => $code['code']],
                array_merge($code, ['is_active' => true])
            );
        }
    }
}

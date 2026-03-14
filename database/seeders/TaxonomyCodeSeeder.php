<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TaxonomyCodeSeeder extends Seeder
{
    /**
     * Seed the taxonomy_codes table with behavioral health taxonomy codes.
     */
    public function run(): void
    {
        $codes = [
            ['code' => '101Y00000X', 'classification' => 'Behavioral Health & Social Service Providers', 'specialization' => 'Counselor',                             'description' => 'A provider who is trained and educated in the performance of behavior health services through interpersonal communications, analyses, guidance, and related methods.'],
            ['code' => '101YA0400X', 'classification' => 'Behavioral Health & Social Service Providers', 'specialization' => 'Counselor — Addiction (Substance Use Disorder)', 'description' => 'A counselor with specialized training in the treatment of alcohol and drug abuse, addiction, and substance use disorders.'],
            ['code' => '101YM0800X', 'classification' => 'Behavioral Health & Social Service Providers', 'specialization' => 'Counselor — Mental Health',              'description' => 'A counselor specializing in the diagnosis and treatment of mental and emotional disorders within a counseling relationship.'],
            ['code' => '101YP1600X', 'classification' => 'Behavioral Health & Social Service Providers', 'specialization' => 'Counselor — Pastoral',                   'description' => 'A counselor who utilizes religious and/or spiritual references and resources to facilitate healing and growth within a counseling relationship.'],
            ['code' => '101YP2500X', 'classification' => 'Behavioral Health & Social Service Providers', 'specialization' => 'Counselor — Professional',               'description' => 'A professional counselor trained in counseling theory and practice, capable of addressing a wide variety of mental health concerns.'],
            ['code' => '101YS0200X', 'classification' => 'Behavioral Health & Social Service Providers', 'specialization' => 'Counselor — School',                     'description' => 'A counselor who works within the educational system to support students\' academic, career, and social/emotional development.'],
            ['code' => '103T00000X', 'classification' => 'Behavioral Health & Social Service Providers', 'specialization' => 'Psychologist',                           'description' => 'A provider who is trained and educated in the performance of psychological services including assessment, diagnosis, and treatment of mental disorders.'],
            ['code' => '103TA0400X', 'classification' => 'Behavioral Health & Social Service Providers', 'specialization' => 'Psychologist — Addiction (Substance Use Disorder)', 'description' => 'A psychologist specializing in the assessment and treatment of individuals with alcohol, drug, or other substance use disorders.'],
            ['code' => '103TA0700X', 'classification' => 'Behavioral Health & Social Service Providers', 'specialization' => 'Psychologist — Adult Development & Aging', 'description' => 'A psychologist specializing in the psychological aspects of adult development and aging, including cognitive decline and late-life mental health.'],
            ['code' => '103TC0700X', 'classification' => 'Behavioral Health & Social Service Providers', 'specialization' => 'Psychologist — Clinical',                'description' => 'A psychologist specializing in the assessment, diagnosis, treatment, and prevention of mental, emotional, and behavioral disorders.'],
            ['code' => '103TC2200X', 'classification' => 'Behavioral Health & Social Service Providers', 'specialization' => 'Psychologist — Clinical Child & Adolescent', 'description' => 'A psychologist specializing in the diagnosis and treatment of psychological disorders in children, adolescents, and their families.'],
            ['code' => '103TF0000X', 'classification' => 'Behavioral Health & Social Service Providers', 'specialization' => 'Psychologist — Family',                  'description' => 'A psychologist specializing in family systems, interpersonal relationships, and the treatment of families and couples.'],
            ['code' => '103TH0100X', 'classification' => 'Behavioral Health & Social Service Providers', 'specialization' => 'Psychologist — Health Service',          'description' => 'A psychologist who is trained to deliver direct, preventive, assessment, and therapeutic intervention services to individuals whose functioning is impaired.'],
            ['code' => '103TP2701X', 'classification' => 'Behavioral Health & Social Service Providers', 'specialization' => 'Psychologist — Prescribing (Medical)',   'description' => 'A psychologist who has undergone specialized training granting authority to prescribe psychotropic medications in applicable jurisdictions.'],
            ['code' => '104100000X', 'classification' => 'Behavioral Health & Social Service Providers', 'specialization' => 'Social Worker',                          'description' => 'A provider trained in social work practice, focused on enhancing human well-being, meeting basic needs, and empowering vulnerable populations.'],
            ['code' => '1041C0700X', 'classification' => 'Behavioral Health & Social Service Providers', 'specialization' => 'Social Worker — Clinical',               'description' => 'A licensed clinical social worker (LCSW) qualified to diagnose and treat mental, behavioral, and emotional disorders through clinical social work practice.'],
            ['code' => '1041S0200X', 'classification' => 'Behavioral Health & Social Service Providers', 'specialization' => 'Social Worker — School',                 'description' => 'A social worker specializing in the school setting, addressing barriers to learning and connecting students and families with community resources.'],
            ['code' => '106H00000X', 'classification' => 'Behavioral Health & Social Service Providers', 'specialization' => 'Marriage & Family Therapist',            'description' => 'A provider trained in the diagnosis and treatment of mental and emotional disorders within the context of marriage, couples, and family systems.'],
            ['code' => '163W00000X', 'classification' => 'Nursing Service Providers',                    'specialization' => 'Registered Nurse',                        'description' => 'A registered nurse (RN) who has completed a nursing education program and met the requirements for licensure.'],
            ['code' => '163WP0808X', 'classification' => 'Nursing Service Providers',                    'specialization' => 'Registered Nurse — Psychiatric/Mental Health', 'description' => 'A registered nurse specializing in the care of patients with mental illness, psychiatric disorders, and behavioral health conditions.'],
            ['code' => '261QM0801X', 'classification' => 'Ambulatory Health Care Facilities',            'specialization' => 'Mental Health Clinic (including CMHC)',   'description' => 'A clinic that provides outpatient mental health services, including assessment, diagnosis, and treatment of mental and behavioral health disorders.'],
            ['code' => '2084P0800X', 'classification' => 'Allopathic & Osteopathic Physicians',          'specialization' => 'Psychiatry',                              'description' => 'A physician specializing in the diagnosis, treatment, and prevention of mental, emotional, and behavioral disorders.'],
            ['code' => '2084P0802X', 'classification' => 'Allopathic & Osteopathic Physicians',          'specialization' => 'Psychiatry — Addiction Medicine',         'description' => 'A psychiatrist specializing in the evaluation and treatment of individuals with alcohol, drug, or other substance-related disorders.'],
            ['code' => '2084P0804X', 'classification' => 'Allopathic & Osteopathic Physicians',          'specialization' => 'Psychiatry — Child & Adolescent',         'description' => 'A psychiatrist specializing in the diagnosis and treatment of mental, addictive, and emotional disorders of children and adolescents.'],
            ['code' => '2084P0805X', 'classification' => 'Allopathic & Osteopathic Physicians',          'specialization' => 'Psychiatry — Geriatric',                  'description' => 'A psychiatrist specializing in the prevention, evaluation, diagnosis, and treatment of mental and emotional disorders in the elderly.'],
            ['code' => '251S00000X', 'classification' => 'Agencies',                                     'specialization' => 'Community/Behavioral Health',             'description' => 'An agency that provides community-based behavioral health services, including outpatient counseling, case management, and crisis intervention.'],
            ['code' => '320600000X', 'classification' => 'Residential Treatment Facilities',             'specialization' => 'Residential Treatment Facility — Mental Illness',    'description' => 'A facility providing 24-hour residential treatment for individuals with serious mental illness who require structured support.'],
            ['code' => '320900000X', 'classification' => 'Residential Treatment Facilities',             'specialization' => 'Residential Treatment Facility — Substance Abuse',   'description' => 'A facility providing 24-hour residential treatment for individuals with substance use disorders, including detoxification and rehabilitation.'],
            ['code' => '323P00000X', 'classification' => 'Residential Treatment Facilities',             'specialization' => 'Psychiatric Residential Treatment Facility',         'description' => 'A facility providing intensive 24-hour psychiatric care for individuals, typically children and adolescents, with severe emotional disturbances.'],
            ['code' => '3104A0630X', 'classification' => 'Home Health / HCBS Waiver Providers',          'specialization' => 'Behavioral Health & Social Service (HCBS)',          'description' => 'A home and community-based services provider delivering behavioral health and social services to individuals in their homes and communities.'],
            ['code' => '364SP0808X', 'classification' => 'Physician Assistants & Advanced Practice Nursing', 'specialization' => 'Clinical Nurse Specialist — Psychiatric/Mental Health', 'description' => 'An advanced practice registered nurse (CNS) specializing in psychiatric and mental health nursing across the lifespan.'],
            ['code' => '363LP0808X', 'classification' => 'Physician Assistants & Advanced Practice Nursing', 'specialization' => 'Nurse Practitioner — Psychiatric/Mental Health',      'description' => 'A psychiatric-mental health nurse practitioner (PMHNP) qualified to diagnose, treat, and prescribe medications for mental health disorders.'],
            ['code' => '390200000X', 'classification' => 'Student, Health Care',                         'specialization' => 'Student Health',                          'description' => 'A student in an accredited healthcare education program performing clinical activities under supervision as part of their training.'],
            ['code' => '171M00000X', 'classification' => 'Other Service Providers',                      'specialization' => 'Case Manager/Care Coordinator',           'description' => 'A provider who assesses, plans, implements, coordinates, monitors, and evaluates care options and services for patients across the continuum of care.'],
            ['code' => '172V00000X', 'classification' => 'Other Service Providers',                      'specialization' => 'Community Health Worker',                 'description' => 'A frontline public health worker who serves as a liaison between health/social services and the community to facilitate access to services.'],
            ['code' => '176B00000X', 'classification' => 'Other Service Providers',                      'specialization' => 'Midlevel Practitioner',                   'description' => 'A healthcare provider who practices under the general supervision of a physician but is qualified to perform certain medical services independently.'],
            ['code' => '225X00000X', 'classification' => 'Respiratory, Developmental, Rehabilitative & Restorative', 'specialization' => 'Occupational Therapist — Mental Health', 'description' => 'An occupational therapist specializing in the evaluation and treatment of individuals with mental health conditions to improve daily functioning.'],
        ];

        $now = now();

        foreach ($codes as $code) {
            DB::table('taxonomy_codes')->updateOrInsert(
                ['code' => $code['code']],
                array_merge($code, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }
    }
}

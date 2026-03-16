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

            // ===================================================================
            // BEHAVIORAL HEALTH & SOCIAL SERVICE PROVIDERS (Individual)
            // ===================================================================

            // Counselors
            ['code' => '101Y00000X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Counselor'],
            ['code' => '101YA0400X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Counselor — Addiction'],
            ['code' => '101YM0800X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Counselor — Mental Health'],
            ['code' => '101YP1600X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Counselor — Pastoral'],
            ['code' => '101YP2500X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Counselor — Professional'],
            ['code' => '101YS0200X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Counselor — School'],

            // Psychologists
            ['code' => '103T00000X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Psychologist'],
            ['code' => '103TA0400X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Psychologist — Addiction'],
            ['code' => '103TA0700X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Psychologist — Adult Development & Aging'],
            ['code' => '103TC0700X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Psychologist — Clinical'],
            ['code' => '103TC2200X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Psychologist — Clinical Child & Adolescent'],
            ['code' => '103TF0000X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Psychologist — Family'],
            ['code' => '103TH0100X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Psychologist — Health Service'],
            ['code' => '103TP2701X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Psychologist — Prescribing'],
            ['code' => '103TP0016X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Psychologist — Prescribing (Medical)'],
            ['code' => '103TR0400X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Psychologist — Rehabilitation'],
            ['code' => '103TS0200X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Psychologist — School'],
            ['code' => '103TW0100X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Psychologist — Women'],

            // Social Workers
            ['code' => '104100000X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Social Worker'],
            ['code' => '1041C0700X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Social Worker — Clinical'],
            ['code' => '1041S0200X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Social Worker — School'],

            // Marriage & Family Therapist
            ['code' => '106H00000X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Marriage & Family Therapist'],

            // Behavioral Analysts
            ['code' => '103K00000X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Behavior Analyst'],
            ['code' => '106S00000X', 'type' => 'Individual', 'classification' => 'Behavioral Health & Social Service Providers', 'specialty' => 'Behavior Technician'],

            // ===================================================================
            // ALLOPATHIC & OSTEOPATHIC PHYSICIANS (Individual)
            // ===================================================================

            // Psychiatry
            ['code' => '2084P0800X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Psychiatry'],
            ['code' => '2084P0802X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Psychiatry — Addiction Medicine'],
            ['code' => '2084P0804X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Psychiatry — Child & Adolescent'],
            ['code' => '2084P0805X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Psychiatry — Geriatric'],
            ['code' => '2084F0202X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Psychiatry — Forensic'],
            ['code' => '2084N0600X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Psychiatry — Clinical Neurophysiology'],
            ['code' => '2084B0040X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Psychiatry — Behavioral Neurology & Neuropsychiatry'],
            ['code' => '2084S0010X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Psychiatry — Sports Medicine'],
            ['code' => '2084S0012X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Psychiatry — Sleep Medicine'],
            ['code' => '2084D0003X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Psychiatry — Consultation-Liaison'],

            // Internal Medicine
            ['code' => '207R00000X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Internal Medicine'],
            ['code' => '207RC0000X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Internal Medicine — Cardiovascular Disease'],
            ['code' => '207RG0100X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Internal Medicine — Gastroenterology'],
            ['code' => '207RP1001X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Internal Medicine — Pulmonary Disease'],
            ['code' => '207RN0300X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Internal Medicine — Nephrology'],
            ['code' => '207RR0500X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Internal Medicine — Rheumatology'],
            ['code' => '207RE0101X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Internal Medicine — Endocrinology, Diabetes & Metabolism'],
            ['code' => '207RH0000X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Internal Medicine — Hematology'],
            ['code' => '207RH0003X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Internal Medicine — Hematology & Oncology'],
            ['code' => '207RI0200X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Internal Medicine — Infectious Disease'],
            ['code' => '207RG0300X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Internal Medicine — Geriatric Medicine'],
            ['code' => '207RA0001X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Internal Medicine — Adolescent Medicine'],
            ['code' => '207RA0201X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Internal Medicine — Allergy & Immunology'],
            ['code' => '207RC0200X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Internal Medicine — Critical Care Medicine'],
            ['code' => '207RI0001X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Internal Medicine — Clinical & Laboratory Immunology'],
            ['code' => '207RC0001X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Internal Medicine — Clinical Cardiac Electrophysiology'],
            ['code' => '207RI0008X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Internal Medicine — Hepatology'],
            ['code' => '207RH0002X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Internal Medicine — Hospice & Palliative Medicine'],
            ['code' => '207RS0012X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Internal Medicine — Sleep Medicine'],
            ['code' => '207RX0202X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Internal Medicine — Medical Oncology'],

            // Family Medicine
            ['code' => '207Q00000X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Family Medicine'],
            ['code' => '207QA0000X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Family Medicine — Adolescent Medicine'],
            ['code' => '207QA0505X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Family Medicine — Adult Medicine'],
            ['code' => '207QG0300X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Family Medicine — Geriatric Medicine'],
            ['code' => '207QS0010X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Family Medicine — Sports Medicine'],
            ['code' => '207QS1201X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Family Medicine — Sleep Medicine'],
            ['code' => '207QH0002X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Family Medicine — Hospice & Palliative Medicine'],

            // General Practice
            ['code' => '208D00000X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'General Practice'],

            // Pediatrics
            ['code' => '208000000X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Pediatrics'],
            ['code' => '2080P0006X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Pediatrics — Developmental-Behavioral'],
            ['code' => '2080N0001X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Pediatrics — Neonatal-Perinatal Medicine'],
            ['code' => '2080C0008X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Pediatrics — Child Abuse'],
            ['code' => '2080H0002X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Pediatrics — Hospice & Palliative Medicine'],
            ['code' => '2080P0201X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Pediatrics — Pediatric Allergy & Immunology'],
            ['code' => '2080P0202X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Pediatrics — Pediatric Cardiology'],
            ['code' => '2080P0203X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Pediatrics — Pediatric Critical Care Medicine'],
            ['code' => '2080P0204X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Pediatrics — Pediatric Emergency Medicine'],
            ['code' => '2080P0205X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Pediatrics — Pediatric Endocrinology'],
            ['code' => '2080P0206X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Pediatrics — Pediatric Gastroenterology'],
            ['code' => '2080P0207X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Pediatrics — Pediatric Hematology-Oncology'],
            ['code' => '2080P0208X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Pediatrics — Pediatric Infectious Diseases'],
            ['code' => '2080P0210X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Pediatrics — Pediatric Nephrology'],
            ['code' => '2080P0214X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Pediatrics — Pediatric Pulmonology'],
            ['code' => '2080P0216X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Pediatrics — Pediatric Rheumatology'],
            ['code' => '2080S0012X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Pediatrics — Sleep Medicine'],
            ['code' => '2080S0010X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Pediatrics — Sports Medicine'],

            // Surgery — General
            ['code' => '208600000X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Surgery'],
            ['code' => '2086S0120X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Surgery — Pediatric Surgery'],
            ['code' => '2086S0122X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Surgery — Plastic & Reconstructive Surgery'],
            ['code' => '2086S0105X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Surgery — Surgery of the Hand'],
            ['code' => '2086S0102X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Surgery — Surgical Critical Care'],
            ['code' => '2086X0206X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Surgery — Surgical Oncology'],
            ['code' => '2086H0002X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Surgery — Hospice & Palliative Medicine'],

            // Orthopedic Surgery
            ['code' => '207X00000X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Orthopedic Surgery'],
            ['code' => '207XS0114X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Orthopedic Surgery — Adult Reconstructive'],
            ['code' => '207XX0004X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Orthopedic Surgery — Foot & Ankle'],
            ['code' => '207XS0106X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Orthopedic Surgery — Hand Surgery'],
            ['code' => '207XS0117X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Orthopedic Surgery — Orthopedic Surgery of the Spine'],
            ['code' => '207XX0801X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Orthopedic Surgery — Orthopedic Trauma'],
            ['code' => '207XP3100X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Orthopedic Surgery — Pediatric Orthopedic Surgery'],
            ['code' => '207XX0005X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Orthopedic Surgery — Sports Medicine'],

            // Cardiovascular Surgery (Thoracic Surgery)
            ['code' => '208G00000X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Thoracic Surgery (Cardiothoracic Vascular Surgery)'],

            // Neurological Surgery
            ['code' => '207T00000X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Neurological Surgery'],

            // Plastic Surgery
            ['code' => '208200000X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Plastic Surgery'],
            ['code' => '2082S0099X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Plastic Surgery — Plastic Surgery Within the Head & Neck'],
            ['code' => '2082S0105X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Plastic Surgery — Surgery of the Hand'],

            // Colon & Rectal Surgery
            ['code' => '208C00000X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Colon & Rectal Surgery'],

            // OB/GYN
            ['code' => '207V00000X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Obstetrics & Gynecology'],
            ['code' => '207VG0400X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Obstetrics & Gynecology — Gynecology'],
            ['code' => '207VX0000X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Obstetrics & Gynecology — Obstetrics'],
            ['code' => '207VM0101X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Obstetrics & Gynecology — Maternal & Fetal Medicine'],
            ['code' => '207VX0201X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Obstetrics & Gynecology — Gynecologic Oncology'],
            ['code' => '207VC0200X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Obstetrics & Gynecology — Critical Care Medicine'],
            ['code' => '207VF0040X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Obstetrics & Gynecology — Female Pelvic Medicine & Reconstructive Surgery'],
            ['code' => '207VE0102X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Obstetrics & Gynecology — Reproductive Endocrinology'],

            // Emergency Medicine
            ['code' => '207P00000X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Emergency Medicine'],
            ['code' => '207PE0004X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Emergency Medicine — Emergency Medical Services'],
            ['code' => '207PE0005X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Emergency Medicine — Undersea & Hyperbaric Medicine'],
            ['code' => '207PS0010X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Emergency Medicine — Sports Medicine'],
            ['code' => '207PT0002X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Emergency Medicine — Medical Toxicology'],
            ['code' => '207PP0204X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Emergency Medicine — Pediatric Emergency Medicine'],
            ['code' => '207PH0002X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Emergency Medicine — Hospice & Palliative Medicine'],

            // Anesthesiology
            ['code' => '207L00000X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Anesthesiology'],
            ['code' => '207LA0401X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Anesthesiology — Addiction Medicine'],
            ['code' => '207LC0200X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Anesthesiology — Critical Care Medicine'],
            ['code' => '207LP2900X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Anesthesiology — Pain Medicine'],
            ['code' => '207LP3000X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Anesthesiology — Pediatric Anesthesiology'],
            ['code' => '207LH0002X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Anesthesiology — Hospice & Palliative Medicine'],

            // Radiology
            ['code' => '2085R0202X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Radiology — Diagnostic Radiology'],
            ['code' => '2085R0001X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Radiology — Radiation Oncology'],
            ['code' => '2085R0204X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Radiology — Vascular & Interventional Radiology'],
            ['code' => '2085N0700X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Radiology — Neuroradiology'],
            ['code' => '2085U0001X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Radiology — Nuclear Radiology'],
            ['code' => '2085P0229X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Radiology — Pediatric Radiology'],
            ['code' => '2085D0003X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Radiology — Diagnostic Neuroimaging'],
            ['code' => '2085H0002X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Radiology — Hospice & Palliative Medicine'],

            // Pathology
            ['code' => '207ZP0101X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Pathology — Anatomic Pathology'],
            ['code' => '207ZP0102X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Pathology — Anatomic Pathology & Clinical Pathology'],
            ['code' => '207ZB0001X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Pathology — Blood Banking & Transfusion Medicine'],
            ['code' => '207ZP0104X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Pathology — Clinical Pathology'],
            ['code' => '207ZP0105X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Pathology — Clinical Pathology/Laboratory Medicine'],
            ['code' => '207ZC0006X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Pathology — Clinical Informatics'],
            ['code' => '207ZC0500X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Pathology — Cytopathology'],
            ['code' => '207ZD0900X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Pathology — Dermatopathology'],
            ['code' => '207ZF0201X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Pathology — Forensic Pathology'],
            ['code' => '207ZN0500X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Pathology — Neuropathology'],

            // Dermatology
            ['code' => '207N00000X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Dermatology'],
            ['code' => '207ND0101X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Dermatology — MOHS-Micrographic Surgery'],
            ['code' => '207NI0002X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Dermatology — Clinical & Laboratory Dermatological Immunology'],
            ['code' => '207ND0900X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Dermatology — Dermatopathology'],
            ['code' => '207NP0225X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Dermatology — Pediatric Dermatology'],
            ['code' => '207NS0135X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Dermatology — Procedural Dermatology'],

            // Ophthalmology
            ['code' => '207W00000X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Ophthalmology'],
            ['code' => '207WX0200X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Ophthalmology — Ophthalmic Plastic & Reconstructive Surgery'],

            // Otolaryngology
            ['code' => '207Y00000X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Otolaryngology'],
            ['code' => '207YS0123X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Otolaryngology — Facial Plastic Surgery'],
            ['code' => '207YX0602X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Otolaryngology — Otolaryngic Allergy'],
            ['code' => '207YX0901X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Otolaryngology — Otology & Neurotology'],
            ['code' => '207YX0905X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Otolaryngology — Otolaryngology/Facial Plastic Surgery'],
            ['code' => '207YP0228X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Otolaryngology — Pediatric Otolaryngology'],
            ['code' => '207YS0012X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Otolaryngology — Sleep Medicine'],

            // Urology
            ['code' => '208800000X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Urology'],
            ['code' => '2088P0231X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Urology — Pediatric Urology'],
            ['code' => '2088F0040X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Urology — Female Pelvic Medicine & Reconstructive Surgery'],

            // Neurology
            ['code' => '2084N0400X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Neurology'],
            ['code' => '2084N0402X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Neurology — Neurology with Special Qualifications in Child Neurology'],
            ['code' => '2084N0600X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Neurology — Clinical Neurophysiology'],
            ['code' => '2084V0102X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Neurology — Vascular Neurology'],
            ['code' => '2084E0001X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Neurology — Epilepsy'],
            ['code' => '2084A0401X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Neurology — Addiction Medicine'],
            ['code' => '2084H0002X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Neurology — Hospice & Palliative Medicine'],

            // Physical Medicine & Rehabilitation
            ['code' => '208100000X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Physical Medicine & Rehabilitation'],
            ['code' => '2081P2900X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Physical Medicine & Rehabilitation — Pain Medicine'],
            ['code' => '2081P0010X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Physical Medicine & Rehabilitation — Pediatric Rehabilitation Medicine'],
            ['code' => '2081P0004X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Physical Medicine & Rehabilitation — Spinal Cord Injury Medicine'],
            ['code' => '2081S0010X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Physical Medicine & Rehabilitation — Sports Medicine'],
            ['code' => '2081H0002X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Physical Medicine & Rehabilitation — Hospice & Palliative Medicine'],
            ['code' => '2081N0001X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Physical Medicine & Rehabilitation — Neuromuscular Medicine'],
            ['code' => '2081B0002X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Physical Medicine & Rehabilitation — Brain Injury Medicine'],

            // Allergy & Immunology
            ['code' => '207K00000X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Allergy & Immunology'],
            ['code' => '207KA0200X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Allergy & Immunology — Allergy'],
            ['code' => '207KI0005X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Allergy & Immunology — Clinical & Laboratory Immunology'],

            // Nuclear Medicine
            ['code' => '207U00000X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Nuclear Medicine'],
            ['code' => '207UN0903X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Nuclear Medicine — In Vivo & In Vitro Nuclear Medicine'],
            ['code' => '207UN0901X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Nuclear Medicine — Nuclear Cardiology'],

            // Preventive Medicine
            ['code' => '2083P0500X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Preventive Medicine — Preventive Medicine/Occupational Environmental Medicine'],
            ['code' => '2083T0002X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Preventive Medicine — Addiction Medicine'],
            ['code' => '2083P0011X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Preventive Medicine — Undersea & Hyperbaric Medicine'],
            ['code' => '2083X0100X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Preventive Medicine — Occupational Medicine'],
            ['code' => '2083P0901X', 'type' => 'Individual', 'classification' => 'Allopathic & Osteopathic Physicians', 'specialty' => 'Preventive Medicine — Public Health & General Preventive Medicine'],

            // ===================================================================
            // PHYSICIAN ASSISTANTS & ADVANCED PRACTICE NURSING (Individual)
            // ===================================================================

            // Nurse Practitioners
            ['code' => '363LP0808X', 'type' => 'Individual', 'classification' => 'Physician Assistants & Advanced Practice Nursing', 'specialty' => 'Nurse Practitioner — Psychiatric/Mental Health'],
            ['code' => '363LF0000X', 'type' => 'Individual', 'classification' => 'Physician Assistants & Advanced Practice Nursing', 'specialty' => 'Nurse Practitioner — Family'],
            ['code' => '363LA2200X', 'type' => 'Individual', 'classification' => 'Physician Assistants & Advanced Practice Nursing', 'specialty' => 'Nurse Practitioner — Adult-Gerontology Acute Care'],
            ['code' => '363LP0200X', 'type' => 'Individual', 'classification' => 'Physician Assistants & Advanced Practice Nursing', 'specialty' => 'Nurse Practitioner — Pediatrics'],
            ['code' => '363LW0102X', 'type' => 'Individual', 'classification' => 'Physician Assistants & Advanced Practice Nursing', 'specialty' => "Nurse Practitioner — Women's Health"],
            ['code' => '363LC1500X', 'type' => 'Individual', 'classification' => 'Physician Assistants & Advanced Practice Nursing', 'specialty' => 'Nurse Practitioner — Community Health'],
            ['code' => '363LC0200X', 'type' => 'Individual', 'classification' => 'Physician Assistants & Advanced Practice Nursing', 'specialty' => 'Nurse Practitioner — Critical Care Medicine'],
            ['code' => '363LN0000X', 'type' => 'Individual', 'classification' => 'Physician Assistants & Advanced Practice Nursing', 'specialty' => 'Nurse Practitioner — Neonatal'],
            ['code' => '363LN0005X', 'type' => 'Individual', 'classification' => 'Physician Assistants & Advanced Practice Nursing', 'specialty' => 'Nurse Practitioner — Neonatal Critical Care'],
            ['code' => '363LA2100X', 'type' => 'Individual', 'classification' => 'Physician Assistants & Advanced Practice Nursing', 'specialty' => 'Nurse Practitioner — Acute Care'],
            ['code' => '363LX0001X', 'type' => 'Individual', 'classification' => 'Physician Assistants & Advanced Practice Nursing', 'specialty' => 'Nurse Practitioner — Obstetrics & Gynecology'],
            ['code' => '363LX0106X', 'type' => 'Individual', 'classification' => 'Physician Assistants & Advanced Practice Nursing', 'specialty' => 'Nurse Practitioner — Occupational Health'],
            ['code' => '363LP0222X', 'type' => 'Individual', 'classification' => 'Physician Assistants & Advanced Practice Nursing', 'specialty' => 'Nurse Practitioner — Primary Care'],
            ['code' => '363LS0200X', 'type' => 'Individual', 'classification' => 'Physician Assistants & Advanced Practice Nursing', 'specialty' => 'Nurse Practitioner — School'],
            ['code' => '363LA2300X', 'type' => 'Individual', 'classification' => 'Physician Assistants & Advanced Practice Nursing', 'specialty' => 'Nurse Practitioner — Adult-Gerontology Primary Care'],

            // Clinical Nurse Specialists
            ['code' => '364SP0808X', 'type' => 'Individual', 'classification' => 'Physician Assistants & Advanced Practice Nursing', 'specialty' => 'Clinical Nurse Specialist — Psychiatric/Mental Health'],
            ['code' => '364SA2100X', 'type' => 'Individual', 'classification' => 'Physician Assistants & Advanced Practice Nursing', 'specialty' => 'Clinical Nurse Specialist — Acute Care'],
            ['code' => '364SA2200X', 'type' => 'Individual', 'classification' => 'Physician Assistants & Advanced Practice Nursing', 'specialty' => 'Clinical Nurse Specialist — Adult-Gerontology'],
            ['code' => '364SC2300X', 'type' => 'Individual', 'classification' => 'Physician Assistants & Advanced Practice Nursing', 'specialty' => 'Clinical Nurse Specialist — Chronic Care'],
            ['code' => '364SC1501X', 'type' => 'Individual', 'classification' => 'Physician Assistants & Advanced Practice Nursing', 'specialty' => 'Clinical Nurse Specialist — Community Health/Public Health'],
            ['code' => '364SF0001X', 'type' => 'Individual', 'classification' => 'Physician Assistants & Advanced Practice Nursing', 'specialty' => 'Clinical Nurse Specialist — Family Health'],
            ['code' => '364SN0000X', 'type' => 'Individual', 'classification' => 'Physician Assistants & Advanced Practice Nursing', 'specialty' => 'Clinical Nurse Specialist — Neonatal'],
            ['code' => '364SP0200X', 'type' => 'Individual', 'classification' => 'Physician Assistants & Advanced Practice Nursing', 'specialty' => 'Clinical Nurse Specialist — Pediatrics'],
            ['code' => '364SW0102X', 'type' => 'Individual', 'classification' => 'Physician Assistants & Advanced Practice Nursing', 'specialty' => "Clinical Nurse Specialist — Women's Health"],
            ['code' => '364SS0200X', 'type' => 'Individual', 'classification' => 'Physician Assistants & Advanced Practice Nursing', 'specialty' => 'Clinical Nurse Specialist — School'],

            // Physician Assistants
            ['code' => '363A00000X', 'type' => 'Individual', 'classification' => 'Physician Assistants & Advanced Practice Nursing', 'specialty' => 'Physician Assistant'],
            ['code' => '363AM0700X', 'type' => 'Individual', 'classification' => 'Physician Assistants & Advanced Practice Nursing', 'specialty' => 'Physician Assistant — Medical'],

            // CRNA
            ['code' => '367500000X', 'type' => 'Individual', 'classification' => 'Physician Assistants & Advanced Practice Nursing', 'specialty' => 'Certified Registered Nurse Anesthetist (CRNA)'],

            // Certified Nurse Midwife
            ['code' => '367A00000X', 'type' => 'Individual', 'classification' => 'Physician Assistants & Advanced Practice Nursing', 'specialty' => 'Certified Nurse Midwife'],

            // ===================================================================
            // NURSING SERVICE PROVIDERS (Individual)
            // ===================================================================

            ['code' => '163W00000X', 'type' => 'Individual', 'classification' => 'Nursing Service Providers', 'specialty' => 'Registered Nurse'],
            ['code' => '163WP0808X', 'type' => 'Individual', 'classification' => 'Nursing Service Providers', 'specialty' => 'Registered Nurse — Psychiatric/Mental Health'],
            ['code' => '163WP0809X', 'type' => 'Individual', 'classification' => 'Nursing Service Providers', 'specialty' => 'Registered Nurse — Psychiatric/Mental Health — Adult'],
            ['code' => '163WP0807X', 'type' => 'Individual', 'classification' => 'Nursing Service Providers', 'specialty' => 'Registered Nurse — Psychiatric/Mental Health — Child & Adolescent'],
            ['code' => '163WC0400X', 'type' => 'Individual', 'classification' => 'Nursing Service Providers', 'specialty' => 'Registered Nurse — Case Management'],
            ['code' => '163WC3500X', 'type' => 'Individual', 'classification' => 'Nursing Service Providers', 'specialty' => 'Registered Nurse — Cardiac Rehabilitation'],
            ['code' => '163WG0000X', 'type' => 'Individual', 'classification' => 'Nursing Service Providers', 'specialty' => 'Registered Nurse — General Practice'],
            ['code' => '163WG0600X', 'type' => 'Individual', 'classification' => 'Nursing Service Providers', 'specialty' => 'Registered Nurse — Gastroenterology'],
            ['code' => '163WH0200X', 'type' => 'Individual', 'classification' => 'Nursing Service Providers', 'specialty' => 'Registered Nurse — Home Health'],
            ['code' => '163WP0200X', 'type' => 'Individual', 'classification' => 'Nursing Service Providers', 'specialty' => 'Registered Nurse — Pediatrics'],
            ['code' => '163WS0200X', 'type' => 'Individual', 'classification' => 'Nursing Service Providers', 'specialty' => 'Registered Nurse — School'],
            ['code' => '163WX0002X', 'type' => 'Individual', 'classification' => 'Nursing Service Providers', 'specialty' => 'Registered Nurse — Obstetric, High-Risk'],
            ['code' => '163WX0003X', 'type' => 'Individual', 'classification' => 'Nursing Service Providers', 'specialty' => 'Registered Nurse — Obstetric, Inpatient'],
            ['code' => '164W00000X', 'type' => 'Individual', 'classification' => 'Nursing Service Providers', 'specialty' => 'Licensed Practical Nurse'],
            ['code' => '167G00000X', 'type' => 'Individual', 'classification' => 'Nursing Service Providers', 'specialty' => 'Licensed Psychiatric Technician'],

            // ===================================================================
            // DENTAL PROVIDERS (Individual)
            // ===================================================================

            ['code' => '122300000X', 'type' => 'Individual', 'classification' => 'Dental Providers', 'specialty' => 'Dentist'],
            ['code' => '1223G0001X', 'type' => 'Individual', 'classification' => 'Dental Providers', 'specialty' => 'Dentist — General Practice'],
            ['code' => '1223X0400X', 'type' => 'Individual', 'classification' => 'Dental Providers', 'specialty' => 'Dentist — Orthodontics & Dentofacial Orthopedics'],
            ['code' => '1223S0112X', 'type' => 'Individual', 'classification' => 'Dental Providers', 'specialty' => 'Dentist — Oral & Maxillofacial Surgery'],
            ['code' => '1223P0106X', 'type' => 'Individual', 'classification' => 'Dental Providers', 'specialty' => 'Dentist — Periodontics'],
            ['code' => '1223E0200X', 'type' => 'Individual', 'classification' => 'Dental Providers', 'specialty' => 'Dentist — Endodontics'],
            ['code' => '1223P0221X', 'type' => 'Individual', 'classification' => 'Dental Providers', 'specialty' => 'Dentist — Pediatric Dentistry'],
            ['code' => '1223P0300X', 'type' => 'Individual', 'classification' => 'Dental Providers', 'specialty' => 'Dentist — Prosthodontics'],
            ['code' => '1223D0001X', 'type' => 'Individual', 'classification' => 'Dental Providers', 'specialty' => 'Dentist — Dental Public Health'],
            ['code' => '1223X0008X', 'type' => 'Individual', 'classification' => 'Dental Providers', 'specialty' => 'Dentist — Oral & Maxillofacial Pathology'],
            ['code' => '1223D0008X', 'type' => 'Individual', 'classification' => 'Dental Providers', 'specialty' => 'Dentist — Oral & Maxillofacial Radiology'],
            ['code' => '124Q00000X', 'type' => 'Individual', 'classification' => 'Dental Providers', 'specialty' => 'Dental Hygienist'],
            ['code' => '126800000X', 'type' => 'Individual', 'classification' => 'Dental Providers', 'specialty' => 'Dental Assistant'],

            // ===================================================================
            // EYE AND VISION SERVICES (Individual)
            // ===================================================================

            ['code' => '152W00000X', 'type' => 'Individual', 'classification' => 'Eye and Vision Services Providers', 'specialty' => 'Optometrist'],
            ['code' => '152WC0802X', 'type' => 'Individual', 'classification' => 'Eye and Vision Services Providers', 'specialty' => 'Optometrist — Corneal & Contact Management'],
            ['code' => '152WL0500X', 'type' => 'Individual', 'classification' => 'Eye and Vision Services Providers', 'specialty' => 'Optometrist — Low Vision Rehabilitation'],
            ['code' => '152WP0200X', 'type' => 'Individual', 'classification' => 'Eye and Vision Services Providers', 'specialty' => 'Optometrist — Pediatrics'],
            ['code' => '152WV0400X', 'type' => 'Individual', 'classification' => 'Eye and Vision Services Providers', 'specialty' => 'Optometrist — Vision Therapy'],
            ['code' => '156FX1800X', 'type' => 'Individual', 'classification' => 'Eye and Vision Services Providers', 'specialty' => 'Technician/Technologist — Optician'],

            // ===================================================================
            // PODIATRIC MEDICINE (Individual)
            // ===================================================================

            ['code' => '213E00000X', 'type' => 'Individual', 'classification' => 'Podiatric Medicine & Surgery Service Providers', 'specialty' => 'Podiatrist'],
            ['code' => '213EG0000X', 'type' => 'Individual', 'classification' => 'Podiatric Medicine & Surgery Service Providers', 'specialty' => 'Podiatrist — General Practice'],
            ['code' => '213EP0504X', 'type' => 'Individual', 'classification' => 'Podiatric Medicine & Surgery Service Providers', 'specialty' => 'Podiatrist — Public Medicine'],
            ['code' => '213EP1101X', 'type' => 'Individual', 'classification' => 'Podiatric Medicine & Surgery Service Providers', 'specialty' => 'Podiatrist — Primary Podiatric Medicine'],
            ['code' => '213ES0000X', 'type' => 'Individual', 'classification' => 'Podiatric Medicine & Surgery Service Providers', 'specialty' => 'Podiatrist — Sports Medicine'],
            ['code' => '213ES0103X', 'type' => 'Individual', 'classification' => 'Podiatric Medicine & Surgery Service Providers', 'specialty' => 'Podiatrist — Foot & Ankle Surgery'],
            ['code' => '213ES0131X', 'type' => 'Individual', 'classification' => 'Podiatric Medicine & Surgery Service Providers', 'specialty' => 'Podiatrist — Foot Surgery'],

            // ===================================================================
            // CHIROPRACTIC (Individual)
            // ===================================================================

            ['code' => '111N00000X', 'type' => 'Individual', 'classification' => 'Chiropractic Providers', 'specialty' => 'Chiropractor'],
            ['code' => '111NI0013X', 'type' => 'Individual', 'classification' => 'Chiropractic Providers', 'specialty' => 'Chiropractor — Independent Medical Examiner'],
            ['code' => '111NI0900X', 'type' => 'Individual', 'classification' => 'Chiropractic Providers', 'specialty' => 'Chiropractor — Internist'],
            ['code' => '111NN0400X', 'type' => 'Individual', 'classification' => 'Chiropractic Providers', 'specialty' => 'Chiropractor — Neurology'],
            ['code' => '111NN1001X', 'type' => 'Individual', 'classification' => 'Chiropractic Providers', 'specialty' => 'Chiropractor — Nutrition'],
            ['code' => '111NR0200X', 'type' => 'Individual', 'classification' => 'Chiropractic Providers', 'specialty' => 'Chiropractor — Radiology'],
            ['code' => '111NS0005X', 'type' => 'Individual', 'classification' => 'Chiropractic Providers', 'specialty' => 'Chiropractor — Sports Physician'],
            ['code' => '111NX0100X', 'type' => 'Individual', 'classification' => 'Chiropractic Providers', 'specialty' => 'Chiropractor — Occupational Health'],
            ['code' => '111NX0800X', 'type' => 'Individual', 'classification' => 'Chiropractic Providers', 'specialty' => 'Chiropractor — Orthopedic'],
            ['code' => '111NP0017X', 'type' => 'Individual', 'classification' => 'Chiropractic Providers', 'specialty' => 'Chiropractor — Pediatric Chiropractor'],
            ['code' => '111NR0400X', 'type' => 'Individual', 'classification' => 'Chiropractic Providers', 'specialty' => 'Chiropractor — Rehabilitation'],

            // ===================================================================
            // PHARMACY SERVICE PROVIDERS (Individual)
            // ===================================================================

            ['code' => '183500000X', 'type' => 'Individual', 'classification' => 'Pharmacy Service Providers', 'specialty' => 'Pharmacist'],
            ['code' => '1835G0000X', 'type' => 'Individual', 'classification' => 'Pharmacy Service Providers', 'specialty' => 'Pharmacist — General Practice'],
            ['code' => '1835N0905X', 'type' => 'Individual', 'classification' => 'Pharmacy Service Providers', 'specialty' => 'Pharmacist — Nuclear Pharmacy'],
            ['code' => '1835P1200X', 'type' => 'Individual', 'classification' => 'Pharmacy Service Providers', 'specialty' => 'Pharmacist — Pharmacotherapy'],
            ['code' => '1835P1300X', 'type' => 'Individual', 'classification' => 'Pharmacy Service Providers', 'specialty' => 'Pharmacist — Psychiatric Pharmacy'],
            ['code' => '183700000X', 'type' => 'Individual', 'classification' => 'Pharmacy Service Providers', 'specialty' => 'Pharmacy Technician'],

            // ===================================================================
            // RESPIRATORY, DEVELOPMENTAL, REHABILITATIVE (Individual)
            // ===================================================================

            // Physical Therapist
            ['code' => '225100000X', 'type' => 'Individual', 'classification' => 'Respiratory, Developmental, Rehabilitative and Restorative Service Providers', 'specialty' => 'Physical Therapist'],
            ['code' => '2251C2600X', 'type' => 'Individual', 'classification' => 'Respiratory, Developmental, Rehabilitative and Restorative Service Providers', 'specialty' => 'Physical Therapist — Cardiopulmonary'],
            ['code' => '2251E1300X', 'type' => 'Individual', 'classification' => 'Respiratory, Developmental, Rehabilitative and Restorative Service Providers', 'specialty' => 'Physical Therapist — Electrophysiology, Clinical'],
            ['code' => '2251G0304X', 'type' => 'Individual', 'classification' => 'Respiratory, Developmental, Rehabilitative and Restorative Service Providers', 'specialty' => 'Physical Therapist — Geriatrics'],
            ['code' => '2251H1200X', 'type' => 'Individual', 'classification' => 'Respiratory, Developmental, Rehabilitative and Restorative Service Providers', 'specialty' => 'Physical Therapist — Hand'],
            ['code' => '2251H1300X', 'type' => 'Individual', 'classification' => 'Respiratory, Developmental, Rehabilitative and Restorative Service Providers', 'specialty' => 'Physical Therapist — Human Factors'],
            ['code' => '2251N0400X', 'type' => 'Individual', 'classification' => 'Respiratory, Developmental, Rehabilitative and Restorative Service Providers', 'specialty' => 'Physical Therapist — Neurology'],
            ['code' => '2251X0800X', 'type' => 'Individual', 'classification' => 'Respiratory, Developmental, Rehabilitative and Restorative Service Providers', 'specialty' => 'Physical Therapist — Orthopedic'],
            ['code' => '2251P0200X', 'type' => 'Individual', 'classification' => 'Respiratory, Developmental, Rehabilitative and Restorative Service Providers', 'specialty' => 'Physical Therapist — Pediatrics'],
            ['code' => '2251S0007X', 'type' => 'Individual', 'classification' => 'Respiratory, Developmental, Rehabilitative and Restorative Service Providers', 'specialty' => 'Physical Therapist — Sports'],

            // Occupational Therapist
            ['code' => '225X00000X', 'type' => 'Individual', 'classification' => 'Respiratory, Developmental, Rehabilitative and Restorative Service Providers', 'specialty' => 'Occupational Therapist'],
            ['code' => '225XE1200X', 'type' => 'Individual', 'classification' => 'Respiratory, Developmental, Rehabilitative and Restorative Service Providers', 'specialty' => 'Occupational Therapist — Ergonomics'],
            ['code' => '225XH1200X', 'type' => 'Individual', 'classification' => 'Respiratory, Developmental, Rehabilitative and Restorative Service Providers', 'specialty' => 'Occupational Therapist — Hand'],
            ['code' => '225XH1300X', 'type' => 'Individual', 'classification' => 'Respiratory, Developmental, Rehabilitative and Restorative Service Providers', 'specialty' => 'Occupational Therapist — Human Factors'],
            ['code' => '225XN1300X', 'type' => 'Individual', 'classification' => 'Respiratory, Developmental, Rehabilitative and Restorative Service Providers', 'specialty' => 'Occupational Therapist — Neurorehabilitation'],
            ['code' => '225XP0200X', 'type' => 'Individual', 'classification' => 'Respiratory, Developmental, Rehabilitative and Restorative Service Providers', 'specialty' => 'Occupational Therapist — Pediatrics'],
            ['code' => '225XR0403X', 'type' => 'Individual', 'classification' => 'Respiratory, Developmental, Rehabilitative and Restorative Service Providers', 'specialty' => 'Occupational Therapist — Driving & Community Mobility'],
            ['code' => '225XL0004X', 'type' => 'Individual', 'classification' => 'Respiratory, Developmental, Rehabilitative and Restorative Service Providers', 'specialty' => 'Occupational Therapist — Low Vision'],
            ['code' => '225XM0800X', 'type' => 'Individual', 'classification' => 'Respiratory, Developmental, Rehabilitative and Restorative Service Providers', 'specialty' => 'Occupational Therapist — Mental Health'],

            // Speech-Language Pathologist
            ['code' => '235Z00000X', 'type' => 'Individual', 'classification' => 'Respiratory, Developmental, Rehabilitative and Restorative Service Providers', 'specialty' => 'Speech-Language Pathologist'],

            // Audiologist
            ['code' => '231H00000X', 'type' => 'Individual', 'classification' => 'Respiratory, Developmental, Rehabilitative and Restorative Service Providers', 'specialty' => 'Audiologist'],
            ['code' => '231HA2400X', 'type' => 'Individual', 'classification' => 'Respiratory, Developmental, Rehabilitative and Restorative Service Providers', 'specialty' => 'Audiologist — Assistive Technology Practitioner'],
            ['code' => '231HA2500X', 'type' => 'Individual', 'classification' => 'Respiratory, Developmental, Rehabilitative and Restorative Service Providers', 'specialty' => 'Audiologist — Assistive Technology Supplier'],

            // Respiratory Therapist
            ['code' => '227800000X', 'type' => 'Individual', 'classification' => 'Respiratory, Developmental, Rehabilitative and Restorative Service Providers', 'specialty' => 'Respiratory Therapist — Certified'],
            ['code' => '227900000X', 'type' => 'Individual', 'classification' => 'Respiratory, Developmental, Rehabilitative and Restorative Service Providers', 'specialty' => 'Respiratory Therapist — Registered'],

            // Rehabilitation Counselor
            ['code' => '225C00000X', 'type' => 'Individual', 'classification' => 'Respiratory, Developmental, Rehabilitative and Restorative Service Providers', 'specialty' => 'Rehabilitation Counselor'],
            ['code' => '225CA2400X', 'type' => 'Individual', 'classification' => 'Respiratory, Developmental, Rehabilitative and Restorative Service Providers', 'specialty' => 'Rehabilitation Counselor — Assistive Technology Practitioner'],
            ['code' => '225CA2500X', 'type' => 'Individual', 'classification' => 'Respiratory, Developmental, Rehabilitative and Restorative Service Providers', 'specialty' => 'Rehabilitation Counselor — Assistive Technology Supplier'],

            // ===================================================================
            // DIETARY & NUTRITIONAL SERVICE PROVIDERS (Individual)
            // ===================================================================

            ['code' => '133V00000X', 'type' => 'Individual', 'classification' => 'Dietary & Nutritional Service Providers', 'specialty' => 'Dietitian, Registered'],
            ['code' => '133VN1006X', 'type' => 'Individual', 'classification' => 'Dietary & Nutritional Service Providers', 'specialty' => 'Dietitian, Registered — Nutrition, Metabolic'],
            ['code' => '133VN1004X', 'type' => 'Individual', 'classification' => 'Dietary & Nutritional Service Providers', 'specialty' => 'Dietitian, Registered — Nutrition, Pediatric'],
            ['code' => '133VN1005X', 'type' => 'Individual', 'classification' => 'Dietary & Nutritional Service Providers', 'specialty' => 'Dietitian, Registered — Nutrition, Renal'],
            ['code' => '133VN1101X', 'type' => 'Individual', 'classification' => 'Dietary & Nutritional Service Providers', 'specialty' => 'Dietitian, Registered — Nutrition, Gerontological'],
            ['code' => '133VN1201X', 'type' => 'Individual', 'classification' => 'Dietary & Nutritional Service Providers', 'specialty' => 'Dietitian, Registered — Nutrition, Obesity & Weight Management'],
            ['code' => '133VN1301X', 'type' => 'Individual', 'classification' => 'Dietary & Nutritional Service Providers', 'specialty' => 'Dietitian, Registered — Nutrition, Oncology'],
            ['code' => '133VN1401X', 'type' => 'Individual', 'classification' => 'Dietary & Nutritional Service Providers', 'specialty' => 'Dietitian, Registered — Nutrition, Pediatric Critical Care'],
            ['code' => '136A00000X', 'type' => 'Individual', 'classification' => 'Dietary & Nutritional Service Providers', 'specialty' => 'Dietetic Technician, Registered'],

            // ===================================================================
            // OTHER SERVICE PROVIDERS (Individual)
            // ===================================================================

            ['code' => '171M00000X', 'type' => 'Individual', 'classification' => 'Other Service Providers', 'specialty' => 'Case Manager/Care Coordinator'],
            ['code' => '172V00000X', 'type' => 'Individual', 'classification' => 'Other Service Providers', 'specialty' => 'Community Health Worker'],
            ['code' => '176B00000X', 'type' => 'Individual', 'classification' => 'Other Service Providers', 'specialty' => 'Midlevel Practitioner'],
            ['code' => '146L00000X', 'type' => 'Individual', 'classification' => 'Emergency Medical Service Providers', 'specialty' => 'Paramedic'],
            ['code' => '146N00000X', 'type' => 'Individual', 'classification' => 'Emergency Medical Service Providers', 'specialty' => 'Emergency Medical Technician — Basic'],
            ['code' => '146M00000X', 'type' => 'Individual', 'classification' => 'Emergency Medical Service Providers', 'specialty' => 'Emergency Medical Technician — Intermediate'],
            ['code' => '170300000X', 'type' => 'Individual', 'classification' => 'Other Service Providers', 'specialty' => 'Genetic Counselor'],
            ['code' => '170100000X', 'type' => 'Individual', 'classification' => 'Other Service Providers', 'specialty' => 'Medical Genetics — Ph.D. Medical Genetics'],
            ['code' => '174400000X', 'type' => 'Individual', 'classification' => 'Other Service Providers', 'specialty' => 'Specialist'],
            ['code' => '174H00000X', 'type' => 'Individual', 'classification' => 'Other Service Providers', 'specialty' => 'Health Educator'],
            ['code' => '171100000X', 'type' => 'Individual', 'classification' => 'Other Service Providers', 'specialty' => 'Acupuncturist'],
            ['code' => '175F00000X', 'type' => 'Individual', 'classification' => 'Other Service Providers', 'specialty' => 'Naturopath'],
            ['code' => '175L00000X', 'type' => 'Individual', 'classification' => 'Other Service Providers', 'specialty' => 'Homeopath'],
            ['code' => '175T00000X', 'type' => 'Individual', 'classification' => 'Other Service Providers', 'specialty' => 'Peer Specialist'],
            ['code' => '174M00000X', 'type' => 'Individual', 'classification' => 'Other Service Providers', 'specialty' => 'Veterinarian'],
            ['code' => '171W00000X', 'type' => 'Individual', 'classification' => 'Other Service Providers', 'specialty' => 'Contractor'],
            ['code' => '172A00000X', 'type' => 'Individual', 'classification' => 'Other Service Providers', 'specialty' => 'Driver'],
            ['code' => '176P00000X', 'type' => 'Individual', 'classification' => 'Other Service Providers', 'specialty' => 'Funeral Director'],

            // Student
            ['code' => '390200000X', 'type' => 'Individual', 'classification' => 'Student, Health Care', 'specialty' => 'Student Health'],

            // ===================================================================
            // TECHNOLOGISTS, TECHNICIANS & OTHER TECHNICAL (Individual)
            // ===================================================================

            ['code' => '246X00000X', 'type' => 'Individual', 'classification' => 'Technologists, Technicians & Other Technical Service Providers', 'specialty' => 'Cardiovascular Technologist'],
            ['code' => '247100000X', 'type' => 'Individual', 'classification' => 'Technologists, Technicians & Other Technical Service Providers', 'specialty' => 'Radiologic Technologist'],
            ['code' => '247200000X', 'type' => 'Individual', 'classification' => 'Technologists, Technicians & Other Technical Service Providers', 'specialty' => 'Technician, Other'],
            ['code' => '246W00000X', 'type' => 'Individual', 'classification' => 'Technologists, Technicians & Other Technical Service Providers', 'specialty' => 'Technician, Cardiology'],
            ['code' => '246R00000X', 'type' => 'Individual', 'classification' => 'Technologists, Technicians & Other Technical Service Providers', 'specialty' => 'Technician, Pathology'],
            ['code' => '246Y00000X', 'type' => 'Individual', 'classification' => 'Technologists, Technicians & Other Technical Service Providers', 'specialty' => 'Specialist/Technologist, Health Information'],
            ['code' => '246Q00000X', 'type' => 'Individual', 'classification' => 'Technologists, Technicians & Other Technical Service Providers', 'specialty' => 'Specialist/Technologist, Pathology'],
            ['code' => '246ZA2600X', 'type' => 'Individual', 'classification' => 'Technologists, Technicians & Other Technical Service Providers', 'specialty' => 'Specialist/Technologist, Other — Art, Medical'],

            // ===================================================================
            // ORGANIZATIONS — HOSPITALS
            // ===================================================================

            ['code' => '282N00000X', 'type' => 'Organization', 'classification' => 'Hospitals', 'specialty' => 'General Acute Care Hospital'],
            ['code' => '282NC0060X', 'type' => 'Organization', 'classification' => 'Hospitals', 'specialty' => 'General Acute Care Hospital — Critical Access'],
            ['code' => '282NC2000X', 'type' => 'Organization', 'classification' => 'Hospitals', 'specialty' => 'General Acute Care Hospital — Children'],
            ['code' => '282NR1301X', 'type' => 'Organization', 'classification' => 'Hospitals', 'specialty' => 'General Acute Care Hospital — Rural'],
            ['code' => '282NW0100X', 'type' => 'Organization', 'classification' => 'Hospitals', 'specialty' => "General Acute Care Hospital — Women"],
            ['code' => '283Q00000X', 'type' => 'Organization', 'classification' => 'Hospitals', 'specialty' => 'Psychiatric Hospital'],
            ['code' => '283X00000X', 'type' => 'Organization', 'classification' => 'Hospitals', 'specialty' => 'Rehabilitation Hospital'],
            ['code' => '283XC2000X', 'type' => 'Organization', 'classification' => 'Hospitals', 'specialty' => 'Rehabilitation Hospital — Children'],
            ['code' => '281P00000X', 'type' => 'Organization', 'classification' => 'Hospitals', 'specialty' => 'Chronic Disease Hospital'],
            ['code' => '281PC2000X', 'type' => 'Organization', 'classification' => 'Hospitals', 'specialty' => 'Chronic Disease Hospital — Children'],
            ['code' => '282E00000X', 'type' => 'Organization', 'classification' => 'Hospitals', 'specialty' => 'Long Term Care Hospital'],
            ['code' => '286500000X', 'type' => 'Organization', 'classification' => 'Hospitals', 'specialty' => 'Military Hospital'],
            ['code' => '287300000X', 'type' => 'Organization', 'classification' => 'Hospitals', 'specialty' => 'Christian Science Sanatorium'],
            ['code' => '284300000X', 'type' => 'Organization', 'classification' => 'Hospitals', 'specialty' => 'Special Hospital'],

            // ===================================================================
            // ORGANIZATIONS — AMBULATORY HEALTH CARE FACILITIES
            // ===================================================================

            ['code' => '261QM0801X', 'type' => 'Organization', 'classification' => 'Ambulatory Health Care Facilities', 'specialty' => 'Mental Health Clinic (Including Community Mental Health Center)'],
            ['code' => '261QA0600X', 'type' => 'Organization', 'classification' => 'Ambulatory Health Care Facilities', 'specialty' => 'Ambulatory Surgery Center'],
            ['code' => '261QU0200X', 'type' => 'Organization', 'classification' => 'Ambulatory Health Care Facilities', 'specialty' => 'Urgent Care Clinic'],
            ['code' => '261QF0400X', 'type' => 'Organization', 'classification' => 'Ambulatory Health Care Facilities', 'specialty' => 'Federally Qualified Health Center (FQHC)'],
            ['code' => '261QR0200X', 'type' => 'Organization', 'classification' => 'Ambulatory Health Care Facilities', 'specialty' => 'Radiology — Diagnostic Radiology'],
            ['code' => '261QR0206X', 'type' => 'Organization', 'classification' => 'Ambulatory Health Care Facilities', 'specialty' => 'Radiology — Mammography'],
            ['code' => '261QR0207X', 'type' => 'Organization', 'classification' => 'Ambulatory Health Care Facilities', 'specialty' => 'Radiology — Mobile Radiology'],
            ['code' => '261QR0208X', 'type' => 'Organization', 'classification' => 'Ambulatory Health Care Facilities', 'specialty' => 'Radiology — Mobile'],
            ['code' => '261QP2300X', 'type' => 'Organization', 'classification' => 'Ambulatory Health Care Facilities', 'specialty' => 'Primary Care Clinic'],
            ['code' => '261QP0905X', 'type' => 'Organization', 'classification' => 'Ambulatory Health Care Facilities', 'specialty' => 'Rehabilitation Clinic — Physical'],
            ['code' => '261QR0400X', 'type' => 'Organization', 'classification' => 'Ambulatory Health Care Facilities', 'specialty' => 'Rehabilitation Clinic'],
            ['code' => '261QR0401X', 'type' => 'Organization', 'classification' => 'Ambulatory Health Care Facilities', 'specialty' => 'Rehabilitation Clinic — Comprehensive Outpatient'],
            ['code' => '261QA0005X', 'type' => 'Organization', 'classification' => 'Ambulatory Health Care Facilities', 'specialty' => 'Ambulatory Family Planning Facility'],
            ['code' => '261QA0006X', 'type' => 'Organization', 'classification' => 'Ambulatory Health Care Facilities', 'specialty' => 'Ambulatory Fertility Facility'],
            ['code' => '261QA1903X', 'type' => 'Organization', 'classification' => 'Ambulatory Health Care Facilities', 'specialty' => 'Ambulatory Surgical'],
            ['code' => '261QC1500X', 'type' => 'Organization', 'classification' => 'Ambulatory Health Care Facilities', 'specialty' => 'Community Health Clinic'],
            ['code' => '261QC1800X', 'type' => 'Organization', 'classification' => 'Ambulatory Health Care Facilities', 'specialty' => 'Corporate Health Clinic'],
            ['code' => '261QD0000X', 'type' => 'Organization', 'classification' => 'Ambulatory Health Care Facilities', 'specialty' => 'Dental Clinic'],
            ['code' => '261QE0002X', 'type' => 'Organization', 'classification' => 'Ambulatory Health Care Facilities', 'specialty' => 'Emergency Care Clinic'],
            ['code' => '261QE0700X', 'type' => 'Organization', 'classification' => 'Ambulatory Health Care Facilities', 'specialty' => 'End-Stage Renal Disease (ESRD) Treatment'],
            ['code' => '261QH0100X', 'type' => 'Organization', 'classification' => 'Ambulatory Health Care Facilities', 'specialty' => 'Health Service Clinic'],
            ['code' => '261QM0850X', 'type' => 'Organization', 'classification' => 'Ambulatory Health Care Facilities', 'specialty' => 'Adult Mental Health Clinic'],
            ['code' => '261QM0855X', 'type' => 'Organization', 'classification' => 'Ambulatory Health Care Facilities', 'specialty' => 'Adolescent & Children Mental Health Clinic'],
            ['code' => '261QM1000X', 'type' => 'Organization', 'classification' => 'Ambulatory Health Care Facilities', 'specialty' => 'Migrant Health Clinic'],
            ['code' => '261QS0112X', 'type' => 'Organization', 'classification' => 'Ambulatory Health Care Facilities', 'specialty' => 'Oral & Maxillofacial Surgery Clinic'],
            ['code' => '261QS1200X', 'type' => 'Organization', 'classification' => 'Ambulatory Health Care Facilities', 'specialty' => 'Sleep Disorder Diagnostic Clinic'],
            ['code' => '261QX0100X', 'type' => 'Organization', 'classification' => 'Ambulatory Health Care Facilities', 'specialty' => 'Occupational Medicine Clinic'],
            ['code' => '261QX0200X', 'type' => 'Organization', 'classification' => 'Ambulatory Health Care Facilities', 'specialty' => 'Oncology Clinic'],
            ['code' => '261QX0203X', 'type' => 'Organization', 'classification' => 'Ambulatory Health Care Facilities', 'specialty' => 'Oncology Clinic — Radiation'],
            ['code' => '261QP2000X', 'type' => 'Organization', 'classification' => 'Ambulatory Health Care Facilities', 'specialty' => 'Physical Therapy Clinic'],
            ['code' => '261QP3300X', 'type' => 'Organization', 'classification' => 'Ambulatory Health Care Facilities', 'specialty' => 'Pain Clinic'],
            ['code' => '261QS0132X', 'type' => 'Organization', 'classification' => 'Ambulatory Health Care Facilities', 'specialty' => 'Ophthalmologic Surgery Clinic'],
            ['code' => '261QA0900X', 'type' => 'Organization', 'classification' => 'Ambulatory Health Care Facilities', 'specialty' => 'Public Health Clinic'],

            // ===================================================================
            // ORGANIZATIONS — CLINICAL LABORATORY
            // ===================================================================

            ['code' => '291U00000X', 'type' => 'Organization', 'classification' => 'Laboratories', 'specialty' => 'Clinical Medical Laboratory'],
            ['code' => '292200000X', 'type' => 'Organization', 'classification' => 'Laboratories', 'specialty' => 'Dental Laboratory'],
            ['code' => '291900000X', 'type' => 'Organization', 'classification' => 'Laboratories', 'specialty' => 'Military Clinical Medical Laboratory'],

            // ===================================================================
            // ORGANIZATIONS — PHARMACY
            // ===================================================================

            ['code' => '333600000X', 'type' => 'Organization', 'classification' => 'Suppliers', 'specialty' => 'Pharmacy'],
            ['code' => '3336C0002X', 'type' => 'Organization', 'classification' => 'Suppliers', 'specialty' => 'Pharmacy — Clinic Pharmacy'],
            ['code' => '3336C0003X', 'type' => 'Organization', 'classification' => 'Suppliers', 'specialty' => 'Pharmacy — Community/Retail Pharmacy'],
            ['code' => '3336C0004X', 'type' => 'Organization', 'classification' => 'Suppliers', 'specialty' => 'Pharmacy — Compounding Pharmacy'],
            ['code' => '3336H0001X', 'type' => 'Organization', 'classification' => 'Suppliers', 'specialty' => 'Pharmacy — Home Infusion Therapy'],
            ['code' => '3336I0012X', 'type' => 'Organization', 'classification' => 'Suppliers', 'specialty' => 'Pharmacy — Institutional Pharmacy'],
            ['code' => '3336L0003X', 'type' => 'Organization', 'classification' => 'Suppliers', 'specialty' => 'Pharmacy — Long Term Care Pharmacy'],
            ['code' => '3336M0002X', 'type' => 'Organization', 'classification' => 'Suppliers', 'specialty' => 'Pharmacy — Mail Order Pharmacy'],
            ['code' => '3336M0003X', 'type' => 'Organization', 'classification' => 'Suppliers', 'specialty' => 'Pharmacy — Managed Care Organization Pharmacy'],
            ['code' => '3336N0007X', 'type' => 'Organization', 'classification' => 'Suppliers', 'specialty' => 'Pharmacy — Nuclear Pharmacy'],
            ['code' => '3336S0011X', 'type' => 'Organization', 'classification' => 'Suppliers', 'specialty' => 'Pharmacy — Specialty Pharmacy'],

            // ===================================================================
            // ORGANIZATIONS — HOME HEALTH & LONG TERM CARE
            // ===================================================================

            ['code' => '251E00000X', 'type' => 'Organization', 'classification' => 'Agencies', 'specialty' => 'Home Health Agency'],
            ['code' => '251B00000X', 'type' => 'Organization', 'classification' => 'Agencies', 'specialty' => 'Case Management Agency'],
            ['code' => '251C00000X', 'type' => 'Organization', 'classification' => 'Agencies', 'specialty' => 'Day Training, Developmentally Disabled Services'],
            ['code' => '251F00000X', 'type' => 'Organization', 'classification' => 'Agencies', 'specialty' => 'Home Health Aide Agency'],
            ['code' => '251G00000X', 'type' => 'Organization', 'classification' => 'Agencies', 'specialty' => 'Hospice, Home Health Care Agency'],
            ['code' => '251S00000X', 'type' => 'Organization', 'classification' => 'Agencies', 'specialty' => 'Community/Behavioral Health'],
            ['code' => '251V00000X', 'type' => 'Organization', 'classification' => 'Agencies', 'specialty' => 'Voluntary or Charitable Agency'],
            ['code' => '314000000X', 'type' => 'Organization', 'classification' => 'Nursing & Custodial Care Facilities', 'specialty' => 'Skilled Nursing Facility'],
            ['code' => '3140N1450X', 'type' => 'Organization', 'classification' => 'Nursing & Custodial Care Facilities', 'specialty' => 'Skilled Nursing Facility — Nursing Care, Pediatric'],
            ['code' => '311500000X', 'type' => 'Organization', 'classification' => 'Nursing & Custodial Care Facilities', 'specialty' => 'Alzheimer Center (Dementia Center)'],
            ['code' => '311Z00000X', 'type' => 'Organization', 'classification' => 'Nursing & Custodial Care Facilities', 'specialty' => 'Custodial Care Facility'],
            ['code' => '310400000X', 'type' => 'Organization', 'classification' => 'Nursing & Custodial Care Facilities', 'specialty' => 'Assisted Living Facility'],
            ['code' => '315D00000X', 'type' => 'Organization', 'classification' => 'Nursing & Custodial Care Facilities', 'specialty' => 'Hospice, Inpatient'],
            ['code' => '315P00000X', 'type' => 'Organization', 'classification' => 'Nursing & Custodial Care Facilities', 'specialty' => 'Intermediate Care Facility, Mentally Retarded'],

            // ===================================================================
            // ORGANIZATIONS — RESIDENTIAL TREATMENT FACILITIES
            // ===================================================================

            ['code' => '320600000X', 'type' => 'Organization', 'classification' => 'Residential Treatment Facilities', 'specialty' => 'Residential Treatment — Mental Illness'],
            ['code' => '320900000X', 'type' => 'Organization', 'classification' => 'Residential Treatment Facilities', 'specialty' => 'Residential Treatment — Substance Abuse'],
            ['code' => '323P00000X', 'type' => 'Organization', 'classification' => 'Residential Treatment Facilities', 'specialty' => 'Psychiatric Residential Treatment'],
            ['code' => '320800000X', 'type' => 'Organization', 'classification' => 'Residential Treatment Facilities', 'specialty' => 'Residential Treatment — Intellectual and/or Developmental Disabilities'],
            ['code' => '322D00000X', 'type' => 'Organization', 'classification' => 'Residential Treatment Facilities', 'specialty' => 'Residential Treatment — Emotionally Disturbed Children'],

            // ===================================================================
            // ORGANIZATIONS — HOME HEALTH / HCBS WAIVER PROVIDERS
            // ===================================================================

            ['code' => '3104A0630X', 'type' => 'Organization', 'classification' => 'Home Health / HCBS Waiver Providers', 'specialty' => 'Behavioral Health & Social Service (HCBS)'],
            ['code' => '3104A0625X', 'type' => 'Organization', 'classification' => 'Home Health / HCBS Waiver Providers', 'specialty' => 'Personal Care Attendant'],

            // ===================================================================
            // ORGANIZATIONS — GROUP PRACTICES
            // ===================================================================

            ['code' => '193200000X', 'type' => 'Organization', 'classification' => 'Group', 'specialty' => 'Group Practice — Multi-Specialty'],
            ['code' => '193400000X', 'type' => 'Organization', 'classification' => 'Group', 'specialty' => 'Group Practice — Single Specialty'],

            // ===================================================================
            // ORGANIZATIONS — MANAGED CARE
            // ===================================================================

            ['code' => '302F00000X', 'type' => 'Organization', 'classification' => 'Managed Care Organizations', 'specialty' => 'Exclusive Provider Organization'],
            ['code' => '302R00000X', 'type' => 'Organization', 'classification' => 'Managed Care Organizations', 'specialty' => 'Health Maintenance Organization'],
            ['code' => '305S00000X', 'type' => 'Organization', 'classification' => 'Managed Care Organizations', 'specialty' => 'Point of Service'],
            ['code' => '305R00000X', 'type' => 'Organization', 'classification' => 'Managed Care Organizations', 'specialty' => 'Preferred Provider Organization'],

            // ===================================================================
            // ORGANIZATIONS — TRANSPORTATION SERVICES
            // ===================================================================

            ['code' => '341600000X', 'type' => 'Organization', 'classification' => 'Transportation Services', 'specialty' => 'Ambulance — Air Transport'],
            ['code' => '341800000X', 'type' => 'Organization', 'classification' => 'Transportation Services', 'specialty' => 'Ambulance — Land Transport'],
            ['code' => '343900000X', 'type' => 'Organization', 'classification' => 'Transportation Services', 'specialty' => 'Non-Emergency Medical Transport (VAN)'],
            ['code' => '344600000X', 'type' => 'Organization', 'classification' => 'Transportation Services', 'specialty' => 'Taxi'],
        ];

        foreach ($codes as $code) {
            DB::table('taxonomy_codes')->updateOrInsert(
                ['code' => $code['code']],
                $code
            );
        }
    }
}

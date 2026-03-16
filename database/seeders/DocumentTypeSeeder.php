<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            // Identity & Personal
            ['slug' => 'government_id',         'name' => 'Government-Issued Photo ID',       'category' => 'identity',    'description' => 'Valid driver\'s license, passport, or state ID',                           'is_required' => true,  'has_expiration' => true,  'typical_validity_months' => 96,  'sort_order' => 1],
            ['slug' => 'ssn_card',              'name' => 'Social Security Card',              'category' => 'identity',    'description' => 'Social Security card or official SSA letter',                               'is_required' => false, 'has_expiration' => false, 'typical_validity_months' => null, 'sort_order' => 2],
            ['slug' => 'passport',              'name' => 'Passport',                          'category' => 'identity',    'description' => 'Valid US or foreign passport',                                              'is_required' => false, 'has_expiration' => true,  'typical_validity_months' => 120, 'sort_order' => 3],
            ['slug' => 'visa_work_auth',        'name' => 'Visa / Work Authorization',         'category' => 'identity',    'description' => 'H-1B, J-1, or other work authorization documents',                         'is_required' => false, 'has_expiration' => true,  'typical_validity_months' => 36,  'sort_order' => 4],

            // Education & Training
            ['slug' => 'medical_degree',        'name' => 'Medical Degree Diploma',            'category' => 'education',   'description' => 'MD, DO, DNP, PhD, PsyD, MSW, or equivalent degree',                       'is_required' => true,  'has_expiration' => false, 'typical_validity_months' => null, 'sort_order' => 10],
            ['slug' => 'residency_cert',        'name' => 'Residency Completion Certificate',  'category' => 'education',   'description' => 'Certificate of residency/fellowship completion',                           'is_required' => false, 'has_expiration' => false, 'typical_validity_months' => null, 'sort_order' => 11],
            ['slug' => 'fellowship_cert',       'name' => 'Fellowship Completion Certificate', 'category' => 'education',   'description' => 'Subspecialty fellowship completion documentation',                          'is_required' => false, 'has_expiration' => false, 'typical_validity_months' => null, 'sort_order' => 12],
            ['slug' => 'transcript',            'name' => 'Official Transcripts',              'category' => 'education',   'description' => 'Official academic transcripts from degree-granting institution',            'is_required' => false, 'has_expiration' => false, 'typical_validity_months' => null, 'sort_order' => 13],
            ['slug' => 'cme_certificates',      'name' => 'CME/CE Certificates',               'category' => 'education',   'description' => 'Continuing medical education or continuing education credits',              'is_required' => false, 'has_expiration' => true,  'typical_validity_months' => 24,  'sort_order' => 14],

            // Licenses & Certifications
            ['slug' => 'state_license',         'name' => 'State Professional License',        'category' => 'license',     'description' => 'Active state medical/professional license',                                'is_required' => true,  'has_expiration' => true,  'typical_validity_months' => 24,  'sort_order' => 20],
            ['slug' => 'dea_certificate',       'name' => 'DEA Registration Certificate',      'category' => 'license',     'description' => 'Drug Enforcement Administration registration for controlled substances',    'is_required' => false, 'has_expiration' => true,  'typical_validity_months' => 36,  'sort_order' => 21],
            ['slug' => 'state_cds',             'name' => 'State Controlled Substance License','category' => 'license',     'description' => 'State-level controlled dangerous substance registration',                  'is_required' => false, 'has_expiration' => true,  'typical_validity_months' => 24,  'sort_order' => 22],
            ['slug' => 'board_certification',   'name' => 'Board Certification',               'category' => 'license',     'description' => 'Specialty board certification (ABMS, ANCC, NBCC, etc.)',                    'is_required' => false, 'has_expiration' => true,  'typical_validity_months' => 120, 'sort_order' => 23],
            ['slug' => 'npi_confirmation',      'name' => 'NPI Confirmation Letter',           'category' => 'license',     'description' => 'NPPES NPI number confirmation from CMS',                                   'is_required' => true,  'has_expiration' => false, 'typical_validity_months' => null, 'sort_order' => 24],
            ['slug' => 'clia_certificate',      'name' => 'CLIA Certificate',                  'category' => 'license',     'description' => 'Clinical Laboratory Improvement Amendments certificate',                    'is_required' => false, 'has_expiration' => true,  'typical_validity_months' => 24,  'sort_order' => 25],
            ['slug' => 'bls_acls_cert',         'name' => 'BLS/ACLS Certification',            'category' => 'license',     'description' => 'Basic Life Support or Advanced Cardiac Life Support certification',         'is_required' => false, 'has_expiration' => true,  'typical_validity_months' => 24,  'sort_order' => 26],
            ['slug' => 'collaborative_agreement','name' => 'Collaborative Practice Agreement', 'category' => 'license',     'description' => 'Supervising physician collaborative agreement (required in some states)',    'is_required' => false, 'has_expiration' => true,  'typical_validity_months' => 12,  'sort_order' => 27],

            // Insurance & Liability
            ['slug' => 'malpractice_coi',       'name' => 'Malpractice Insurance COI',         'category' => 'insurance',   'description' => 'Certificate of insurance for professional liability / malpractice coverage','is_required' => true,  'has_expiration' => true,  'typical_validity_months' => 12,  'sort_order' => 30],
            ['slug' => 'malpractice_face',      'name' => 'Malpractice Policy Face Sheet',     'category' => 'insurance',   'description' => 'Full policy face sheet showing coverage limits and terms',                 'is_required' => false, 'has_expiration' => true,  'typical_validity_months' => 12,  'sort_order' => 31],
            ['slug' => 'tail_coverage',         'name' => 'Tail Coverage Documentation',       'category' => 'insurance',   'description' => 'Extended reporting period / tail coverage for prior claims-made policies',   'is_required' => false, 'has_expiration' => false, 'typical_validity_months' => null, 'sort_order' => 32],
            ['slug' => 'general_liability',     'name' => 'General Liability Insurance',       'category' => 'insurance',   'description' => 'Commercial general liability insurance for practice/facility',              'is_required' => false, 'has_expiration' => true,  'typical_validity_months' => 12,  'sort_order' => 33],
            ['slug' => 'workers_comp',          'name' => 'Workers Compensation Insurance',    'category' => 'insurance',   'description' => 'Workers compensation coverage documentation',                               'is_required' => false, 'has_expiration' => true,  'typical_validity_months' => 12,  'sort_order' => 34],

            // Employment & Practice
            ['slug' => 'cv_resume',             'name' => 'Curriculum Vitae / Resume',         'category' => 'employment',  'description' => 'Current CV with complete work history (no gaps > 6 months)',                'is_required' => true,  'has_expiration' => false, 'typical_validity_months' => null, 'sort_order' => 40],
            ['slug' => 'employment_agreement',  'name' => 'Employment Agreement',              'category' => 'employment',  'description' => 'Current employment contract or letter of engagement',                       'is_required' => false, 'has_expiration' => false, 'typical_validity_months' => null, 'sort_order' => 41],
            ['slug' => 'hospital_privileges',   'name' => 'Hospital Privileges Letter',        'category' => 'employment',  'description' => 'Active hospital admitting/staff privileges letter',                         'is_required' => false, 'has_expiration' => true,  'typical_validity_months' => 24,  'sort_order' => 42],
            ['slug' => 'peer_references',       'name' => 'Peer Reference Letters',            'category' => 'employment',  'description' => 'Professional peer references (typically 3 required)',                        'is_required' => false, 'has_expiration' => false, 'typical_validity_months' => null, 'sort_order' => 43],
            ['slug' => 'w9_form',               'name' => 'W-9 Tax Form',                     'category' => 'employment',  'description' => 'IRS W-9 form with Tax ID / EIN for payment setup',                         'is_required' => true,  'has_expiration' => false, 'typical_validity_months' => null, 'sort_order' => 44],

            // Compliance & Verification
            ['slug' => 'background_check',      'name' => 'Background Check Report',           'category' => 'compliance',  'description' => 'Criminal background check and screening results',                           'is_required' => false, 'has_expiration' => true,  'typical_validity_months' => 12,  'sort_order' => 50],
            ['slug' => 'oig_exclusion',         'name' => 'OIG Exclusion Check',               'category' => 'compliance',  'description' => 'Office of Inspector General exclusion verification',                        'is_required' => true,  'has_expiration' => true,  'typical_validity_months' => 1,   'sort_order' => 51],
            ['slug' => 'sam_check',             'name' => 'SAM/GSA Exclusion Check',           'category' => 'compliance',  'description' => 'System for Award Management debarment verification',                        'is_required' => true,  'has_expiration' => true,  'typical_validity_months' => 1,   'sort_order' => 52],
            ['slug' => 'npdb_report',           'name' => 'NPDB Report',                       'category' => 'compliance',  'description' => 'National Practitioner Data Bank query report',                              'is_required' => false, 'has_expiration' => true,  'typical_validity_months' => 36,  'sort_order' => 53],
            ['slug' => 'drug_screening',        'name' => 'Drug Screening Results',            'category' => 'compliance',  'description' => 'Pre-employment or random drug screening documentation',                     'is_required' => false, 'has_expiration' => true,  'typical_validity_months' => 12,  'sort_order' => 54],
            ['slug' => 'hipaa_training',        'name' => 'HIPAA Training Certificate',        'category' => 'compliance',  'description' => 'HIPAA privacy and security training completion certificate',                'is_required' => false, 'has_expiration' => true,  'typical_validity_months' => 12,  'sort_order' => 55],
            ['slug' => 'osha_training',         'name' => 'OSHA Training Certificate',         'category' => 'compliance',  'description' => 'Occupational safety and bloodborne pathogen training',                      'is_required' => false, 'has_expiration' => true,  'typical_validity_months' => 12,  'sort_order' => 56],
            ['slug' => 'attestation_form',      'name' => 'Credentialing Attestation',         'category' => 'compliance',  'description' => 'Signed attestation of accuracy for credentialing application',              'is_required' => true,  'has_expiration' => true,  'typical_validity_months' => 6,   'sort_order' => 57],

            // Facility-Specific
            ['slug' => 'facility_license',      'name' => 'Facility/Practice License',         'category' => 'facility',    'description' => 'State facility license or business license for practice location',          'is_required' => false, 'has_expiration' => true,  'typical_validity_months' => 12,  'sort_order' => 60],
            ['slug' => 'accreditation_cert',    'name' => 'Accreditation Certificate',         'category' => 'facility',    'description' => 'Joint Commission, AAAHC, or NCQA accreditation certificate',               'is_required' => false, 'has_expiration' => true,  'typical_validity_months' => 36,  'sort_order' => 61],
            ['slug' => 'fire_safety_cert',      'name' => 'Fire Safety / Inspection Report',   'category' => 'facility',    'description' => 'Local fire marshal inspection and safety compliance certificate',           'is_required' => false, 'has_expiration' => true,  'typical_validity_months' => 12,  'sort_order' => 62],

            // Other / Payer-Specific
            ['slug' => 'caqh_profile',          'name' => 'CAQH ProView Profile',              'category' => 'other',       'description' => 'CAQH ProView profile ID and attestation status',                            'is_required' => false, 'has_expiration' => true,  'typical_validity_months' => 3,   'sort_order' => 70],
            ['slug' => 'provider_photo',        'name' => 'Professional Headshot Photo',       'category' => 'other',       'description' => 'Professional headshot for provider directories',                             'is_required' => false, 'has_expiration' => false, 'typical_validity_months' => null, 'sort_order' => 71],
            ['slug' => 'practice_tin_letter',   'name' => 'IRS EIN Confirmation Letter',       'category' => 'other',       'description' => 'IRS confirmation letter for practice Tax ID / EIN',                         'is_required' => false, 'has_expiration' => false, 'typical_validity_months' => null, 'sort_order' => 72],
            ['slug' => 'immunization_records',  'name' => 'Immunization Records',              'category' => 'other',       'description' => 'TB test, Hep B, and required immunization documentation',                   'is_required' => false, 'has_expiration' => true,  'typical_validity_months' => 12,  'sort_order' => 73],
        ];

        foreach ($types as $type) {
            DB::table('document_types')->updateOrInsert(
                ['slug' => $type['slug']],
                $type
            );
        }
    }
}

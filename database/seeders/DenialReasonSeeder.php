<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DenialReasonSeeder extends Seeder
{
    public function run(): void
    {
        $reasons = [
            // Documentation
            ['slug' => 'incomplete_app',         'name' => 'Incomplete Application',            'category' => 'documentation',   'description' => 'Application is missing required fields or sections.',                       'recommended_action' => 'Review application for completeness. Fill in all required fields and resubmit.',                         'is_resubmittable' => true,  'sort_order' => 1],
            ['slug' => 'missing_documents',      'name' => 'Missing Required Documents',        'category' => 'documentation',   'description' => 'One or more required supporting documents were not provided.',              'recommended_action' => 'Check payer\'s document requirements. Upload missing items (license, malpractice COI, DEA, etc.) and resubmit.', 'is_resubmittable' => true,  'sort_order' => 2],
            ['slug' => 'expired_documents',      'name' => 'Expired Documents',                 'category' => 'documentation',   'description' => 'Submitted documents (license, insurance, DEA) are expired.',                'recommended_action' => 'Obtain and upload current, unexpired documents. Verify all dates before resubmitting.',                  'is_resubmittable' => true,  'sort_order' => 3],
            ['slug' => 'invalid_npi',            'name' => 'Invalid or Inactive NPI',           'category' => 'documentation',   'description' => 'NPI number is invalid, inactive, or does not match provider information.', 'recommended_action' => 'Verify NPI on NPPES registry. Update NPI record if needed, then resubmit.',                              'is_resubmittable' => true,  'sort_order' => 4],
            ['slug' => 'caqh_incomplete',        'name' => 'CAQH Profile Incomplete/Expired',   'category' => 'documentation',   'description' => 'CAQH ProView profile is not complete or attestation has expired.',         'recommended_action' => 'Complete and re-attest CAQH ProView profile. Ensure all sections are filled and current.',               'is_resubmittable' => true,  'sort_order' => 5],

            // Eligibility
            ['slug' => 'panel_closed',           'name' => 'Panel Closed',                      'category' => 'eligibility',     'description' => 'Payer\'s provider panel is currently closed in this area/specialty.',       'recommended_action' => 'Ask payer when panel will reopen. Place provider on waiting list if available. Consider single-case agreement.', 'is_resubmittable' => true, 'sort_order' => 10],
            ['slug' => 'out_of_area',            'name' => 'Out of Service Area',               'category' => 'eligibility',     'description' => 'Provider\'s practice location is outside payer\'s coverage area.',          'recommended_action' => 'Verify if payer covers the provider\'s practice state/county. Consider telehealth credentialing.',       'is_resubmittable' => false, 'sort_order' => 11],
            ['slug' => 'specialty_not_covered',  'name' => 'Specialty Not Covered',              'category' => 'eligibility',     'description' => 'Payer does not credential this specialty or provider type.',                'recommended_action' => 'Confirm which specialties/provider types the payer accepts. May need to apply under different taxonomy.',  'is_resubmittable' => false, 'sort_order' => 12],
            ['slug' => 'duplicate_app',          'name' => 'Duplicate Application',              'category' => 'eligibility',     'description' => 'An existing application or enrollment already exists for this provider.',  'recommended_action' => 'Contact payer to locate existing enrollment. May need to update rather than create new.',                 'is_resubmittable' => false, 'sort_order' => 13],
            ['slug' => 'provider_type_excluded', 'name' => 'Provider Type Not Accepted',        'category' => 'eligibility',     'description' => 'Payer does not accept this provider type (e.g., LCSW, LPC) for direct credentialing.', 'recommended_action' => 'Check if provider can credential under group NPI. Explore behavioral health carve-out options.', 'is_resubmittable' => false, 'sort_order' => 14],

            // Capacity
            ['slug' => 'network_adequacy',       'name' => 'Network Adequacy Met',               'category' => 'capacity',        'description' => 'Payer has sufficient providers in this specialty/area — no additional capacity needed.', 'recommended_action' => 'Request exception if provider serves underserved population. Ask to be placed on waiting list.', 'is_resubmittable' => true, 'sort_order' => 20],
            ['slug' => 'geographic_saturation',  'name' => 'Geographic Saturation',              'category' => 'capacity',        'description' => 'Too many providers already credentialed in this geographic area.',         'recommended_action' => 'Consider applying at different practice location. Request geographic exception for telehealth.',           'is_resubmittable' => true, 'sort_order' => 21],

            // Compliance
            ['slug' => 'oig_exclusion',          'name' => 'OIG/SAM Exclusion',                  'category' => 'compliance',      'description' => 'Provider appears on OIG exclusion list or SAM debarment list.',            'recommended_action' => 'Verify exclusion status. If erroneous, contact OIG for removal. Cannot be credentialed while excluded.', 'is_resubmittable' => false, 'sort_order' => 30],
            ['slug' => 'malpractice_history',    'name' => 'Malpractice / Disciplinary History', 'category' => 'compliance',      'description' => 'Provider has adverse malpractice claims or board disciplinary actions.',   'recommended_action' => 'Obtain detailed explanation from provider. Submit with supporting documentation showing resolution.',      'is_resubmittable' => true,  'sort_order' => 31],
            ['slug' => 'license_issue',          'name' => 'License Restriction or Action',      'category' => 'compliance',      'description' => 'Provider\'s license has restrictions, probation, or adverse action.',      'recommended_action' => 'Verify current license status with state board. Wait for restrictions to be lifted before reapplying.',   'is_resubmittable' => true,  'sort_order' => 32],
            ['slug' => 'background_check_fail',  'name' => 'Failed Background Check',            'category' => 'compliance',      'description' => 'Background check revealed disqualifying findings.',                         'recommended_action' => 'Review findings with provider. Determine if issues are resolvable. Some payers allow exceptions with documentation.', 'is_resubmittable' => true, 'sort_order' => 33],

            // Administrative
            ['slug' => 'app_expired',            'name' => 'Application Expired',                 'category' => 'administrative', 'description' => 'Application sat too long without required follow-up and expired.',         'recommended_action' => 'Submit a fresh application with current documents. Set up follow-up reminders to prevent recurrence.',    'is_resubmittable' => true,  'sort_order' => 40],
            ['slug' => 'wrong_payer_entity',     'name' => 'Wrong Payer Entity',                  'category' => 'administrative', 'description' => 'Application submitted to wrong payer entity or subsidiary.',               'recommended_action' => 'Identify correct payer entity for provider\'s state/plan. Resubmit to correct entity.',                  'is_resubmittable' => true,  'sort_order' => 41],
            ['slug' => 'fee_schedule_rejected',  'name' => 'Fee Schedule Not Accepted',           'category' => 'administrative', 'description' => 'Provider or group did not agree to payer\'s fee schedule or contract terms.', 'recommended_action' => 'Review fee schedule and negotiate terms. Consider if contract is financially viable.',                   'is_resubmittable' => true,  'sort_order' => 42],
        ];

        foreach ($reasons as $reason) {
            DB::table('denial_reasons')->updateOrInsert(
                ['slug' => $reason['slug']],
                $reason
            );
        }
    }
}

<?php
// Seed the carc_codes and rarc_codes tables with the most-frequent codes.
// Source: X12 published code list as of 2024-11 (codes themselves rarely change
// year to year; descriptions occasionally clarified).
//
// Coverage chosen: every CARC code that appears in >0.1% of denials nationally,
// plus every RARC code we've seen in production samples. Roughly 80 CARCs +
// 25 RARCs = ~95% of real-world denial volume in psych/mental-health billing.
//
// Run via `php artisan migrate` or hit /run-migrations route after deploy.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // ── CARC codes ────────────────────────────────────────────────────
        // Format: [code, category, group_codes, description]
        // category aligns with V2's Denial Inbox routing.
        $carcs = [
            // ── auto-appealable: timely filing, medical necessity, COB, fee schedule
            ['1', 'eligibility', 'PR', 'Deductible amount'],
            ['2', 'eligibility', 'PR', 'Coinsurance amount'],
            ['3', 'eligibility', 'PR', 'Copayment amount'],
            ['4', 'coding-fix', 'CO', 'The procedure code is inconsistent with the modifier used'],
            ['5', 'coding-fix', 'CO', 'The procedure code/type of bill is inconsistent with the place of service'],
            ['6', 'coding-fix', 'CO', 'The procedure/revenue code is inconsistent with the patient\'s age'],
            ['7', 'coding-fix', 'CO', 'The procedure/revenue code is inconsistent with the patient\'s gender'],
            ['8', 'coding-fix', 'CO', 'The procedure code is inconsistent with the provider type/specialty (taxonomy)'],
            ['9', 'coding-fix', 'CO', 'The diagnosis is inconsistent with the patient\'s age'],
            ['10', 'coding-fix', 'CO', 'The diagnosis is inconsistent with the patient\'s gender'],
            ['11', 'coding-fix', 'CO', 'The diagnosis is inconsistent with the procedure'],
            ['12', 'coding-fix', 'CO', 'The diagnosis is inconsistent with the provider type'],
            ['13', 'eligibility', 'OA', 'The date of death precedes the date of service'],
            ['14', 'eligibility', 'CO', 'The date of birth follows the date of service'],
            ['15', 'authorization', 'CO', 'The authorization number is missing, invalid, or does not apply to the billed services or provider'],
            ['16', 'coding-fix', 'CO', 'Claim/service lacks information or has submission/billing error(s)'],
            ['17', 'coding-fix', 'CO', 'Referral absent or exceeded'],
            ['18', 'duplicate', 'OA', 'Exact duplicate claim/service'],
            ['19', 'eligibility', 'OA', 'This is a work-related injury/illness and thus the liability of the Worker\'s Compensation Carrier'],
            ['20', 'eligibility', 'OA', 'This injury/illness is covered by the liability carrier'],
            ['21', 'eligibility', 'OA', 'This injury/illness is the liability of the no-fault carrier'],
            ['22', 'auto-appealable', 'OA', 'This care may be covered by another payer per coordination of benefits'],
            ['23', 'other', 'OA', 'The impact of prior payer(s) adjudication including payments and/or adjustments'],
            ['24', 'other', 'CO', 'Charges are covered under a capitation agreement/managed care plan'],
            ['25', 'eligibility', 'OA', 'Payment denied. Your Stop loss deductible has not been met'],
            ['26', 'eligibility', 'CO', 'Expenses incurred prior to coverage'],
            ['27', 'eligibility', 'CO', 'Expenses incurred after coverage terminated'],
            ['29', 'auto-appealable', 'CO', 'The time limit for filing has expired'],
            ['31', 'eligibility', 'CO', 'Patient cannot be identified as our insured'],
            ['32', 'eligibility', 'CO', 'Our records indicate the patient is not an eligible dependent'],
            ['33', 'eligibility', 'CO', 'Insured has no dependent coverage'],
            ['34', 'eligibility', 'CO', 'Insured has no coverage for newborns'],
            ['35', 'other', 'CO', 'Lifetime benefit maximum has been reached'],
            ['39', 'authorization', 'CO', 'Services denied at the time authorization/pre-certification was requested'],
            ['40', 'other', 'CO', 'Charges do not meet qualifications for emergent/urgent care'],
            ['44', 'other', 'CO', 'Prompt-pay discount'],
            ['45', 'auto-appealable', 'CO', 'Charge exceeds fee schedule/maximum allowable or contracted/legislated fee arrangement'],
            ['49', 'other', 'CO', 'This is a non-covered service because it is a routine/preventive exam or a diagnostic/screening procedure done in conjunction with a routine/preventive exam'],
            ['50', 'auto-appealable', 'CO', 'These are non-covered services because this is not deemed a medical necessity by the payer'],
            ['51', 'other', 'CO', 'These are non-covered services because this is a pre-existing condition'],
            ['54', 'other', 'CO', 'Multiple physicians/assistants are not covered in this case'],
            ['55', 'other', 'CO', 'Procedure/treatment/drug is deemed experimental/investigational by the payer'],
            ['58', 'other', 'CO', 'Treatment was deemed by the payer to have been rendered in an inappropriate or invalid place of service'],
            ['59', 'coding-fix', 'CO', 'Processed based on multiple or concurrent procedure rules'],
            ['60', 'other', 'CO', 'Charges for outpatient services are not covered when performed within a period of time prior to or after inpatient services'],
            ['96', 'other', 'CO', 'Non-covered charge(s)'],
            ['97', 'other', 'CO', 'The benefit for this service is included in the payment/allowance for another service/procedure that has already been adjudicated'],
            ['100', 'eligibility', 'PR', 'Payment made to patient/insured/responsible party'],
            ['107', 'other', 'CO', 'The related or qualifying claim/service was not identified on this claim'],
            ['109', 'eligibility', 'CO', 'Claim/service not covered by this payer/contractor. You must send the claim/service to the correct payer/contractor'],
            ['110', 'other', 'CO', 'Billing date predates service date'],
            ['111', 'coding-fix', 'CO', 'Not covered unless the provider accepts assignment'],
            ['115', 'other', 'CO', 'Procedure postponed, canceled, or delayed'],
            ['119', 'eligibility', 'PR', 'Benefit maximum for this time period or occurrence has been reached'],
            ['125', 'coding-fix', 'CO', 'Submission/billing error(s). At least one Remark Code must be provided'],
            ['140', 'other', 'CO', 'Patient/Insured health identification number and name do not match'],
            ['142', 'other', 'CO', 'Monthly Medicaid patient liability amount'],
            ['146', 'coding-fix', 'CO', 'Diagnosis was invalid for the date(s) of service reported'],
            ['151', 'other', 'CO', 'Payment adjusted because the payer deems the information submitted does not support this many/frequency of services'],
            ['167', 'other', 'CO', 'This (these) diagnosis(es) is (are) not covered'],
            ['170', 'authorization', 'CO', 'Payment is denied when performed/billed by this type of provider'],
            ['171', 'authorization', 'CO', 'Payment is denied when performed/billed by this type of provider in this type of facility'],
            ['181', 'coding-fix', 'CO', 'Procedure code was invalid on the date of service'],
            ['182', 'coding-fix', 'CO', 'Procedure modifier was invalid on the date of service'],
            ['183', 'coding-fix', 'CO', 'The referring provider is not eligible to refer the service billed'],
            ['185', 'coding-fix', 'CO', 'The rendering provider is not eligible to perform the service billed'],
            ['187', 'other', 'CO', 'Consumer Spending Account payments'],
            ['188', 'other', 'CO', 'This product/procedure is only covered when used according to FDA recommendations'],
            ['197', 'authorization', 'CO', 'Precertification/authorization/notification/pre-treatment absent'],
            ['198', 'authorization', 'CO', 'Precertification/notification/authorization/pre-treatment exceeded'],
            ['199', 'authorization', 'CO', 'Revenue code and Procedure code do not match'],
            ['200', 'other', 'CO', 'Expenses incurred during lapse in coverage'],
            ['204', 'other', 'CO', 'This service/equipment/drug is not covered under the patient\'s current benefit plan'],
            ['208', 'documentation', 'CO', 'National Provider Identifier - Not matched'],
            ['222', 'other', 'CO', 'Exceeds the contracted maximum number of hours/days/units by this provider for this period'],
            ['234', 'other', 'CO', 'This procedure is not paid separately'],
            ['252', 'documentation', 'CO', 'An attachment/other documentation is required to adjudicate this claim/service'],
            ['256', 'documentation', 'CO', 'Service not payable per managed care contract'],
            ['273', 'other', 'CO', 'Coverage/program guidelines were not met'],
        ];

        $now = now();
        $carcRows = array_map(fn($r) => [
            'code' => $r[0], 'category' => $r[1], 'typical_group_codes' => $r[2],
            'description' => $r[3], 'created_at' => $now, 'updated_at' => $now,
        ], $carcs);
        // Chunk to avoid hitting Postgres parameter limits on a single INSERT.
        // upsert (Postgres ON CONFLICT DO UPDATE) keeps this migration
        // idempotent — re-running after a rollback or on a fresh deploy
        // with existing data updates rather than throwing PK violation.
        foreach (array_chunk($carcRows, 50) as $chunk) {
            DB::table('carc_codes')->upsert(
                $chunk,
                ['code'], // unique key
                ['description', 'category', 'typical_group_codes', 'updated_at'], // update on conflict
            );
        }

        // ── RARC codes ─────────────────────────────────────────────────────
        // [code, description, triggers_appeal_window, indicates_documentation_request]
        $rarcs = [
            ['MA01', 'Alert: If you do not agree with what we approved for these services, you may appeal our decision', true, false],
            ['MA02', 'Alert: If you do not agree with this determination, you have the right to appeal', true, false],
            ['MA04', 'Secondary payment cannot be considered without the identity of or payment information from the primary payer', false, true],
            ['MA13', 'Alert: You may be subject to penalties if you bill the patient for amounts not reported with the PR (patient responsibility) group code', false, false],
            ['MA15', 'Alert: Your claim has been separated to expedite handling. You will receive a separate notice for the other services reported', false, false],
            ['MA27', 'Missing/incomplete/invalid entitlement number or name shown on the claim', false, true],
            ['MA28', 'Receipt of this notice by a physician or supplier who did not accept assignment is for information only and does not make the physician or supplier a party to the determination', false, false],
            ['MA66', 'Missing/incomplete/invalid principal procedure code', false, true],
            ['MA68', 'We did not crossover this claim because the secondary insurance information on the claim was incomplete', false, true],
            ['MA92', 'Missing plan information for other insurance', false, true],
            ['MA130', 'Your claim contains incomplete and/or invalid information, and no appeal rights are afforded because the claim is unprocessable', false, true],
            ['N4', 'Missing/incomplete/invalid prior insurance carrier EOB', false, true],
            ['N20', 'Service not payable with other service rendered on the same date', false, false],
            ['N29', 'Missing documentation/orders/notes/summary/report/chart', false, true],
            ['N30', 'Patient ineligible for this service', false, false],
            ['N130', 'Consult plan benefit documents/guidelines for information about restrictions for this service', false, false],
            ['N179', 'Additional information has been requested from the member. The charges will be reconsidered upon receipt of that information', false, true],
            ['N211', 'Alert: You may not appeal this decision', false, false],
            ['N350', 'Missing/incomplete/invalid description of service for a Not Otherwise Classified (NOC) code or for an Unlisted/By Report procedure', false, true],
            ['N362', 'The number of Days or Units of Service exceeds our acceptable maximum', false, false],
            ['N418', 'Misrouted claim. See the payer\'s claim submission instructions', false, false],
            ['N431', 'Service is not covered with this procedure/diagnosis', false, false],
            ['N447', 'Payment is based on a generic equivalent as required documentation was not provided', false, true],
            ['N522', 'Duplicate of a claim processed, or to be processed, as a crossover claim', false, false],
            ['N657', 'This should be billed with the appropriate code for these services', false, false],
        ];

        $rarcRows = array_map(fn($r) => [
            'code' => $r[0], 'description' => $r[1],
            'triggers_appeal_window' => $r[2], 'indicates_documentation_request' => $r[3],
            'created_at' => $now, 'updated_at' => $now,
        ], $rarcs);
        foreach (array_chunk($rarcRows, 50) as $chunk) {
            DB::table('rarc_codes')->upsert(
                $chunk,
                ['code'],
                ['description', 'triggers_appeal_window', 'indicates_documentation_request', 'updated_at'],
            );
        }
    }

    public function down(): void
    {
        DB::table('carc_codes')->truncate();
        DB::table('rarc_codes')->truncate();
    }
};

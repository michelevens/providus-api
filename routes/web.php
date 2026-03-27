<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/seed-demo-billing', function () {
    try {
        // Find agency — use query param or fall back to demo
        $agencyId = request()->query('agency_id');
        $agency = $agencyId
            ? \App\Models\Agency::find($agencyId)
            : \App\Models\Agency::where('slug', 'demo-agency')->first();
        if (!$agency) return response()->json(['success' => false, 'error' => 'Agency not found.'], 404);
        $aid = $agency->id;

        $org = \App\Models\Organization::where('agency_id', $aid)->first();
        $provider = \App\Models\Provider::where('agency_id', $aid)->first();
        $user = \App\Models\User::where('agency_id', $aid)->where('role', 'agency')->first();

        // 1. Create billing client
        $client = \App\Models\BillingClient::firstOrCreate(
            ['agency_id' => $aid, 'organization_name' => $org ? $org->name : 'Demo Psychiatry Group'],
            [
                'organization_id' => $org?->id,
                'contact_name' => 'Maria Johnson',
                'contact_email' => 'maria@demopsych.com',
                'contact_phone' => '(555) 234-5678',
                'billing_platform' => 'Office Ally',
                'monthly_fee' => 1500,
                'fee_structure' => 'flat',
                'status' => 'active',
                'start_date' => '2025-06-01',
                'notes' => 'Demo billing client — Office Ally for claim submission.',
                'created_by' => $user?->id,
            ]
        );

        // 2. Create billing tasks
        $taskData = [
            ['title' => 'Follow up on UnitedHealthcare denial for patient J. Smith', 'category' => 'denial_management', 'priority' => 'high', 'status' => 'pending', 'due_date' => now()->addDays(2)->toDateString()],
            ['title' => 'Submit March claims batch to Aetna', 'category' => 'claim_submission', 'priority' => 'urgent', 'status' => 'in_progress', 'due_date' => now()->subDays(1)->toDateString()],
            ['title' => 'Post Cigna EFT payment — $4,230', 'category' => 'payment_posting', 'priority' => 'normal', 'status' => 'pending', 'due_date' => now()->addDays(1)->toDateString()],
            ['title' => 'Verify eligibility for new patient referrals', 'category' => 'eligibility_verification', 'priority' => 'normal', 'status' => 'pending', 'due_date' => now()->addDays(3)->toDateString()],
            ['title' => 'Run monthly AR aging report for client', 'category' => 'reporting', 'priority' => 'low', 'status' => 'pending', 'due_date' => now()->addDays(5)->toDateString()],
            ['title' => 'Appeal timely filing denial — BCBS claim #CLM-000012', 'category' => 'denial_management', 'priority' => 'urgent', 'status' => 'pending', 'due_date' => now()->toDateString()],
            ['title' => 'Enter charges for last week therapy sessions', 'category' => 'charge_entry', 'priority' => 'high', 'status' => 'completed', 'due_date' => now()->subDays(3)->toDateString()],
        ];
        foreach ($taskData as $t) {
            \App\Models\BillingTask::firstOrCreate(
                ['agency_id' => $aid, 'billing_client_id' => $client->id, 'title' => $t['title']],
                array_merge($t, ['agency_id' => $aid, 'billing_client_id' => $client->id, 'created_by' => $user?->id])
            );
        }

        // 3. Create billing activities
        $actData = [
            ['activity_type' => 'claim_submitted', 'activity_date' => now()->toDateString(), 'amount' => 3200, 'quantity' => 8, 'notes' => 'Submitted 8 claims to Aetna for March therapy sessions'],
            ['activity_type' => 'payment_posted', 'activity_date' => now()->toDateString(), 'amount' => 4230, 'quantity' => 12, 'notes' => 'Posted Cigna EFT payment — 12 claims paid', 'payer_name' => 'Cigna'],
            ['activity_type' => 'denial_worked', 'activity_date' => now()->subDays(1)->toDateString(), 'amount' => 480, 'quantity' => 2, 'notes' => 'Worked 2 UHC denials — authorization issue, sent appeal', 'payer_name' => 'UnitedHealthcare'],
            ['activity_type' => 'eligibility_check', 'activity_date' => now()->subDays(1)->toDateString(), 'quantity' => 5, 'notes' => 'Verified eligibility for 5 new patient referrals'],
            ['activity_type' => 'claim_followup', 'activity_date' => now()->subDays(2)->toDateString(), 'amount' => 1800, 'quantity' => 6, 'notes' => 'Called BCBS on 6 outstanding claims >45 days', 'payer_name' => 'BCBS of Florida'],
            ['activity_type' => 'payment_posted', 'activity_date' => now()->subDays(3)->toDateString(), 'amount' => 2100, 'quantity' => 7, 'notes' => 'Posted UHC check #12345 — 7 claims', 'payer_name' => 'UnitedHealthcare', 'reference' => 'CHK#12345'],
            ['activity_type' => 'claim_submitted', 'activity_date' => now()->subDays(4)->toDateString(), 'amount' => 5600, 'quantity' => 15, 'notes' => 'Submitted 15 claims for February — all payers'],
            ['activity_type' => 'report_generated', 'activity_date' => now()->subDays(5)->toDateString(), 'notes' => 'Generated February monthly collection report for client review'],
            ['activity_type' => 'denial_worked', 'activity_date' => now()->subDays(6)->toDateString(), 'amount' => 320, 'quantity' => 1, 'notes' => 'Appealed Aetna denial — coding issue, corrected and resubmitted', 'payer_name' => 'Aetna'],
            ['activity_type' => 'claim_submitted', 'activity_date' => now()->subDays(7)->toDateString(), 'amount' => 2800, 'quantity' => 10, 'notes' => 'Submitted 10 claims to Cigna via Office Ally', 'payer_name' => 'Cigna'],
        ];
        foreach ($actData as $a) {
            \App\Models\BillingActivity::firstOrCreate(
                ['agency_id' => $aid, 'billing_client_id' => $client->id, 'activity_type' => $a['activity_type'], 'activity_date' => $a['activity_date'], 'notes' => $a['notes']],
                array_merge($a, ['agency_id' => $aid, 'billing_client_id' => $client->id, 'created_by' => $user?->id])
            );
        }

        // 4. Create billing financials (last 4 months)
        $finData = [
            ['period' => now()->subMonths(3)->format('Y-m'), 'claims_submitted' => 42, 'amount_billed' => 18500, 'amount_collected' => 16200, 'denial_count' => 3, 'denied_amount' => 1200, 'adjustments' => 800, 'patient_responsibility' => 300],
            ['period' => now()->subMonths(2)->format('Y-m'), 'claims_submitted' => 48, 'amount_billed' => 21000, 'amount_collected' => 18900, 'denial_count' => 4, 'denied_amount' => 1600, 'adjustments' => 950, 'patient_responsibility' => 450],
            ['period' => now()->subMonths(1)->format('Y-m'), 'claims_submitted' => 55, 'amount_billed' => 24500, 'amount_collected' => 21800, 'denial_count' => 5, 'denied_amount' => 1900, 'adjustments' => 1100, 'patient_responsibility' => 520],
            ['period' => now()->format('Y-m'), 'claims_submitted' => 23, 'amount_billed' => 9800, 'amount_collected' => 6330, 'denial_count' => 2, 'denied_amount' => 800, 'adjustments' => 400, 'patient_responsibility' => 180],
        ];
        foreach ($finData as $f) {
            \App\Models\BillingFinancial::updateOrCreate(
                ['agency_id' => $aid, 'billing_client_id' => $client->id, 'period' => $f['period']],
                array_merge($f, ['created_by' => $user?->id])
            );
        }

        // 5. Create RCM claims
        $claimData = [
            ['patient_name' => 'John Smith', 'payer_name' => 'UnitedHealthcare', 'date_of_service' => now()->subDays(30)->toDateString(), 'total_charges' => 160, 'total_paid' => 160, 'balance' => 0, 'status' => 'paid', 'paid_date' => now()->subDays(10)->toDateString()],
            ['patient_name' => 'Jane Doe', 'payer_name' => 'Aetna', 'date_of_service' => now()->subDays(25)->toDateString(), 'total_charges' => 250, 'total_paid' => 0, 'balance' => 250, 'status' => 'submitted', 'submitted_date' => now()->subDays(20)->toDateString()],
            ['patient_name' => 'Robert Williams', 'payer_name' => 'Cigna', 'date_of_service' => now()->subDays(20)->toDateString(), 'total_charges' => 120, 'total_paid' => 95, 'balance' => 25, 'status' => 'partial_paid', 'paid_date' => now()->subDays(5)->toDateString()],
            ['patient_name' => 'Emily Chen', 'payer_name' => 'BCBS of Florida', 'date_of_service' => now()->subDays(45)->toDateString(), 'total_charges' => 320, 'total_paid' => 0, 'balance' => 320, 'status' => 'denied', 'denial_reason' => 'Authorization not obtained prior to service'],
            ['patient_name' => 'Michael Brown', 'payer_name' => 'UnitedHealthcare', 'date_of_service' => now()->subDays(15)->toDateString(), 'total_charges' => 160, 'total_paid' => 0, 'balance' => 160, 'status' => 'submitted', 'submitted_date' => now()->subDays(10)->toDateString()],
            ['patient_name' => 'Sarah Johnson', 'payer_name' => 'Aetna', 'date_of_service' => now()->subDays(60)->toDateString(), 'total_charges' => 480, 'total_paid' => 480, 'balance' => 0, 'status' => 'paid', 'paid_date' => now()->subDays(20)->toDateString()],
            ['patient_name' => 'David Lee', 'payer_name' => 'Cigna', 'date_of_service' => now()->subDays(35)->toDateString(), 'total_charges' => 160, 'total_paid' => 0, 'balance' => 160, 'status' => 'pending'],
            ['patient_name' => 'Lisa Garcia', 'payer_name' => 'BCBS of Florida', 'date_of_service' => now()->subDays(10)->toDateString(), 'total_charges' => 250, 'total_paid' => 0, 'balance' => 250, 'status' => 'submitted', 'submitted_date' => now()->subDays(7)->toDateString()],
            ['patient_name' => 'James Wilson', 'payer_name' => 'UnitedHealthcare', 'date_of_service' => now()->subDays(90)->toDateString(), 'total_charges' => 160, 'total_paid' => 0, 'balance' => 160, 'status' => 'denied', 'denial_reason' => 'Timely filing limit exceeded'],
            ['patient_name' => 'Anna Martinez', 'payer_name' => 'Aetna', 'date_of_service' => now()->subDays(5)->toDateString(), 'total_charges' => 120, 'total_paid' => 0, 'balance' => 120, 'status' => 'draft'],
        ];
        $claimIds = [];
        $count = \App\Models\Claim::where('agency_id', $aid)->count();
        foreach ($claimData as $i => $cd) {
            $c = \App\Models\Claim::firstOrCreate(
                ['agency_id' => $aid, 'patient_name' => $cd['patient_name'], 'date_of_service' => $cd['date_of_service']],
                array_merge($cd, [
                    'agency_id' => $aid,
                    'billing_client_id' => $client->id,
                    'claim_number' => 'CLM-' . str_pad($count + $i + 1, 6, '0', STR_PAD_LEFT),
                    'claim_type' => '837P',
                    'provider_id' => $provider?->id,
                    'provider_name' => $provider ? $provider->first_name . ' ' . $provider->last_name : 'Sarah Demo',
                    'created_by' => $user?->id,
                ])
            );
            $claimIds[] = $c->id;
        }

        // 6. Create denials for denied claims
        $deniedClaims = \App\Models\Claim::where('agency_id', $aid)->where('status', 'denied')->get();
        foreach ($deniedClaims as $dc) {
            \App\Models\ClaimDenial::firstOrCreate(
                ['agency_id' => $aid, 'claim_id' => $dc->id],
                [
                    'agency_id' => $aid,
                    'billing_client_id' => $client->id,
                    'denial_category' => $dc->denial_reason && str_contains($dc->denial_reason, 'Authorization') ? 'authorization' : 'timely_filing',
                    'denial_reason' => $dc->denial_reason ?: 'Unknown',
                    'denied_amount' => $dc->total_charges,
                    'status' => 'new',
                    'priority' => 'high',
                    'denial_date' => now()->subDays(5)->toDateString(),
                    'appeal_deadline' => now()->addDays(25)->toDateString(),
                    'created_by' => $user?->id,
                ]
            );
        }

        // 7. Create payments for paid claims
        $paidClaims = \App\Models\Claim::where('agency_id', $aid)->whereIn('status', ['paid', 'partial_paid'])->get();
        foreach ($paidClaims as $pc) {
            $pmt = \App\Models\ClaimPayment::firstOrCreate(
                ['agency_id' => $aid, 'payer_name' => $pc->payer_name, 'payment_date' => $pc->paid_date ?: now()->subDays(10)->toDateString()],
                [
                    'agency_id' => $aid,
                    'billing_client_id' => $client->id,
                    'payment_type' => 'eft',
                    'total_amount' => $pc->total_paid,
                    'posted_amount' => $pc->total_paid,
                    'remaining_amount' => 0,
                    'status' => 'posted',
                    'posted_by' => $user?->id,
                    'posted_at' => now(),
                    'created_by' => $user?->id,
                ]
            );
            \App\Models\PaymentAllocation::firstOrCreate(
                ['claim_payment_id' => $pmt->id, 'claim_id' => $pc->id],
                ['charged_amount' => $pc->total_charges, 'allowed_amount' => $pc->total_paid, 'paid_amount' => $pc->total_paid, 'adjustment_amount' => 0, 'patient_responsibility' => 0]
            );
        }

        // 8. Create charge entries
        $chargeData = [
            ['patient_name' => 'New Patient A', 'payer_name' => 'Aetna', 'cpt_code' => '90791', 'cpt_description' => 'Psychiatric diagnostic evaluation', 'charge_amount' => 250, 'date_of_service' => now()->toDateString(), 'status' => 'pending'],
            ['patient_name' => 'John Smith', 'payer_name' => 'UnitedHealthcare', 'cpt_code' => '90834', 'cpt_description' => 'Psychotherapy, 45 min', 'charge_amount' => 120, 'date_of_service' => now()->toDateString(), 'status' => 'pending'],
            ['patient_name' => 'Jane Doe', 'payer_name' => 'Aetna', 'cpt_code' => '90837', 'cpt_description' => 'Psychotherapy, 60 min', 'charge_amount' => 160, 'date_of_service' => now()->subDays(1)->toDateString(), 'status' => 'pending'],
            ['patient_name' => 'Robert Williams', 'payer_name' => 'Cigna', 'cpt_code' => '99213', 'cpt_description' => 'Office visit, established, 20-29 min', 'charge_amount' => 95, 'date_of_service' => now()->subDays(1)->toDateString(), 'status' => 'reviewed'],
            ['patient_name' => 'Emily Chen', 'payer_name' => 'BCBS of Florida', 'cpt_code' => '90847', 'cpt_description' => 'Family psychotherapy with patient', 'charge_amount' => 140, 'date_of_service' => now()->subDays(2)->toDateString(), 'status' => 'submitted'],
        ];
        foreach ($chargeData as $ch) {
            \App\Models\ChargeEntry::firstOrCreate(
                ['agency_id' => $aid, 'patient_name' => $ch['patient_name'], 'cpt_code' => $ch['cpt_code'], 'date_of_service' => $ch['date_of_service']],
                array_merge($ch, [
                    'agency_id' => $aid,
                    'billing_client_id' => $client->id,
                    'provider_id' => $provider?->id,
                    'provider_name' => $provider ? $provider->first_name . ' ' . $provider->last_name : 'Sarah Demo',
                    'icd_codes' => 'F41.1',
                    'icd_descriptions' => 'Generalized anxiety disorder',
                    'units' => 1,
                    'created_by' => $user?->id,
                ])
            );
        }

        return response()->json(['success' => true, 'message' => 'Demo billing data seeded', 'billing_client_id' => $client->id, 'claims' => count($claimData), 'activities' => count($actData), 'charges' => count($chargeData)]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
    }
});

Route::get('/run-migrations', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        return response()->json(['success' => true, 'output' => \Illuminate\Support\Facades\Artisan::output()]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

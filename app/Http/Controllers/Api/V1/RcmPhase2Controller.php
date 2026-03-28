<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppealTemplate;
use App\Models\BillingTask;
use App\Models\Claim;
use App\Models\ClaimDenial;
use App\Models\ClientReport;
use App\Models\EligibilityCheck;
use App\Models\FeeSchedule;
use App\Models\PatientStatement;
use App\Models\PayerFollowup;
use App\Models\PayerRule;
use App\Models\ProviderFeedback;
use App\Models\UnderpaymentFlag;
use App\Services\AiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RcmPhase2Controller extends Controller
{
    // ══════════════════════════════════════════════════
    // 1. FEE SCHEDULE MANAGEMENT
    // ══════════════════════════════════════════════════

    public function feeSchedules(Request $request): JsonResponse
    {
        $query = FeeSchedule::where('agency_id', $request->user()->agency_id);
        if ($p = $request->input('payer_name')) $query->where('payer_name', $p);
        if ($c = $request->input('cpt_code')) $query->where('cpt_code', $c);
        return response()->json(['success' => true, 'data' => $query->orderBy('payer_name')->orderBy('cpt_code')->get()]);
    }

    public function storeFeeSchedule(Request $request): JsonResponse
    {
        $request->validate(['payer_name' => 'required|string|max:100', 'cpt_code' => 'required|string|max:10', 'contracted_rate' => 'required|numeric|min:0']);
        $fs = FeeSchedule::create(['agency_id' => $request->user()->agency_id, 'created_by' => $request->user()->id, ...$request->only([
            'billing_client_id', 'payer_name', 'cpt_code', 'cpt_description', 'modifier',
            'contracted_rate', 'expected_allowed', 'effective_date', 'termination_date', 'plan_type', 'notes',
        ])]);
        return response()->json(['success' => true, 'data' => $fs], 201);
    }

    public function updateFeeSchedule(Request $request, int $id): JsonResponse
    {
        $fs = FeeSchedule::where('agency_id', $request->user()->agency_id)->findOrFail($id);
        $fs->update($request->only([
            'billing_client_id', 'payer_name', 'cpt_code', 'cpt_description', 'modifier',
            'contracted_rate', 'expected_allowed', 'effective_date', 'termination_date', 'plan_type', 'notes',
        ]));
        return response()->json(['success' => true, 'data' => $fs]);
    }

    public function destroyFeeSchedule(Request $request, int $id): JsonResponse
    {
        FeeSchedule::where('agency_id', $request->user()->agency_id)->findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    public function bulkImportFeeSchedules(Request $request): JsonResponse
    {
        $request->validate(['schedules' => 'required|array|min:1|max:500']);
        $aid = $request->user()->agency_id;
        $uid = $request->user()->id;
        $created = 0;
        foreach ($request->schedules as $row) {
            FeeSchedule::updateOrCreate(
                ['agency_id' => $aid, 'payer_name' => $row['payer_name'], 'cpt_code' => $row['cpt_code'], 'modifier' => $row['modifier'] ?? null],
                array_merge($row, ['agency_id' => $aid, 'created_by' => $uid])
            );
            $created++;
        }
        return response()->json(['success' => true, 'imported' => $created], 201);
    }

    // ══════════════════════════════════════════════════
    // 2. WORK QUEUES / SMART WORKLISTS
    // ══════════════════════════════════════════════════

    public function workQueues(Request $request): JsonResponse
    {
        $aid = $request->user()->agency_id;
        $now = now();

        // AR Follow-Up Queue: claims >30 days with balance, no recent followup
        $arFollowup = Claim::where('agency_id', $aid)
            ->whereIn('status', ['submitted', 'acknowledged', 'pending', 'in_process'])
            ->where('balance', '>', 0)
            ->where('date_of_service', '<', $now->copy()->subDays(30))
            ->whereDoesntHave('followups', fn($q) => $q->where('created_at', '>', $now->copy()->subDays(14)))
            ->orderBy('date_of_service')
            ->limit(50)
            ->get(['id', 'claim_number', 'patient_name', 'payer_name', 'date_of_service', 'total_charges', 'balance', 'status']);

        // Denial Work Queue: open denials sorted by deadline urgency
        $denialQueue = ClaimDenial::where('agency_id', $aid)
            ->whereIn('status', ['new', 'in_review', 'appeal_in_progress', 'pending_response'])
            ->with(['claim:id,claim_number,patient_name,payer_name,total_charges'])
            ->orderByRaw('CASE WHEN appeal_deadline IS NOT NULL THEN 0 ELSE 1 END, appeal_deadline ASC')
            ->limit(50)
            ->get();

        // Underpayment Queue: flagged underpayments needing review
        $underpayments = UnderpaymentFlag::where('agency_id', $aid)
            ->where('status', 'flagged')
            ->with(['claim:id,claim_number,patient_name,payer_name'])
            ->orderByDesc('variance')
            ->limit(30)
            ->get();

        // Pending Follow-Ups: scheduled follow-ups that are due or overdue
        $pendingFollowups = PayerFollowup::where('agency_id', $aid)
            ->where('followup_completed', false)
            ->whereNotNull('followup_date')
            ->where('followup_date', '<=', $now->toDateString())
            ->with(['claim:id,claim_number,patient_name,payer_name,balance'])
            ->orderBy('followup_date')
            ->limit(30)
            ->get();

        // High-value claims at risk: >$500 balance, >60 days old
        $highValue = Claim::where('agency_id', $aid)
            ->whereIn('status', ['submitted', 'acknowledged', 'pending'])
            ->where('balance', '>=', 500)
            ->where('date_of_service', '<', $now->copy()->subDays(60))
            ->orderByDesc('balance')
            ->limit(20)
            ->get(['id', 'claim_number', 'patient_name', 'payer_name', 'date_of_service', 'total_charges', 'balance']);

        return response()->json(['success' => true, 'data' => [
            'ar_followup' => $arFollowup,
            'denial_queue' => $denialQueue,
            'underpayments' => $underpayments,
            'pending_followups' => $pendingFollowups,
            'high_value_at_risk' => $highValue,
            'counts' => [
                'ar_followup' => $arFollowup->count(),
                'denials' => $denialQueue->count(),
                'underpayments' => $underpayments->count(),
                'followups_due' => $pendingFollowups->count(),
                'high_value' => $highValue->count(),
            ],
        ]]);
    }

    // ══════════════════════════════════════════════════
    // 3. DENIAL WORKFLOW AUTOMATION
    // ══════════════════════════════════════════════════

    public function appealTemplates(Request $request): JsonResponse
    {
        $templates = AppealTemplate::where('agency_id', $request->user()->agency_id)->orderBy('name')->get();
        return response()->json(['success' => true, 'data' => $templates]);
    }

    public function storeAppealTemplate(Request $request): JsonResponse
    {
        $request->validate(['name' => 'required|string|max:100', 'body' => 'required|string']);
        $t = AppealTemplate::create(['agency_id' => $request->user()->agency_id, 'created_by' => $request->user()->id, ...$request->only([
            'name', 'denial_category', 'template_type', 'subject', 'body', 'required_attachments', 'is_default',
        ])]);
        return response()->json(['success' => true, 'data' => $t], 201);
    }

    public function updateAppealTemplate(Request $request, int $id): JsonResponse
    {
        $t = AppealTemplate::where('agency_id', $request->user()->agency_id)->findOrFail($id);
        $t->update($request->only(['name', 'denial_category', 'template_type', 'subject', 'body', 'required_attachments', 'is_default']));
        return response()->json(['success' => true, 'data' => $t]);
    }

    public function destroyAppealTemplate(Request $request, int $id): JsonResponse
    {
        AppealTemplate::where('agency_id', $request->user()->agency_id)->findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    public function generateAppealLetter(Request $request): JsonResponse
    {
        $request->validate(['denial_id' => 'required', 'template_id' => 'required']);
        $aid = $request->user()->agency_id;
        $denial = ClaimDenial::where('agency_id', $aid)->with(['claim.billingClient', 'claim.serviceLines'])->findOrFail($request->denial_id);
        $template = AppealTemplate::where('agency_id', $aid)->findOrFail($request->template_id);
        $claim = $denial->claim;

        $replacements = [
            '{{claim_number}}' => $claim->claim_number ?? '',
            '{{patient_name}}' => $claim->patient_name ?? '',
            '{{payer_name}}' => $claim->payer_name ?? '',
            '{{date_of_service}}' => $claim->date_of_service ?? '',
            '{{provider_name}}' => $claim->provider_name ?? '',
            '{{member_id}}' => $claim->patient_member_id ?? '',
            '{{total_charges}}' => number_format($claim->total_charges ?? 0, 2),
            '{{denied_amount}}' => number_format($denial->denied_amount ?? 0, 2),
            '{{denial_reason}}' => $denial->denial_reason ?? '',
            '{{denial_code}}' => $denial->denial_code ?? '',
            '{{denial_category}}' => $denial->denial_category ?? '',
            '{{appeal_deadline}}' => $denial->appeal_deadline ? $denial->appeal_deadline->format('m/d/Y') : '',
            '{{today}}' => now()->format('m/d/Y'),
            '{{agency_name}}' => $request->user()->agency->name ?? '',
            '{{client_name}}' => $claim->billingClient->organization_name ?? '',
            '{{cpt_codes}}' => $claim->serviceLines->pluck('cpt_code')->implode(', '),
        ];

        $body = str_replace(array_keys($replacements), array_values($replacements), $template->body);
        $subject = str_replace(array_keys($replacements), array_values($replacements), $template->subject ?? '');

        // Auto-create a follow-up task for the appeal
        BillingTask::create([
            'agency_id' => $aid,
            'billing_client_id' => $claim->billing_client_id,
            'title' => "Follow up on appeal — {$claim->claim_number} ({$claim->payer_name})",
            'category' => 'denial_management',
            'priority' => $denial->priority ?? 'high',
            'status' => 'pending',
            'due_date' => $denial->appeal_deadline ?? now()->addDays(14),
            'description' => "Appeal submitted for denial: {$denial->denial_reason}",
            'created_by' => $request->user()->id,
        ]);

        // Update denial status to appeal_in_progress
        $denial->update(['status' => 'appeal_in_progress', 'appeal_submitted_date' => now()]);

        return response()->json(['success' => true, 'data' => [
            'subject' => $subject,
            'body' => $body,
            'template_name' => $template->name,
            'required_attachments' => $template->required_attachments,
        ]]);
    }

    // Auto-escalate denials approaching deadline
    public function escalateDenials(Request $request): JsonResponse
    {
        $aid = $request->user()->agency_id;
        $escalated = 0;

        // Find denials with deadlines in next 7 days that haven't been escalated
        $urgent = ClaimDenial::where('agency_id', $aid)
            ->whereIn('status', ['new', 'in_review'])
            ->whereNotNull('appeal_deadline')
            ->where('appeal_deadline', '<=', now()->addDays(7))
            ->where('appeal_deadline', '>', now())
            ->with('claim')
            ->get();

        foreach ($urgent as $d) {
            $d->update(['priority' => 'urgent']);
            BillingTask::firstOrCreate(
                ['agency_id' => $aid, 'title' => "URGENT: Appeal deadline approaching — {$d->claim->claim_number}"],
                [
                    'billing_client_id' => $d->billing_client_id,
                    'category' => 'denial_management',
                    'priority' => 'urgent',
                    'status' => 'pending',
                    'due_date' => $d->appeal_deadline,
                    'description' => "Appeal deadline: {$d->appeal_deadline->format('m/d/Y')}. Denial: {$d->denial_reason}",
                    'created_by' => $request->user()->id,
                ]
            );
            $escalated++;
        }

        return response()->json(['success' => true, 'escalated' => $escalated]);
    }

    // ══════════════════════════════════════════════════
    // 4. MULTI-CLAIM PAYMENT ALLOCATION
    // ══════════════════════════════════════════════════

    // Already partially built in RcmController — this adds batch allocation
    public function batchAllocatePayment(Request $request): JsonResponse
    {
        $request->validate([
            'payment_id' => 'required|exists:claim_payments,id',
            'allocations' => 'required|array|min:1',
            'allocations.*.claim_id' => 'required|exists:claims,id',
            'allocations.*.paid_amount' => 'required|numeric|min:0',
        ]);

        $aid = $request->user()->agency_id;
        $payment = \App\Models\ClaimPayment::where('agency_id', $aid)->findOrFail($request->payment_id);
        $allocated = 0;

        foreach ($request->allocations as $alloc) {
            \App\Models\PaymentAllocation::create([
                'claim_payment_id' => $payment->id,
                'claim_id' => $alloc['claim_id'],
                'charged_amount' => $alloc['charged_amount'] ?? 0,
                'allowed_amount' => $alloc['allowed_amount'] ?? 0,
                'paid_amount' => $alloc['paid_amount'],
                'adjustment_amount' => $alloc['adjustment_amount'] ?? 0,
                'patient_responsibility' => $alloc['patient_responsibility'] ?? 0,
            ]);

            $claim = Claim::find($alloc['claim_id']);
            if ($claim) {
                $claim->total_paid = ($claim->total_paid ?? 0) + $alloc['paid_amount'];
                $claim->balance = $claim->total_charges - $claim->total_paid - ($claim->adjustments ?? 0);
                $claim->patient_responsibility = ($claim->patient_responsibility ?? 0) + ($alloc['patient_responsibility'] ?? 0);
                if ($claim->balance <= 0) $claim->status = 'paid';
                elseif ($claim->total_paid > 0) $claim->status = 'partial_paid';
                $claim->paid_date = $payment->payment_date;
                $claim->save();
            }
            $allocated++;
        }

        $payment->recalculate();
        $payment->load(['allocations.claim:id,claim_number,patient_name']);

        return response()->json(['success' => true, 'allocated' => $allocated, 'data' => $payment]);
    }

    // ══════════════════════════════════════════════════
    // 5. PAYER FOLLOW-UP TRACKING
    // ══════════════════════════════════════════════════

    public function followups(Request $request): JsonResponse
    {
        $query = PayerFollowup::where('agency_id', $request->user()->agency_id)
            ->with(['claim:id,claim_number,patient_name,payer_name,balance']);
        if ($cid = $request->input('claim_id')) $query->where('claim_id', $cid);
        if ($request->input('due_only')) $query->where('followup_completed', false)->whereNotNull('followup_date')->where('followup_date', '<=', now());
        return response()->json(['success' => true, 'data' => $query->orderByDesc('created_at')->get()]);
    }

    public function storeFollowup(Request $request): JsonResponse
    {
        $request->validate(['claim_id' => 'required|exists:claims,id', 'notes' => 'required|string']);
        $claim = Claim::where('agency_id', $request->user()->agency_id)->findOrFail($request->claim_id);
        $f = PayerFollowup::create(['agency_id' => $request->user()->agency_id, 'created_by' => $request->user()->id, 'payer_name' => $claim->payer_name, ...$request->only([
            'claim_id', 'contact_method', 'payer_rep', 'reference_number', 'outcome', 'notes', 'followup_date',
        ])]);
        return response()->json(['success' => true, 'data' => $f->load('claim:id,claim_number,patient_name')], 201);
    }

    public function updateFollowup(Request $request, int $id): JsonResponse
    {
        $f = PayerFollowup::where('agency_id', $request->user()->agency_id)->findOrFail($id);
        $f->update($request->only(['contact_method', 'payer_rep', 'reference_number', 'outcome', 'notes', 'followup_date', 'followup_completed']));
        return response()->json(['success' => true, 'data' => $f]);
    }

    public function destroyFollowup(Request $request, int $id): JsonResponse
    {
        PayerFollowup::where('agency_id', $request->user()->agency_id)->findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    // ══════════════════════════════════════════════════
    // 6. UNDERPAYMENT DETECTION
    // ══════════════════════════════════════════════════

    public function detectUnderpayments(Request $request): JsonResponse
    {
        $aid = $request->user()->agency_id;
        $uid = $request->user()->id;

        // Get all fee schedules for this agency
        $schedules = FeeSchedule::where('agency_id', $aid)->get()->groupBy(fn($fs) => $fs->payer_name . '|' . $fs->cpt_code);

        // Get paid claims with service lines
        $paidClaims = Claim::where('agency_id', $aid)
            ->whereIn('status', ['paid', 'partial_paid'])
            ->whereDoesntHave('underpaymentFlags')
            ->with('serviceLines')
            ->get();

        $flagged = 0;
        foreach ($paidClaims as $claim) {
            foreach ($claim->serviceLines as $line) {
                $key = ($claim->payer_name ?? '') . '|' . $line->cpt_code;
                $feeSchedule = $schedules->get($key)?->first();
                if (!$feeSchedule) continue;

                $expected = $feeSchedule->contracted_rate * ($line->units ?? 1);
                $paid = $line->paid_amount ?? 0;
                $variance = $expected - $paid;

                if ($variance > 1 && $paid > 0) { // underpaid by more than $1
                    UnderpaymentFlag::create([
                        'agency_id' => $aid,
                        'claim_id' => $claim->id,
                        'cpt_code' => $line->cpt_code,
                        'expected_amount' => round($expected, 2),
                        'paid_amount' => round($paid, 2),
                        'variance' => round($variance, 2),
                        'status' => 'flagged',
                        'created_by' => $uid,
                    ]);
                    $flagged++;
                }
            }
        }

        return response()->json(['success' => true, 'flagged' => $flagged]);
    }

    public function underpayments(Request $request): JsonResponse
    {
        $query = UnderpaymentFlag::where('agency_id', $request->user()->agency_id)
            ->with(['claim:id,claim_number,patient_name,payer_name,total_charges,total_paid']);
        if ($s = $request->input('status')) $query->where('status', $s);
        return response()->json(['success' => true, 'data' => $query->orderByDesc('variance')->get()]);
    }

    public function updateUnderpayment(Request $request, int $id): JsonResponse
    {
        $flag = UnderpaymentFlag::where('agency_id', $request->user()->agency_id)->findOrFail($id);
        $data = $request->only(['status', 'notes']);
        if (in_array($data['status'] ?? '', ['reviewed', 'resolved', 'accepted']) && !$flag->reviewed_at) {
            $data['reviewed_by'] = $request->user()->id;
            $data['reviewed_at'] = now();
        }
        $flag->update($data);
        return response()->json(['success' => true, 'data' => $flag]);
    }

    // ══════════════════════════════════════════════════
    // 7. EXPORT / REPORTING SUITE
    // ══════════════════════════════════════════════════

    public function exportClaims(Request $request): JsonResponse
    {
        $aid = $request->user()->agency_id;
        $query = Claim::where('agency_id', $aid)->with(['serviceLines']);
        if ($s = $request->input('status')) $query->where('status', $s);
        if ($from = $request->input('from_date')) $query->where('date_of_service', '>=', $from);
        if ($to = $request->input('to_date')) $query->where('date_of_service', '<=', $to);

        $claims = $query->orderByDesc('date_of_service')->get();

        $rows = $claims->map(fn($c) => [
            'claim_number' => $c->claim_number,
            'patient_name' => $c->patient_name,
            'payer_name' => $c->payer_name,
            'date_of_service' => $c->date_of_service,
            'total_charges' => $c->total_charges,
            'total_paid' => $c->total_paid,
            'adjustments' => $c->adjustments,
            'patient_responsibility' => $c->patient_responsibility,
            'balance' => $c->balance,
            'status' => $c->status,
            'provider_name' => $c->provider_name,
            'denial_reason' => $c->denial_reason,
            'submitted_date' => $c->submitted_date,
            'paid_date' => $c->paid_date,
        ]);

        return response()->json(['success' => true, 'data' => $rows, 'count' => $rows->count()]);
    }

    public function exportDenials(Request $request): JsonResponse
    {
        $denials = ClaimDenial::where('agency_id', $request->user()->agency_id)
            ->with(['claim:id,claim_number,patient_name,payer_name,total_charges'])
            ->get()
            ->map(fn($d) => [
                'claim_number' => $d->claim->claim_number ?? '',
                'patient_name' => $d->claim->patient_name ?? '',
                'payer_name' => $d->claim->payer_name ?? '',
                'denial_category' => $d->denial_category,
                'denial_code' => $d->denial_code,
                'denial_reason' => $d->denial_reason,
                'denied_amount' => $d->denied_amount,
                'recovered_amount' => $d->recovered_amount,
                'appeal_deadline' => $d->appeal_deadline,
                'status' => $d->status,
                'priority' => $d->priority,
            ]);

        return response()->json(['success' => true, 'data' => $denials, 'count' => $denials->count()]);
    }

    // ══════════════════════════════════════════════════
    // 8. CLIENT-FACING REPORTS
    // ══════════════════════════════════════════════════

    public function generateClientReport(Request $request): JsonResponse
    {
        $request->validate(['billing_client_id' => 'required', 'period' => 'required|string|size:7']); // e.g. 2026-03
        $aid = $request->user()->agency_id;
        $clientId = $request->billing_client_id;
        $period = $request->period;

        $claims = Claim::where('agency_id', $aid)->where('billing_client_id', $clientId)
            ->where('date_of_service', 'like', $period . '%')->get();

        $denials = ClaimDenial::where('agency_id', $aid)->where('billing_client_id', $clientId)
            ->whereHas('claim', fn($q) => $q->where('date_of_service', 'like', $period . '%'))->get();

        $totalCharged = $claims->sum(fn($c) => (float) $c->total_charges);
        $totalCollected = $claims->sum(fn($c) => (float) $c->total_paid);
        $deniedCount = $claims->where('status', 'denied')->count();
        $totalDenied = $denials->sum(fn($d) => (float) $d->denied_amount);
        $paidClaims = $claims->whereIn('status', ['paid', 'partial_paid']);
        $avgDaysToPay = $paidClaims->count() > 0 ? round($paidClaims->avg(function ($c) {
            if (!$c->date_of_service || !$c->paid_date) return 0;
            return now()->parse($c->date_of_service)->diffInDays(now()->parse($c->paid_date));
        })) : 0;

        // By payer breakdown
        $byPayer = $claims->groupBy('payer_name')->map(fn($group, $payer) => [
            'payer' => $payer, 'claims' => $group->count(),
            'charged' => round($group->sum(fn($c) => (float) $c->total_charges), 2),
            'collected' => round($group->sum(fn($c) => (float) $c->total_paid), 2),
        ])->values();

        // Denial breakdown
        $denialBreakdown = $denials->groupBy('denial_category')->map(fn($group, $cat) => [
            'category' => $cat, 'count' => $group->count(),
            'amount' => round($group->sum(fn($d) => (float) $d->denied_amount), 2),
        ])->values();

        $report = ClientReport::updateOrCreate(
            ['agency_id' => $aid, 'billing_client_id' => $clientId, 'period' => $period],
            [
                'total_claims' => $claims->count(),
                'claims_submitted' => $claims->whereIn('status', ['submitted', 'acknowledged'])->count(),
                'claims_paid' => $paidClaims->count(),
                'claims_denied' => $deniedCount,
                'total_charged' => round($totalCharged, 2),
                'total_collected' => round($totalCollected, 2),
                'total_denied_amount' => round($totalDenied, 2),
                'total_adjustments' => round($claims->sum(fn($c) => (float) $c->adjustments), 2),
                'patient_responsibility' => round($claims->sum(fn($c) => (float) $c->patient_responsibility), 2),
                'collection_rate' => $totalCharged > 0 ? round($totalCollected / $totalCharged * 100, 1) : 0,
                'clean_claim_rate' => $claims->count() > 0 ? round(($claims->count() - $deniedCount) / $claims->count() * 100, 1) : 0,
                'denial_rate' => $claims->count() > 0 ? round($deniedCount / $claims->count() * 100, 1) : 0,
                'avg_days_to_pay' => $avgDaysToPay,
                'by_payer' => $byPayer,
                'denial_breakdown' => $denialBreakdown,
                'created_by' => $request->user()->id,
            ]
        );

        return response()->json(['success' => true, 'data' => $report->load('billingClient:id,organization_name')]);
    }

    public function clientReports(Request $request): JsonResponse
    {
        $query = ClientReport::where('agency_id', $request->user()->agency_id)->with('billingClient:id,organization_name');
        if ($cid = $request->input('billing_client_id')) $query->where('billing_client_id', $cid);
        return response()->json(['success' => true, 'data' => $query->orderByDesc('period')->get()]);
    }

    // ══════════════════════════════════════════════════
    // 9. PATIENT STATEMENTS & COLLECTIONS
    // ══════════════════════════════════════════════════

    public function patientStatements(Request $request): JsonResponse
    {
        $query = PatientStatement::where('agency_id', $request->user()->agency_id);
        if ($s = $request->input('status')) $query->where('status', $s);
        return response()->json(['success' => true, 'data' => $query->orderByDesc('created_at')->get()]);
    }

    public function storePatientStatement(Request $request): JsonResponse
    {
        $request->validate(['patient_name' => 'required|string|max:100', 'patient_balance' => 'required|numeric|min:0']);
        $st = PatientStatement::create(['agency_id' => $request->user()->agency_id, 'created_by' => $request->user()->id, ...$request->only([
            'billing_client_id', 'claim_id', 'patient_name', 'patient_email', 'patient_phone',
            'patient_address', 'total_charges', 'insurance_paid', 'adjustments', 'patient_balance',
            'amount_paid', 'status', 'statement_date', 'due_date', 'notes',
        ])]);
        return response()->json(['success' => true, 'data' => $st], 201);
    }

    public function updatePatientStatement(Request $request, int $id): JsonResponse
    {
        $st = PatientStatement::where('agency_id', $request->user()->agency_id)->findOrFail($id);
        $st->update($request->only([
            'patient_name', 'patient_email', 'patient_phone', 'patient_address',
            'total_charges', 'insurance_paid', 'adjustments', 'patient_balance',
            'amount_paid', 'status', 'due_date', 'notes',
        ]));
        return response()->json(['success' => true, 'data' => $st]);
    }

    public function generatePatientStatements(Request $request): JsonResponse
    {
        $aid = $request->user()->agency_id;
        $uid = $request->user()->id;

        // Auto-generate statements for claims with patient responsibility OR unpaid balance
        $claims = Claim::where('agency_id', $aid)
            ->whereDoesntHave('patientStatements')
            ->where(function ($q) {
                $q->where('patient_responsibility', '>', 0)
                  ->orWhere(function ($q2) {
                      $q2->whereIn('status', ['paid', 'partial_paid'])
                         ->where('balance', '>', 0);
                  });
            })
            ->get();

        $created = 0;
        foreach ($claims as $claim) {
            $ptBalance = (float) $claim->patient_responsibility > 0
                ? $claim->patient_responsibility
                : $claim->balance;
            if ($ptBalance <= 0) continue;

            PatientStatement::create([
                'agency_id' => $aid,
                'billing_client_id' => $claim->billing_client_id,
                'claim_id' => $claim->id,
                'patient_name' => $claim->patient_name,
                'total_charges' => $claim->total_charges,
                'insurance_paid' => $claim->total_paid,
                'adjustments' => $claim->adjustments ?? 0,
                'patient_balance' => $ptBalance,
                'status' => 'draft',
                'statement_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'created_by' => $uid,
            ]);
            $created++;
        }

        return response()->json(['success' => true, 'generated' => $created]);
    }

    // ══════════════════════════════════════════════════
    // 10. ELIGIBILITY VERIFICATION
    // ══════════════════════════════════════════════════

    public function eligibilityChecks(Request $request): JsonResponse
    {
        $query = EligibilityCheck::where('agency_id', $request->user()->agency_id);
        return response()->json(['success' => true, 'data' => $query->orderByDesc('created_at')->limit(100)->get()]);
    }

    public function checkEligibility(Request $request): JsonResponse
    {
        $request->validate(['patient_name' => 'required', 'payer_name' => 'required']);
        $aid = $request->user()->agency_id;

        $check = EligibilityCheck::create(['agency_id' => $aid, 'created_by' => $request->user()->id, 'status' => 'pending', ...$request->only([
            'billing_client_id', 'patient_name', 'patient_dob', 'member_id',
            'payer_name', 'payer_id', 'provider_npi',
        ])]);

        // TODO: Integrate with Stedi 270/271 API for real-time verification
        // For now, store the request and return placeholder
        $check->update([
            'status' => 'pending',
            'error_message' => 'Real-time verification requires Stedi API integration. Check saved for manual verification.',
        ]);

        return response()->json(['success' => true, 'data' => $check], 201);
    }

    public function updateEligibilityCheck(Request $request, int $id): JsonResponse
    {
        $check = EligibilityCheck::where('agency_id', $request->user()->agency_id)->findOrFail($id);
        $check->update($request->only([
            'status', 'is_active', 'coverage_start', 'coverage_end', 'plan_name', 'plan_type',
            'group_number', 'copay', 'deductible', 'deductible_met', 'out_of_pocket_max', 'oop_met', 'error_message',
        ]));
        return response()->json(['success' => true, 'data' => $check]);
    }

    // ══════════════════════════════════════════════════
    // 11. ERA/EOB AUTO-POSTING (835 Parser)
    // ══════════════════════════════════════════════════

    public function parseEra(Request $request): JsonResponse
    {
        $request->validate(['era_data' => 'required|string']);

        // Parse 835 ERA format — simplified parser for common fields
        $lines = explode("~", $request->era_data);
        $payments = [];
        $currentClaim = null;
        $payerName = '';
        $checkNumber = '';
        $totalAmount = 0;

        foreach ($lines as $line) {
            $segments = explode("*", trim($line));
            $segId = $segments[0] ?? '';

            if ($segId === 'N1' && ($segments[1] ?? '') === 'PR') {
                $payerName = $segments[2] ?? '';
            }
            if ($segId === 'TRN') {
                $checkNumber = $segments[2] ?? '';
            }
            if ($segId === 'BPR') {
                $totalAmount = (float)($segments[2] ?? 0);
            }
            if ($segId === 'CLP') {
                if ($currentClaim) $payments[] = $currentClaim;
                $currentClaim = [
                    'claim_number' => $segments[1] ?? '',
                    'status_code' => $segments[2] ?? '',
                    'charged_amount' => (float)($segments[3] ?? 0),
                    'paid_amount' => (float)($segments[4] ?? 0),
                    'patient_responsibility' => (float)($segments[5] ?? 0),
                    'adjustments' => [],
                ];
            }
            if ($segId === 'CAS' && $currentClaim) {
                $currentClaim['adjustments'][] = [
                    'group' => $segments[1] ?? '',
                    'code' => $segments[2] ?? '',
                    'amount' => (float)($segments[3] ?? 0),
                ];
            }
            if ($segId === 'SVC' && $currentClaim) {
                $cptParts = explode(':', $segments[1] ?? '');
                $currentClaim['service_lines'][] = [
                    'cpt_code' => $cptParts[1] ?? $cptParts[0] ?? '',
                    'charged' => (float)($segments[2] ?? 0),
                    'paid' => (float)($segments[3] ?? 0),
                ];
            }
        }
        if ($currentClaim) $payments[] = $currentClaim;

        return response()->json(['success' => true, 'data' => [
            'payer_name' => $payerName,
            'check_number' => $checkNumber,
            'total_amount' => $totalAmount,
            'claims' => $payments,
            'claim_count' => count($payments),
        ]]);
    }

    // ══════════════════════════════════════════════════
    // 11b. 837 PROFESSIONAL CLAIM FILE PARSER
    // ══════════════════════════════════════════════════

    public function parse837(Request $request): JsonResponse
    {
        $request->validate(['data' => 'required|string']);
        $raw = $request->data;
        $segments = array_filter(array_map('trim', explode('~', $raw)));

        $claims = [];
        $current = null;
        $currentSvcLine = null;
        $billingNpi = '';
        $billingName = '';
        $patientName = '';
        $patientDob = '';
        $patientMemberId = '';
        $payerName = '';
        $payerId = '';
        $renderingName = '';
        $renderingNpi = '';
        $icdCodes = [];

        foreach ($segments as $seg) {
            $parts = explode('*', $seg);
            $id = $parts[0] ?? '';

            // Billing provider
            if ($id === 'NM1' && ($parts[1] ?? '') === '85') {
                $billingName = $parts[3] ?? '';
                $billingNpi = $parts[9] ?? '';
            }

            // Subscriber/patient
            if ($id === 'NM1' && ($parts[1] ?? '') === 'IL') {
                $last = $parts[3] ?? '';
                $first = $parts[4] ?? '';
                $patientName = trim("$first $last");
                $patientMemberId = $parts[9] ?? '';
            }

            // Patient DOB
            if ($id === 'DMG') {
                $dob = $parts[2] ?? '';
                $patientDob = $dob ? substr($dob, 0, 4) . '-' . substr($dob, 4, 2) . '-' . substr($dob, 6, 2) : '';
            }

            // Payer
            if ($id === 'NM1' && ($parts[1] ?? '') === 'PR') {
                $payerName = $parts[3] ?? '';
                $payerId = $parts[9] ?? '';
            }

            // Subscriber info (insurance plan name)
            if ($id === 'SBR') {
                $planName = $parts[4] ?? '';
                if ($planName && !$payerName) $payerName = $planName;
            }

            // Diagnosis codes
            if ($id === 'HI') {
                $icdCodes = [];
                for ($i = 1; $i < count($parts); $i++) {
                    $code = $parts[$i] ?? '';
                    if ($code && strpos($code, ':') !== false) {
                        $icdCodes[] = explode(':', $code)[1] ?? $code;
                    }
                }
            }

            // Rendering provider
            if ($id === 'NM1' && ($parts[1] ?? '') === '82') {
                $rLast = str_replace(', DNP', '', $parts[3] ?? '');
                $rFirst = str_replace('DR ', '', $parts[4] ?? '');
                $rMiddle = $parts[5] ?? '';
                $renderingName = trim("$rFirst $rMiddle $rLast");
                $renderingNpi = $parts[9] ?? '';
            }

            // Claim
            if ($id === 'CLM') {
                if ($current) $claims[] = $current;
                $current = [
                    'claim_number' => $parts[1] ?? '',
                    'total_charges' => (float)($parts[2] ?? 0),
                    'patient_name' => $patientName,
                    'patient_dob' => $patientDob,
                    'patient_member_id' => $patientMemberId,
                    'payer_name' => $payerName,
                    'payer_id' => $payerId,
                    'provider_name' => $renderingName,
                    'provider_npi' => $renderingNpi,
                    'billing_npi' => $billingNpi,
                    'icd_codes' => implode(', ', $icdCodes),
                    'service_lines' => [],
                ];
                $currentSvcLine = null;
            }

            // Service line
            if ($id === 'SV1' && $current) {
                $cptParts = explode(':', $parts[1] ?? '');
                $cpt = $cptParts[1] ?? $cptParts[0] ?? '';
                $currentSvcLine = [
                    'cpt_code' => $cpt,
                    'charge_amount' => (float)($parts[2] ?? 0),
                    'units' => (int)($parts[4] ?? 1),
                    'date_of_service' => '',
                ];
                $current['service_lines'][] = &$currentSvcLine;
            }

            // Service line date
            if ($id === 'DTP' && ($parts[1] ?? '') === '472' && $currentSvcLine) {
                $d = $parts[3] ?? '';
                if (strlen($d) === 8) {
                    $currentSvcLine['date_of_service'] = substr($d, 0, 4) . '-' . substr($d, 4, 2) . '-' . substr($d, 6, 2);
                }
                // Also set claim DOS from first service line
                if ($current && !isset($current['date_of_service'])) {
                    $current['date_of_service'] = $currentSvcLine['date_of_service'];
                }
                unset($currentSvcLine);
                $currentSvcLine = null;
            }

            // End of transaction — reset patient/payer for next
            if ($id === 'SE') {
                $patientName = '';
                $patientDob = '';
                $patientMemberId = '';
                $payerName = '';
                $payerId = '';
                $icdCodes = [];
            }
        }
        if ($current) $claims[] = $current;

        return response()->json(['success' => true, 'data' => [
            'billing_provider' => $billingName,
            'billing_npi' => $billingNpi,
            'claim_count' => count($claims),
            'total_charges' => round(array_sum(array_column($claims, 'total_charges')), 2),
            'service_line_count' => array_sum(array_map(fn($c) => count($c['service_lines']), $claims)),
            'claims' => $claims,
        ]]);
    }

    public function import837(Request $request): JsonResponse
    {
        $request->validate(['claims' => 'required|array|min:1']);
        $aid = $request->user()->agency_id;
        $uid = $request->user()->id;
        $clientId = $request->input('billing_client_id');
        $imported = 0;
        $chargesCreated = 0;
        $errors = [];

        foreach ($request->claims as $i => $row) {
            try {
                $dos = $row['date_of_service'] ?? ($row['service_lines'][0]['date_of_service'] ?? null);
                if (!$dos) { $errors[] = "Claim {$row['claim_number']}: no DOS"; continue; }

                // Create claim
                $claim = Claim::create([
                    'agency_id' => $aid,
                    'billing_client_id' => $clientId,
                    'claim_number' => $row['claim_number'] ?? 'CLM-' . str_pad($i + 1, 6, '0', STR_PAD_LEFT),
                    'claim_type' => '837P',
                    'status' => 'submitted',
                    'patient_name' => $row['patient_name'] ?? null,
                    'patient_dob' => $row['patient_dob'] ?? null,
                    'patient_member_id' => $row['patient_member_id'] ?? null,
                    'payer_name' => $row['payer_name'] ?? null,
                    'payer_id_number' => $row['payer_id'] ?? null,
                    'provider_name' => $row['provider_name'] ?? null,
                    'date_of_service' => $dos,
                    'total_charges' => $row['total_charges'] ?? 0,
                    'balance' => $row['total_charges'] ?? 0,
                    'submission_method' => 'electronic',
                    'submitted_date' => now()->toDateString(),
                    'created_by' => $uid,
                ]);

                // Create service lines + charge entries
                foreach ($row['service_lines'] ?? [] as $j => $sl) {
                    \App\Models\ClaimServiceLine::create([
                        'claim_id' => $claim->id,
                        'line_number' => $j + 1,
                        'cpt_code' => $sl['cpt_code'] ?? '',
                        'charges' => $sl['charge_amount'] ?? 0,
                        'units' => $sl['units'] ?? 1,
                        'icd_codes' => $row['icd_codes'] ?? '',
                    ]);

                    \App\Models\ChargeEntry::create([
                        'agency_id' => $aid,
                        'billing_client_id' => $clientId,
                        'claim_id' => $claim->id,
                        'patient_name' => $row['patient_name'] ?? null,
                        'payer_name' => $row['payer_name'] ?? null,
                        'provider_name' => $row['provider_name'] ?? null,
                        'date_of_service' => $sl['date_of_service'] ?? $dos,
                        'cpt_code' => $sl['cpt_code'] ?? '',
                        'icd_codes' => $row['icd_codes'] ?? '',
                        'units' => $sl['units'] ?? 1,
                        'charge_amount' => $sl['charge_amount'] ?? 0,
                        'status' => 'submitted',
                        'created_by' => $uid,
                    ]);
                    $chargesCreated++;
                }

                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Claim " . ($row['claim_number'] ?? $i) . ": " . $e->getMessage();
            }
        }

        return response()->json(['success' => true, 'imported_claims' => $imported, 'charges_created' => $chargesCreated, 'errors' => $errors], 201);
    }

    // ══════════════════════════════════════════════════
    // 12. AI DENIAL PREVENTION
    // ══════════════════════════════════════════════════

    public function denialRiskAnalysis(Request $request): JsonResponse
    {
        $aid = $request->user()->agency_id;

        // Analyze historical denial patterns
        $denials = ClaimDenial::where('agency_id', $aid)
            ->with('claim:id,payer_name,claim_type')
            ->get();

        if ($denials->isEmpty()) {
            return response()->json(['success' => true, 'data' => [
                'risk_factors' => [],
                'payer_denial_rates' => [],
                'category_breakdown' => [],
                'recommendations' => ['Not enough denial data to generate risk analysis. Import more claims to build patterns.'],
            ]]);
        }

        // Denial rate by payer
        $allClaims = Claim::where('agency_id', $aid)->get();
        $byPayer = $allClaims->groupBy('payer_name')->map(function ($claims, $payer) use ($denials) {
            $total = $claims->count();
            $denied = $claims->where('status', 'denied')->count();
            $payerDenials = $denials->filter(fn($d) => ($d->claim->payer_name ?? '') === $payer);
            $topCategory = $payerDenials->groupBy('denial_category')->sortByDesc(fn($g) => $g->count())->keys()->first();
            return [
                'payer' => $payer,
                'total_claims' => $total,
                'denied' => $denied,
                'denial_rate' => $total > 0 ? round($denied / $total * 100, 1) : 0,
                'top_denial_category' => $topCategory,
                'avg_denied_amount' => $payerDenials->count() > 0 ? round($payerDenials->avg(fn($d) => (float)$d->denied_amount), 2) : 0,
            ];
        })->sortByDesc('denial_rate')->values();

        // Category patterns
        $catBreakdown = $denials->groupBy('denial_category')->map(fn($g, $cat) => [
            'category' => $cat,
            'count' => $g->count(),
            'total_amount' => round($g->sum(fn($d) => (float) $d->denied_amount), 2),
            'avg_amount' => round($g->avg(fn($d) => (float) $d->denied_amount), 2),
            'pct' => round($g->count() / $denials->count() * 100, 1),
        ])->sortByDesc('count')->values();

        // Generate risk factors & recommendations
        $riskFactors = [];
        $recommendations = [];

        foreach ($byPayer as $p) {
            if ($p['denial_rate'] > 20) {
                $riskFactors[] = [
                    'level' => 'high',
                    'payer' => $p['payer'],
                    'message' => "{$p['payer']} has a {$p['denial_rate']}% denial rate ({$p['denied']} of {$p['total_claims']} claims).",
                    'category' => $p['top_denial_category'],
                ];
                $rec = match($p['top_denial_category']) {
                    'authorization' => "Verify prior authorization is on file before submitting claims to {$p['payer']}.",
                    'timely_filing' => "Submit claims to {$p['payer']} within 48 hours of DOS. Current pattern shows timely filing issues.",
                    'coding' => "Review coding accuracy for {$p['payer']} claims. Consider a coding audit.",
                    'eligibility' => "Check patient eligibility with {$p['payer']} before every visit.",
                    'medical_necessity' => "Ensure documentation supports medical necessity for {$p['payer']} claims.",
                    'documentation' => "Strengthen documentation before submitting to {$p['payer']}.",
                    default => "Review denial patterns for {$p['payer']} and implement preventive checks.",
                };
                $recommendations[] = $rec;
            }
        }

        if (empty($riskFactors)) {
            $recommendations[] = 'Denial rates are within acceptable range. Continue monitoring.';
        }

        return response()->json(['success' => true, 'data' => [
            'risk_factors' => $riskFactors,
            'payer_denial_rates' => $byPayer,
            'category_breakdown' => $catBreakdown,
            'recommendations' => $recommendations,
            'total_denials' => $denials->count(),
            'total_claims' => $allClaims->count(),
            'overall_denial_rate' => $allClaims->count() > 0 ? round($allClaims->where('status', 'denied')->count() / $allClaims->count() * 100, 1) : 0,
        ]]);
    }

    // Pre-submission risk check for a specific claim
    public function preSubmissionCheck(Request $request): JsonResponse
    {
        $request->validate(['payer_name' => 'required', 'cpt_code' => 'required']);
        $aid = $request->user()->agency_id;

        $payer = $request->payer_name;
        $cpt = $request->cpt_code;

        // Historical denial data for this payer + CPT combo
        $historicalDenials = ClaimDenial::where('agency_id', $aid)
            ->whereHas('claim', fn($q) => $q->where('payer_name', $payer))
            ->get();

        $totalPayerClaims = Claim::where('agency_id', $aid)->where('payer_name', $payer)->count();
        $deniedPayerClaims = Claim::where('agency_id', $aid)->where('payer_name', $payer)->where('status', 'denied')->count();
        $payerDenialRate = $totalPayerClaims > 0 ? round($deniedPayerClaims / $totalPayerClaims * 100, 1) : 0;

        $warnings = [];
        $riskLevel = 'low';

        if ($payerDenialRate > 30) {
            $warnings[] = "HIGH RISK: {$payer} has a {$payerDenialRate}% denial rate. Double-check all requirements.";
            $riskLevel = 'high';
        } elseif ($payerDenialRate > 15) {
            $warnings[] = "MODERATE RISK: {$payer} has a {$payerDenialRate}% denial rate.";
            $riskLevel = 'medium';
        }

        // Check common denial reasons for this payer
        $topReasons = $historicalDenials->groupBy('denial_category')->sortByDesc(fn($g) => $g->count())->take(3);
        foreach ($topReasons as $cat => $denials) {
            $pct = round($denials->count() / max($historicalDenials->count(), 1) * 100);
            if ($pct > 20) {
                $warnings[] = "Common denial reason: {$cat} ({$pct}% of denials). Verify this is addressed.";
            }
        }

        // Check fee schedule
        $feeSchedule = FeeSchedule::where('agency_id', $aid)->where('payer_name', $payer)->where('cpt_code', $cpt)->first();
        if (!$feeSchedule) {
            $warnings[] = "No fee schedule found for {$payer} + CPT {$cpt}. Expected payment unknown.";
        }

        return response()->json(['success' => true, 'data' => [
            'risk_level' => $riskLevel,
            'payer_denial_rate' => $payerDenialRate,
            'warnings' => $warnings,
            'fee_schedule' => $feeSchedule,
            'total_payer_claims' => $totalPayerClaims,
        ]]);
    }

    // ══════════════════════════════════════════════════
    // 13. PAYER INTELLIGENCE HUB
    // ══════════════════════════════════════════════════

    public function payerRules(Request $request): JsonResponse
    {
        $rules = PayerRule::where('agency_id', $request->user()->agency_id)->orderBy('payer_name')->get();
        return response()->json(['success' => true, 'data' => $rules]);
    }

    public function showPayerRule(Request $request, string $payerName): JsonResponse
    {
        $rule = PayerRule::where('agency_id', $request->user()->agency_id)
            ->where('payer_name', $payerName)->first();

        // Also pull live stats for this payer
        $aid = $request->user()->agency_id;
        $claims = Claim::where('agency_id', $aid)->where('payer_name', $payerName)->get();
        $denials = ClaimDenial::where('agency_id', $aid)
            ->whereHas('claim', fn($q) => $q->where('payer_name', $payerName))->get();

        $stats = [
            'total_claims' => $claims->count(),
            'total_charged' => round($claims->sum(fn($c) => (float)$c->total_charges), 2),
            'total_paid' => round($claims->sum(fn($c) => (float)$c->total_paid), 2),
            'denied_count' => $claims->where('status', 'denied')->count(),
            'denial_rate' => $claims->count() > 0 ? round($claims->where('status', 'denied')->count() / $claims->count() * 100, 1) : 0,
            'avg_days_to_pay' => $claims->whereNotNull('paid_date')->count() > 0
                ? round($claims->whereNotNull('paid_date')->avg(fn($c) => abs(now()->parse($c->date_of_service)->diffInDays(now()->parse($c->paid_date)))))
                : null,
            'top_denial_categories' => $denials->groupBy('denial_category')
                ->map(fn($g, $k) => ['category' => $k, 'count' => $g->count()])
                ->sortByDesc('count')->values()->take(5),
            'collection_rate' => $claims->sum(fn($c) => (float)$c->total_charges) > 0
                ? round($claims->sum(fn($c) => (float)$c->total_paid) / $claims->sum(fn($c) => (float)$c->total_charges) * 100, 1) : 0,
        ];

        return response()->json(['success' => true, 'data' => [
            'rule' => $rule,
            'stats' => $stats,
        ]]);
    }

    public function storePayerRule(Request $request): JsonResponse
    {
        $request->validate(['payer_name' => 'required|string|max:100']);
        $rule = PayerRule::updateOrCreate(
            ['agency_id' => $request->user()->agency_id, 'payer_name' => $request->payer_name],
            array_merge(
                $request->only([
                    'timely_filing_days', 'appeal_filing_days', 'corrected_claim_days',
                    'portal_url', 'provider_phone', 'claims_address', 'appeals_address', 'appeals_fax',
                    'electronic_payer_id', 'auth_required_cpts', 'bundling_rules',
                    'medical_necessity_notes', 'common_denial_reasons', 'credentialing_requirements',
                    'reimbursement_notes', 'billing_tips',
                ]),
                ['created_by' => $request->user()->id]
            )
        );
        return response()->json(['success' => true, 'data' => $rule], 201);
    }

    public function updatePayerRule(Request $request, int $id): JsonResponse
    {
        $rule = PayerRule::where('agency_id', $request->user()->agency_id)->findOrFail($id);
        $rule->update($request->only([
            'payer_name', 'timely_filing_days', 'appeal_filing_days', 'corrected_claim_days',
            'portal_url', 'provider_phone', 'claims_address', 'appeals_address', 'appeals_fax',
            'electronic_payer_id', 'auth_required_cpts', 'bundling_rules',
            'medical_necessity_notes', 'common_denial_reasons', 'credentialing_requirements',
            'reimbursement_notes', 'billing_tips',
        ]));
        return response()->json(['success' => true, 'data' => $rule]);
    }

    public function destroyPayerRule(Request $request, int $id): JsonResponse
    {
        PayerRule::where('agency_id', $request->user()->agency_id)->findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    // Check claim against payer rules before submission
    public function checkPayerRules(Request $request): JsonResponse
    {
        $request->validate(['payer_name' => 'required', 'date_of_service' => 'required|date']);
        $aid = $request->user()->agency_id;
        $rule = PayerRule::where('agency_id', $aid)->where('payer_name', $request->payer_name)->first();

        $warnings = [];
        $dos = now()->parse($request->date_of_service);
        $daysSinceDos = $dos->diffInDays(now());

        if ($rule) {
            // Timely filing check
            if ($rule->timely_filing_days && $daysSinceDos > $rule->timely_filing_days) {
                $warnings[] = ['level' => 'critical', 'message' => "TIMELY FILING RISK: {$daysSinceDos} days since DOS. {$rule->payer_name} limit is {$rule->timely_filing_days} days."];
            } elseif ($rule->timely_filing_days && $daysSinceDos > $rule->timely_filing_days * 0.8) {
                $warnings[] = ['level' => 'warning', 'message' => "Approaching timely filing limit: {$daysSinceDos}/{$rule->timely_filing_days} days."];
            }

            // Auth requirement check
            $cpt = $request->input('cpt_code');
            if ($cpt && $rule->auth_required_cpts && in_array($cpt, $rule->auth_required_cpts)) {
                $warnings[] = ['level' => 'warning', 'message' => "CPT {$cpt} requires prior authorization for {$rule->payer_name}."];
            }

            // Bundling check
            if ($rule->bundling_rules) {
                foreach ($rule->bundling_rules as $br) {
                    if ($cpt === ($br['primary'] ?? '') || $cpt === ($br['cannot_bill_with'] ?? '')) {
                        $warnings[] = ['level' => 'info', 'message' => "Bundling rule: {$br['primary']} cannot be billed with {$br['cannot_bill_with']}."];
                    }
                }
            }
        } else {
            $warnings[] = ['level' => 'info', 'message' => "No payer rules configured for {$request->payer_name}. Consider adding them."];
        }

        return response()->json(['success' => true, 'data' => [
            'payer_name' => $request->payer_name,
            'rule' => $rule,
            'warnings' => $warnings,
            'days_since_dos' => $daysSinceDos,
        ]]);
    }

    // ══════════════════════════════════════════════════
    // 14. DUPLICATE CLAIM DETECTION
    // ══════════════════════════════════════════════════

    public function detectDuplicates(Request $request): JsonResponse
    {
        $aid = $request->user()->agency_id;
        $claims = Claim::where('agency_id', $aid)->get();

        $duplicates = [];
        $seen = [];

        foreach ($claims as $claim) {
            // Key: patient + DOS + payer + charges
            $key = strtolower(trim($claim->patient_name ?? '')) . '|' .
                   ($claim->date_of_service ?? '') . '|' .
                   strtolower(trim($claim->payer_name ?? '')) . '|' .
                   number_format((float)$claim->total_charges, 2);

            if (isset($seen[$key])) {
                if (!isset($duplicates[$key])) {
                    $duplicates[$key] = ['original' => $seen[$key], 'duplicates' => []];
                }
                $duplicates[$key]['duplicates'][] = [
                    'id' => $claim->id,
                    'claim_number' => $claim->claim_number,
                    'status' => $claim->status,
                    'submitted_date' => $claim->submitted_date,
                ];
            } else {
                $seen[$key] = [
                    'id' => $claim->id,
                    'claim_number' => $claim->claim_number,
                    'patient_name' => $claim->patient_name,
                    'payer_name' => $claim->payer_name,
                    'date_of_service' => $claim->date_of_service,
                    'total_charges' => $claim->total_charges,
                    'status' => $claim->status,
                ];
            }
        }

        $groups = array_values($duplicates);

        return response()->json(['success' => true, 'data' => [
            'duplicate_groups' => $groups,
            'total_duplicates' => array_sum(array_map(fn($g) => count($g['duplicates']), $groups)),
            'total_claims_checked' => $claims->count(),
        ]]);
    }

    // ══════════════════════════════════════════════════
    // 15. PROVIDER FEEDBACK LOOP
    // ══════════════════════════════════════════════════

    public function providerFeedback(Request $request): JsonResponse
    {
        $query = ProviderFeedback::where('agency_id', $request->user()->agency_id)
            ->with(['claim:id,claim_number', 'denial:id,denial_category,denial_code']);
        if ($p = $request->input('provider_name')) $query->where('provider_name', $p);
        if ($s = $request->input('status')) $query->where('status', $s);
        return response()->json(['success' => true, 'data' => $query->orderByDesc('created_at')->get()]);
    }

    public function storeProviderFeedback(Request $request): JsonResponse
    {
        $request->validate(['provider_name' => 'required', 'feedback_type' => 'required', 'issue' => 'required', 'recommendation' => 'required']);
        $fb = ProviderFeedback::create([
            'agency_id' => $request->user()->agency_id,
            'created_by' => $request->user()->id,
            ...$request->only([
                'provider_id', 'provider_name', 'claim_id', 'denial_id',
                'feedback_type', 'cpt_code', 'payer_name', 'issue', 'recommendation',
            ]),
        ]);
        return response()->json(['success' => true, 'data' => $fb], 201);
    }

    public function updateProviderFeedback(Request $request, int $id): JsonResponse
    {
        $fb = ProviderFeedback::where('agency_id', $request->user()->agency_id)->findOrFail($id);
        $fb->update($request->only(['status', 'sent_date', 'provider_response']));
        return response()->json(['success' => true, 'data' => $fb]);
    }

    // Auto-generate feedback from denials with coding/documentation issues
    public function autoGenerateFeedback(Request $request): JsonResponse
    {
        $aid = $request->user()->agency_id;
        $uid = $request->user()->id;

        $codingDenials = ClaimDenial::where('agency_id', $aid)
            ->whereIn('denial_category', ['coding', 'documentation', 'medical_necessity', 'authorization'])
            ->whereDoesntHave('claim', fn($q) => $q->whereHas('provider'))  // skip if no provider
            ->with(['claim:id,claim_number,provider_name,payer_name,date_of_service'])
            ->get();

        // Also check denials that DO have provider info
        $codingDenials = ClaimDenial::where('agency_id', $aid)
            ->whereIn('denial_category', ['coding', 'documentation', 'medical_necessity', 'authorization'])
            ->with(['claim:id,claim_number,provider_name,payer_name,date_of_service'])
            ->get();

        $created = 0;
        foreach ($codingDenials as $denial) {
            $claim = $denial->claim;
            if (!$claim || !$claim->provider_name) continue;

            // Skip if feedback already exists for this denial
            $exists = ProviderFeedback::where('agency_id', $aid)->where('denial_id', $denial->id)->exists();
            if ($exists) continue;

            $recommendation = match($denial->denial_category) {
                'coding' => "Review CPT/ICD coding for accuracy. Ensure codes match the documentation and services rendered. Consider using more specific codes.",
                'documentation' => "Ensure clinical documentation supports the billed services. Include treatment goals, progress, and medical necessity justification.",
                'medical_necessity' => "Document medical necessity clearly. Include symptom severity, functional impairment, and why this level of service is required.",
                'authorization' => "Verify prior authorization is obtained before rendering services. Check authorization number and covered date range.",
                default => "Review the denial reason and adjust billing practices accordingly.",
            };

            ProviderFeedback::create([
                'agency_id' => $aid,
                'provider_name' => $claim->provider_name,
                'claim_id' => $claim->id,
                'denial_id' => $denial->id,
                'feedback_type' => $denial->denial_category,
                'cpt_code' => $denial->denial_code,
                'payer_name' => $claim->payer_name,
                'issue' => "Claim {$claim->claim_number} denied by {$claim->payer_name}: {$denial->denial_reason}",
                'recommendation' => $recommendation,
                'status' => 'pending',
                'created_by' => $uid,
            ]);
            $created++;
        }

        return response()->json(['success' => true, 'generated' => $created]);
    }

    // ══════════════════════════════════════════════════
    // 16. REAL-TIME ELIGIBILITY (STEDI)
    // ══════════════════════════════════════════════════

    public function realTimeEligibility(Request $request): JsonResponse
    {
        $request->validate(['patient_name' => 'required', 'payer_name' => 'required', 'member_id' => 'required']);
        $aid = $request->user()->agency_id;
        $config = $request->user()->agency->config ?? null;
        $stediNpi = $config->stedi_npi ?? null;

        $check = EligibilityCheck::create([
            'agency_id' => $aid,
            'created_by' => $request->user()->id,
            'status' => 'pending',
            ...$request->only(['billing_client_id', 'patient_name', 'patient_dob', 'member_id', 'payer_name', 'payer_id', 'provider_npi']),
        ]);

        // Try Stedi API if configured
        $stediKey = env('STEDI_API_KEY');
        if ($stediKey && $request->member_id) {
            try {
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'Authorization' => "Key {$stediKey}",
                    'Content-Type' => 'application/json',
                ])->post('https://healthcare.us.stedi.com/2024-04-01/change/medicalnetwork/eligibility/v3', [
                    'controlNumber' => (string) rand(100000000, 999999999),
                    'tradingPartnerServiceId' => $request->payer_id ?? $request->payer_name,
                    'provider' => [
                        'organizationName' => $request->user()->agency->name ?? '',
                        'npi' => $request->provider_npi ?? $stediNpi ?? '',
                    ],
                    'subscriber' => [
                        'memberId' => $request->member_id,
                        'firstName' => explode(' ', $request->patient_name)[0] ?? '',
                        'lastName' => explode(' ', $request->patient_name)[1] ?? '',
                        'dateOfBirth' => $request->patient_dob ?? '',
                    ],
                    'encounter' => ['serviceTypeCodes' => ['30']], // Health benefit plan coverage
                ]);

                $data = $response->json();
                $isActive = ($data['planStatus'][0]['statusCode'] ?? '') === '1';
                $planName = $data['planStatus'][0]['planDetails'] ?? $data['planName'] ?? null;

                // Extract benefits
                $copay = null; $deductible = null; $deductibleMet = null; $oopMax = null; $oopMet = null;
                foreach ($data['benefitsInformation'] ?? [] as $b) {
                    if (($b['code'] ?? '') === 'B' && ($b['insuranceTypeCode'] ?? '') === 'IND') $copay = $b['benefitAmount'] ?? null;
                    if (($b['code'] ?? '') === 'C') $deductible = $b['benefitAmount'] ?? null;
                    if (($b['code'] ?? '') === 'G') $oopMax = $b['benefitAmount'] ?? null;
                }

                $check->update([
                    'status' => $isActive ? 'active' : 'inactive',
                    'is_active' => $isActive,
                    'plan_name' => $planName,
                    'copay' => $copay,
                    'deductible' => $deductible,
                    'deductible_met' => $deductibleMet,
                    'out_of_pocket_max' => $oopMax,
                    'oop_met' => $oopMet,
                    'raw_response' => $data,
                ]);
            } catch (\Exception $e) {
                $check->update([
                    'status' => 'error',
                    'error_message' => 'Stedi API error: ' . $e->getMessage(),
                ]);
            }
        } else {
            $check->update([
                'status' => 'pending',
                'error_message' => $stediKey ? 'Member ID required for real-time check.' : 'STEDI_API_KEY not configured. Set it in environment variables for real-time eligibility.',
            ]);
        }

        return response()->json(['success' => true, 'data' => $check], 201);
    }

    // ══════════════════════════════════════════════════
    // 17. AI PAYER POLICY EXTRACTION
    // ══════════════════════════════════════════════════

    public function extractPayerPolicy(Request $request): JsonResponse
    {
        $aid = $request->user()->agency_id;
        $ai = new AiService();

        if ($request->has('pdf_url')) {
            $result = $ai->extractPayerPolicy($request->pdf_url);
        } elseif ($request->has('policy_text')) {
            $result = $ai->extractPayerPolicyFromText($request->policy_text);
        } else {
            return response()->json(['success' => false, 'error' => 'Provide pdf_url or policy_text'], 400);
        }

        if (isset($result['error'])) {
            return response()->json(['success' => false, 'error' => $result['error']], 422);
        }

        $extracted = $result['data'] ?? [];

        // Auto-save as payer rule if payer_name was extracted
        if (!empty($extracted['payer_name']) && $request->input('auto_save', false)) {
            PayerRule::updateOrCreate(
                ['agency_id' => $aid, 'payer_name' => $extracted['payer_name']],
                array_filter([
                    'timely_filing_days' => $extracted['timely_filing_days'] ?? null,
                    'appeal_filing_days' => $extracted['appeal_filing_days'] ?? null,
                    'corrected_claim_days' => $extracted['corrected_claim_days'] ?? null,
                    'portal_url' => $extracted['portal_url'] ?? null,
                    'provider_phone' => $extracted['provider_phone'] ?? null,
                    'claims_address' => $extracted['claims_address'] ?? null,
                    'appeals_address' => $extracted['appeals_address'] ?? null,
                    'appeals_fax' => $extracted['appeals_fax'] ?? null,
                    'electronic_payer_id' => $extracted['electronic_payer_id'] ?? null,
                    'auth_required_cpts' => $extracted['auth_required_cpts'] ?? null,
                    'bundling_rules' => $extracted['bundling_rules'] ?? null,
                    'medical_necessity_notes' => $extracted['medical_necessity_notes'] ?? null,
                    'common_denial_reasons' => $extracted['common_denial_reasons'] ?? null,
                    'credentialing_requirements' => $extracted['credentialing_requirements'] ?? null,
                    'reimbursement_notes' => $extracted['reimbursement_notes'] ?? null,
                    'billing_tips' => $extracted['billing_tips'] ?? null,
                    'created_by' => $request->user()->id,
                ], fn($v) => $v !== null)
            );
        }

        return response()->json(['success' => true, 'data' => $extracted]);
    }

    // ══════════════════════════════════════════════════
    // 18. CHARGE-CLAIM-PAYMENT RECONCILIATION
    // ══════════════════════════════════════════════════

    /**
     * Auto-match charges to claims by patient + DOS + payer.
     * Links unlinked charges to their corresponding claims.
     */
    public function autoReconcile(Request $request): JsonResponse
    {
        $aid = $request->user()->agency_id;

        $unlinkedCharges = \App\Models\ChargeEntry::where('agency_id', $aid)
            ->whereNull('claim_id')
            ->get();

        $claims = Claim::where('agency_id', $aid)->get();
        $matched = 0;

        foreach ($unlinkedCharges as $charge) {
            $patientKey = strtolower(trim($charge->patient_name ?? ''));
            $dos = $charge->date_of_service ? $charge->date_of_service->format('Y-m-d') : null;
            $payer = strtolower(trim($charge->payer_name ?? ''));

            if (!$patientKey || !$dos) continue;

            // Find matching claim: same patient + DOS + payer
            $match = $claims->first(function ($c) use ($patientKey, $dos, $payer) {
                $cPatient = strtolower(trim($c->patient_name ?? ''));
                $cDos = $c->date_of_service ? $c->date_of_service->format('Y-m-d') : null;
                $cPayer = strtolower(trim($c->payer_name ?? ''));
                return $cPatient === $patientKey && $cDos === $dos && ($payer === $cPayer || !$payer || !$cPayer);
            });

            if ($match) {
                $charge->update(['claim_id' => $match->id, 'status' => $match->status === 'paid' ? 'billed' : 'submitted']);
                $matched++;
            }
        }

        return response()->json(['success' => true, 'matched' => $matched, 'unlinked_remaining' => $unlinkedCharges->count() - $matched]);
    }

    /**
     * Full reconciliation report — shows gaps across charges, claims, payments.
     */
    public function reconciliationReport(Request $request): JsonResponse
    {
        $aid = $request->user()->agency_id;
        $now = now();

        $charges = \App\Models\ChargeEntry::where('agency_id', $aid)->get();
        $claims = Claim::where('agency_id', $aid)->get();
        $payments = \App\Models\PaymentAllocation::whereHas('claim', fn($q) => $q->where('agency_id', $aid))->get();

        // 1. Unbilled charges — charges with no linked claim
        $unbilledCharges = $charges->whereNull('claim_id')->values()->map(fn($c) => [
            'id' => $c->id,
            'patient_name' => $c->patient_name,
            'date_of_service' => $c->date_of_service,
            'cpt_code' => $c->cpt_code,
            'payer_name' => $c->payer_name,
            'charge_amount' => $c->charge_amount,
            'status' => $c->status,
            'days_old' => $c->date_of_service ? $now->diffInDays($c->date_of_service) : 0,
        ]);

        // 2. Claims with no payment after 30+ days
        $unpaidClaims = $claims
            ->whereIn('status', ['submitted', 'acknowledged', 'pending', 'in_process'])
            ->filter(fn($c) => $c->date_of_service && $now->diffInDays($c->date_of_service) > 30)
            ->values()->map(fn($c) => [
                'id' => $c->id,
                'claim_number' => $c->claim_number,
                'patient_name' => $c->patient_name,
                'payer_name' => $c->payer_name,
                'date_of_service' => $c->date_of_service,
                'total_charges' => $c->total_charges,
                'balance' => $c->balance,
                'days_old' => $now->diffInDays($c->date_of_service),
                'status' => $c->status,
            ]);

        // 3. Paid claims with remaining balance (partial payments / underpayments)
        $partiallyPaid = $claims
            ->where('total_paid', '>', 0)
            ->where('balance', '>', 0)
            ->values()->map(fn($c) => [
                'id' => $c->id,
                'claim_number' => $c->claim_number,
                'patient_name' => $c->patient_name,
                'payer_name' => $c->payer_name,
                'total_charges' => $c->total_charges,
                'total_paid' => $c->total_paid,
                'balance' => $c->balance,
                'status' => $c->status,
            ]);

        // 4. Charges linked to claims — revenue traced
        $reconciledCharges = $charges->whereNotNull('claim_id')->count();
        $totalChargeAmount = $charges->sum(fn($c) => (float) $c->charge_amount);
        $totalClaimCharges = $claims->sum(fn($c) => (float) $c->total_charges);
        $totalCollected = $claims->sum(fn($c) => (float) $c->total_paid);
        $totalDenied = $claims->where('status', 'denied')->sum(fn($c) => (float) $c->total_charges);
        $totalUnbilled = $unbilledCharges->sum('charge_amount');

        // 5. Revenue pipeline summary
        $pipeline = [
            'charges_entered' => ['count' => $charges->count(), 'amount' => round($totalChargeAmount, 2)],
            'charges_reconciled' => ['count' => $reconciledCharges, 'amount' => round($charges->whereNotNull('claim_id')->sum(fn($c) => (float) $c->charge_amount), 2)],
            'charges_unbilled' => ['count' => $unbilledCharges->count(), 'amount' => round($totalUnbilled, 2)],
            'claims_submitted' => ['count' => $claims->count(), 'amount' => round($totalClaimCharges, 2)],
            'claims_paid' => ['count' => $claims->whereIn('status', ['paid', 'partial_paid'])->count(), 'amount' => round($totalCollected, 2)],
            'claims_denied' => ['count' => $claims->where('status', 'denied')->count(), 'amount' => round($totalDenied, 2)],
            'claims_pending' => ['count' => $unpaidClaims->count(), 'amount' => round($unpaidClaims->sum('total_charges'), 2)],
            'partially_paid' => ['count' => $partiallyPaid->count(), 'amount' => round($partiallyPaid->sum('balance'), 2)],
            'collection_rate' => $totalClaimCharges > 0 ? round($totalCollected / $totalClaimCharges * 100, 1) : 0,
        ];

        return response()->json(['success' => true, 'data' => [
            'pipeline' => $pipeline,
            'unbilled_charges' => $unbilledCharges,
            'unpaid_claims' => $unpaidClaims,
            'partially_paid' => $partiallyPaid,
        ]]);
    }
}

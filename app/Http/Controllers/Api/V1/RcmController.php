<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ChargeEntry;
use App\Models\Claim;
use App\Models\ClaimDenial;
use App\Models\ClaimPayment;
use App\Models\ClaimServiceLine;
use App\Models\PaymentAllocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RcmController extends Controller
{
    // ── Claims ──

    public function claims(Request $request): JsonResponse
    {
        $query = Claim::where('agency_id', $request->user()->agency_id)
            ->with(['billingClient:id,organization_name', 'serviceLines']);
        if ($cid = $request->input('billing_client_id')) $query->where('billing_client_id', $cid);
        if ($s = $request->input('status')) $query->where('status', $s);
        if ($from = $request->input('from_date')) $query->where('date_of_service', '>=', $from);
        if ($to = $request->input('to_date')) $query->where('date_of_service', '<=', $to);
        return response()->json(['success' => true, 'data' => $query->orderByDesc('date_of_service')->paginate(100)]);
    }

    public function showClaim(Request $request, int $id): JsonResponse
    {
        $claim = Claim::where('agency_id', $request->user()->agency_id)
            ->with(['billingClient:id,organization_name', 'serviceLines', 'denials', 'paymentAllocations', 'followups'])
            ->findOrFail($id);
        return response()->json(['success' => true, 'data' => $claim]);
    }

    public function storeClaim(Request $request): JsonResponse
    {
        $request->validate([
            'date_of_service' => 'required|date',
            'service_lines' => 'nullable|array',
            'service_lines.*.cpt_code' => 'required_with:service_lines|string|max:10',
        ]);
        $count = Claim::where('agency_id', $request->user()->agency_id)->count() + 1;
        $claim = Claim::create([
            'agency_id' => $request->user()->agency_id,
            'claim_number' => 'CLM-' . str_pad($count, 6, '0', STR_PAD_LEFT),
            'created_by' => $request->user()->id,
            ...$request->only([
                'billing_client_id', 'claim_type', 'status', 'provider_id', 'provider_name',
                'patient_name', 'patient_dob', 'patient_member_id', 'payer_name', 'payer_id_number',
                'date_of_service', 'date_of_service_end', 'place_of_service', 'facility_name',
                'referring_provider', 'authorization_number', 'total_charges', 'submission_method',
                'clearinghouse', 'submitted_date', 'notes',
            ]),
        ]);
        if ($request->has('service_lines')) {
            foreach ($request->service_lines as $i => $line) {
                ClaimServiceLine::create(['claim_id' => $claim->id, 'line_number' => $i + 1, ...$line]);
            }
            $claim->recalculate();
        }
        $claim->load(['serviceLines', 'billingClient:id,organization_name']);
        return response()->json(['success' => true, 'data' => $claim], 201);
    }

    public function updateClaim(Request $request, int $id): JsonResponse
    {
        $claim = Claim::where('agency_id', $request->user()->agency_id)->findOrFail($id);
        $claim->update($request->only([
            'billing_client_id', 'claim_type', 'status', 'provider_id', 'provider_name',
            'patient_name', 'patient_dob', 'patient_member_id', 'payer_name', 'payer_id_number',
            'date_of_service', 'date_of_service_end', 'place_of_service', 'facility_name',
            'referring_provider', 'authorization_number', 'total_charges', 'total_allowed',
            'total_paid', 'patient_responsibility', 'adjustments', 'balance',
            'submission_method', 'clearinghouse', 'submitted_date', 'acknowledged_date',
            'adjudicated_date', 'paid_date', 'check_number', 'denial_reason', 'denial_codes',
            'appeal_deadline', 'notes',
        ]));
        if ($request->has('service_lines')) {
            $claim->serviceLines()->delete();
            foreach ($request->service_lines as $i => $line) {
                ClaimServiceLine::create(['claim_id' => $claim->id, 'line_number' => $i + 1, ...$line]);
            }
            $claim->recalculate();
        }
        $claim->load(['serviceLines', 'billingClient:id,organization_name']);
        return response()->json(['success' => true, 'data' => $claim]);
    }

    public function destroyClaim(Request $request, int $id): JsonResponse
    {
        Claim::where('agency_id', $request->user()->agency_id)->findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    public function bulkImportClaims(Request $request): JsonResponse
    {
        $request->validate(['claims' => 'required|array|min:1|max:500']);

        $agencyId = $request->user()->agency_id;
        $userId = $request->user()->id;
        $baseCount = Claim::where('agency_id', $agencyId)->count();
        $created = 0;
        $errors = [];

        foreach ($request->claims as $i => $row) {
            try {
                if (empty($row['date_of_service'])) {
                    $errors[] = "Row " . ($i + 1) . ": date_of_service is required";
                    continue;
                }
                // Use source claim number if available, otherwise auto-generate
                $claimNumber = $row['payer_id_number'] ?? $row['claim_number'] ?? null;
                if (!$claimNumber) {
                    $claimNumber = 'CLM-' . str_pad($baseCount + $created + 1, 6, '0', STR_PAD_LEFT);
                }

                // Duplicate check: same agency + claim_number + date_of_service + patient_name
                $dupeQuery = Claim::where('agency_id', $agencyId)
                    ->where('claim_number', $claimNumber)
                    ->where('date_of_service', $row['date_of_service']);
                if (!empty($row['patient_name'])) {
                    $dupeQuery->where('patient_name', $row['patient_name']);
                }
                if ($dupeQuery->exists()) {
                    $errors[] = "Row " . ($i + 1) . ": duplicate claim ({$claimNumber} on {$row['date_of_service']})";
                    continue;
                }

                $claim = Claim::create([
                    'agency_id' => $agencyId,
                    'claim_number' => $claimNumber,
                    'created_by' => $userId,
                    'claim_type' => $row['claim_type'] ?? '837P',
                    'status' => $row['status'] ?? 'submitted',
                    'billing_client_id' => $row['billing_client_id'] ?? null,
                    'provider_name' => $row['provider_name'] ?? null,
                    'patient_name' => $row['patient_name'] ?? null,
                    'patient_dob' => $row['patient_dob'] ?? null,
                    'patient_member_id' => $row['patient_member_id'] ?? null,
                    'payer_name' => $row['payer_name'] ?? null,
                    'payer_id_number' => $row['payer_id_number'] ?? null,
                    'date_of_service' => $row['date_of_service'],
                    'date_of_service_end' => $row['date_of_service_end'] ?? null,
                    'place_of_service' => $row['place_of_service'] ?? null,
                    'facility_name' => $row['facility_name'] ?? null,
                    'referring_provider' => $row['referring_provider'] ?? null,
                    'authorization_number' => $row['authorization_number'] ?? null,
                    'total_charges' => $row['total_charges'] ?? 0,
                    'total_paid' => $row['total_paid'] ?? 0,
                    'patient_responsibility' => $row['patient_responsibility'] ?? 0,
                    'balance' => ($row['total_charges'] ?? 0) - ($row['total_paid'] ?? 0),
                    'submission_method' => $row['submission_method'] ?? 'electronic',
                    'submitted_date' => $row['submitted_date'] ?? null,
                    'paid_date' => $row['paid_date'] ?? null,
                    'check_number' => $row['check_number'] ?? null,
                    'denial_reason' => $row['denial_reason'] ?? null,
                    'notes' => $row['notes'] ?? null,
                ]);

                // Auto-create denial record if status is denied
                if ($claim->status === 'denied') {
                    ClaimDenial::create([
                        'agency_id' => $agencyId,
                        'claim_id' => $claim->id,
                        'billing_client_id' => $claim->billing_client_id,
                        'denial_category' => 'other',
                        'denial_reason' => $row['denial_reason'] ?? 'Imported as denied',
                        'denied_amount' => $claim->total_charges,
                        'status' => 'new',
                        'priority' => 'normal',
                        'denial_date' => $claim->date_of_service,
                        'created_by' => $userId,
                    ]);
                }

                // Create service lines if CPT code provided
                if (!empty($row['cpt_code'])) {
                    ClaimServiceLine::create([
                        'claim_id' => $claim->id,
                        'line_number' => 1,
                        'cpt_code' => $row['cpt_code'],
                        'cpt_description' => $row['cpt_description'] ?? '',
                        'modifiers' => $row['modifiers'] ?? '',
                        'icd_codes' => $row['icd_codes'] ?? '',
                        'units' => $row['units'] ?? 1,
                        'charges' => $row['total_charges'] ?? 0,
                        'paid_amount' => $row['total_paid'] ?? 0,
                    ]);
                }

                $created++;
            } catch (\Exception $e) {
                $errors[] = "Row " . ($i + 1) . ": " . $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'imported' => $created,
            'errors' => $errors,
            'total_submitted' => count($request->claims),
        ], 201);
    }

    public function claimStats(Request $request): JsonResponse
    {
        $aid = $request->user()->agency_id;
        $claims = Claim::where('agency_id', $aid)->get();
        $totalClaims = $claims->count();
        $totalCharged = $claims->sum(fn($c) => (float) $c->total_charges);
        $totalPaid = $claims->sum(fn($c) => (float) $c->total_paid);
        $totalBalance = $claims->sum(fn($c) => (float) $c->balance);
        $pendingCount = $claims->whereIn('status', ['submitted', 'acknowledged', 'pending'])->count();
        $paidCount = $claims->whereIn('status', ['paid', 'partial_paid'])->count();
        $deniedCount = $claims->where('status', 'denied')->count();
        $totalPatientResp = $claims->sum(fn($c) => (float) $c->patient_responsibility);
        $totalDeniedAmount = $claims->where('status', 'denied')->sum(fn($c) => (float) $c->total_charges);

        // Monthly breakdown for charts (configurable range)
        $monthRange = (int) ($request->input('months', 6));
        if ($monthRange < 1) $monthRange = 6;
        if ($monthRange > 24) $monthRange = 24;
        $monthly = [];
        for ($m = $monthRange - 1; $m >= 0; $m--) {
            $date = now()->subMonths($m);
            $key = $date->format('Y-m');
            $monthClaims = $claims->filter(fn($c) => substr($c->date_of_service, 0, 7) === $key);
            $monthly[] = [
                'period' => $key,
                'claims_submitted' => $monthClaims->count(),
                'amount_billed' => round($monthClaims->sum(fn($c) => (float) $c->total_charges), 2),
                'amount_collected' => round($monthClaims->sum(fn($c) => (float) $c->total_paid), 2),
                'denied_amount' => round($monthClaims->where('status', 'denied')->sum(fn($c) => (float) $c->total_charges), 2),
            ];
        }

        return response()->json(['success' => true, 'data' => [
            'total_claims' => $totalClaims,
            'total_charged' => round($totalCharged, 2),
            'total_paid' => round($totalPaid, 2),
            'total_balance' => round($totalBalance, 2),
            'total_patient_responsibility' => round($totalPatientResp, 2),
            'total_denied_amount' => round($totalDeniedAmount, 2),
            'pending_count' => $pendingCount,
            'paid_count' => $paidCount,
            'denied_count' => $deniedCount,
            'clean_claim_rate' => $totalClaims > 0 ? round(($totalClaims - $deniedCount) / $totalClaims * 100, 1) : 0,
            'collection_rate' => $totalCharged > 0 ? round($totalPaid / $totalCharged * 100, 1) : 0,
            'monthly' => $monthly,
        ]]);
    }

    // ── Denials ──

    public function denials(Request $request): JsonResponse
    {
        $query = ClaimDenial::where('agency_id', $request->user()->agency_id)
            ->with(['claim:id,claim_number,payer_name,patient_name,total_charges', 'billingClient:id,organization_name']);
        if ($cid = $request->input('billing_client_id')) $query->where('billing_client_id', $cid);
        if ($s = $request->input('status')) $query->where('status', $s);
        if ($cat = $request->input('category')) $query->where('denial_category', $cat);
        return response()->json(['success' => true, 'data' => $query->orderByDesc('created_at')->get()]);
    }

    public function storeDenial(Request $request): JsonResponse
    {
        $request->validate([
            'claim_id' => 'required|exists:claims,id',
            'denial_category' => 'required|string|max:30',
            'denial_reason' => 'required|string|max:500',
        ]);
        $claim = Claim::where('agency_id', $request->user()->agency_id)->findOrFail($request->claim_id);
        $denial = ClaimDenial::create([
            'agency_id' => $request->user()->agency_id,
            'billing_client_id' => $claim->billing_client_id,
            'created_by' => $request->user()->id,
            ...$request->only([
                'claim_id', 'denial_category', 'denial_code', 'denial_reason', 'denied_amount',
                'status', 'priority', 'denial_date', 'appeal_deadline', 'assigned_to',
            ]),
        ]);
        $claim->update(['status' => 'denied', 'denial_reason' => $request->denial_reason]);
        return response()->json(['success' => true, 'data' => $denial->load('claim:id,claim_number,payer_name')], 201);
    }

    public function updateDenial(Request $request, int $id): JsonResponse
    {
        $denial = ClaimDenial::where('agency_id', $request->user()->agency_id)->findOrFail($id);
        $data = $request->only([
            'denial_category', 'denial_code', 'denial_reason', 'denied_amount', 'status', 'priority',
            'appeal_deadline', 'appeal_level', 'appeal_submitted_date', 'recovered_amount',
            'appeal_notes', 'resolution_notes', 'assigned_to',
        ]);
        if (in_array($data['status'] ?? '', ['resolved_won', 'resolved_lost', 'resolved_partial', 'written_off']) && !$denial->resolved_at) {
            $data['resolved_at'] = now();
        }
        $denial->update($data);
        return response()->json(['success' => true, 'data' => $denial]);
    }

    public function destroyDenial(Request $request, int $id): JsonResponse
    {
        ClaimDenial::where('agency_id', $request->user()->agency_id)->findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    public function denialStats(Request $request): JsonResponse
    {
        $aid = $request->user()->agency_id;
        $total = ClaimDenial::where('agency_id', $aid)->count();
        $open = ClaimDenial::where('agency_id', $aid)->whereIn('status', ['new', 'in_review', 'appeal_in_progress', 'pending_response'])->count();
        $totalDenied = ClaimDenial::where('agency_id', $aid)->sum('denied_amount');
        $totalRecovered = ClaimDenial::where('agency_id', $aid)->sum('recovered_amount');
        $won = ClaimDenial::where('agency_id', $aid)->where('status', 'resolved_won')->count();
        $lost = ClaimDenial::where('agency_id', $aid)->where('status', 'resolved_lost')->count();
        $appealRate = ($won + $lost) > 0 ? round($won / ($won + $lost) * 100, 1) : 0;
        $overdue = ClaimDenial::where('agency_id', $aid)->whereIn('status', ['new', 'in_review'])
            ->whereNotNull('appeal_deadline')->where('appeal_deadline', '<', now())->count();
        $byCategory = ClaimDenial::where('agency_id', $aid)
            ->selectRaw('denial_category, COUNT(*) as count, SUM(denied_amount) as total')
            ->groupBy('denial_category')->get();
        return response()->json(['success' => true, 'data' => [
            'total' => $total, 'open' => $open, 'total_denied' => $totalDenied,
            'total_recovered' => $totalRecovered, 'appeal_success_rate' => $appealRate,
            'overdue_appeals' => $overdue, 'by_category' => $byCategory,
        ]]);
    }

    // ── Payments ──

    public function payments(Request $request): JsonResponse
    {
        $query = ClaimPayment::where('agency_id', $request->user()->agency_id)
            ->with(['billingClient:id,organization_name', 'allocations.claim:id,claim_number']);
        if ($cid = $request->input('billing_client_id')) $query->where('billing_client_id', $cid);
        if ($s = $request->input('status')) $query->where('status', $s);
        return response()->json(['success' => true, 'data' => $query->orderByDesc('payment_date')->get()]);
    }

    public function storePayment(Request $request): JsonResponse
    {
        $request->validate([
            'payment_type' => 'required|in:check,eft,virtual_card,patient,ach',
            'payment_date' => 'required|date',
            'total_amount' => 'required|numeric|min:0.01',
        ]);
        $payment = ClaimPayment::create([
            'agency_id' => $request->user()->agency_id,
            'created_by' => $request->user()->id,
            'remaining_amount' => $request->total_amount,
            ...$request->only([
                'billing_client_id', 'payer_name', 'payment_type', 'check_number',
                'trace_number', 'payment_date', 'deposit_date', 'total_amount', 'notes',
            ]),
        ]);
        if ($request->has('allocations')) {
            foreach ($request->allocations as $alloc) {
                PaymentAllocation::create(['claim_payment_id' => $payment->id, ...$alloc]);
                $claim = Claim::find($alloc['claim_id'] ?? null);
                if ($claim) {
                    $claim->total_paid = ($claim->total_paid ?? 0) + ($alloc['paid_amount'] ?? 0);
                    $claim->balance = $claim->total_charges - $claim->total_paid - ($claim->adjustments ?? 0);
                    if ($claim->balance <= 0) $claim->status = 'paid';
                    elseif ($claim->total_paid > 0) $claim->status = 'partial_paid';
                    $claim->paid_date = $request->payment_date;
                    $claim->save();
                }
            }
            $payment->recalculate();
        }
        $payment->load(['allocations.claim:id,claim_number', 'billingClient:id,organization_name']);
        return response()->json(['success' => true, 'data' => $payment], 201);
    }

    public function updatePayment(Request $request, int $id): JsonResponse
    {
        $payment = ClaimPayment::where('agency_id', $request->user()->agency_id)->findOrFail($id);
        $payment->update($request->only([
            'billing_client_id', 'payer_name', 'payment_type', 'check_number',
            'trace_number', 'payment_date', 'deposit_date', 'total_amount', 'status', 'notes',
        ]));
        return response()->json(['success' => true, 'data' => $payment]);
    }

    public function destroyPayment(Request $request, int $id): JsonResponse
    {
        ClaimPayment::where('agency_id', $request->user()->agency_id)->findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    // ── Charge Capture ──

    public function charges(Request $request): JsonResponse
    {
        $query = ChargeEntry::where('agency_id', $request->user()->agency_id)
            ->with(['billingClient:id,organization_name']);
        if ($cid = $request->input('billing_client_id')) $query->where('billing_client_id', $cid);
        if ($s = $request->input('status')) $query->where('status', $s);
        return response()->json(['success' => true, 'data' => $query->orderByDesc('date_of_service')->get()]);
    }

    public function storeCharge(Request $request): JsonResponse
    {
        $request->validate([
            'date_of_service' => 'required|date',
            'cpt_code' => 'required|string|max:10',
            'charge_amount' => 'required|numeric|min:0',
        ]);
        $charge = ChargeEntry::create([
            'agency_id' => $request->user()->agency_id,
            'created_by' => $request->user()->id,
            ...$request->only([
                'billing_client_id', 'provider_id', 'provider_name', 'patient_name', 'payer_name',
                'date_of_service', 'cpt_code', 'cpt_description', 'modifiers', 'icd_codes',
                'icd_descriptions', 'units', 'charge_amount', 'allowed_amount', 'place_of_service',
                'facility_name', 'authorization_number', 'status', 'notes',
            ]),
        ]);
        return response()->json(['success' => true, 'data' => $charge], 201);
    }

    public function updateCharge(Request $request, int $id): JsonResponse
    {
        $charge = ChargeEntry::where('agency_id', $request->user()->agency_id)->findOrFail($id);
        $charge->update($request->only([
            'billing_client_id', 'provider_id', 'provider_name', 'patient_name', 'payer_name',
            'date_of_service', 'cpt_code', 'cpt_description', 'modifiers', 'icd_codes',
            'icd_descriptions', 'units', 'charge_amount', 'allowed_amount', 'place_of_service',
            'facility_name', 'authorization_number', 'status', 'claim_id', 'notes',
        ]));
        return response()->json(['success' => true, 'data' => $charge]);
    }

    public function destroyCharge(Request $request, int $id): JsonResponse
    {
        ChargeEntry::where('agency_id', $request->user()->agency_id)->findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    public function bulkImportCharges(Request $request): JsonResponse
    {
        $request->validate(['charges' => 'required|array|min:1|max:500']);
        $agencyId = $request->user()->agency_id;
        $userId = $request->user()->id;
        $created = 0;
        $errors = [];

        foreach ($request->charges as $i => $row) {
            try {
                if (empty($row['date_of_service'])) { $errors[] = "Row " . ($i + 1) . ": date_of_service required"; continue; }
                if (empty($row['cpt_code'])) { $errors[] = "Row " . ($i + 1) . ": cpt_code required"; continue; }

                ChargeEntry::create([
                    'agency_id' => $agencyId,
                    'created_by' => $userId,
                    'billing_client_id' => $row['billing_client_id'] ?? null,
                    'provider_name' => $row['provider_name'] ?? null,
                    'patient_name' => $row['patient_name'] ?? null,
                    'payer_name' => $row['payer_name'] ?? null,
                    'date_of_service' => $row['date_of_service'],
                    'cpt_code' => $row['cpt_code'],
                    'cpt_description' => $row['cpt_description'] ?? '',
                    'modifiers' => $row['modifiers'] ?? '',
                    'icd_codes' => $row['icd_codes'] ?? '',
                    'icd_descriptions' => $row['icd_descriptions'] ?? '',
                    'units' => $row['units'] ?? 1,
                    'charge_amount' => $row['charge_amount'] ?? $row['total_charges'] ?? 0,
                    'allowed_amount' => $row['allowed_amount'] ?? 0,
                    'place_of_service' => $row['place_of_service'] ?? null,
                    'authorization_number' => $row['authorization_number'] ?? null,
                    'status' => $row['status'] ?? 'pending',
                    'notes' => $row['notes'] ?? null,
                ]);
                $created++;
            } catch (\Exception $e) {
                $errors[] = "Row " . ($i + 1) . ": " . $e->getMessage();
            }
        }

        return response()->json(['success' => true, 'imported' => $created, 'errors' => $errors, 'total_submitted' => count($request->charges)], 201);
    }

    // ── AR Aging ──

    public function arAging(Request $request): JsonResponse
    {
        $aid = $request->user()->agency_id;
        $openStatuses = ['submitted', 'acknowledged', 'pending', 'partial_paid', 'in_process'];
        $claims = Claim::where('agency_id', $aid)->whereIn('status', $openStatuses)
            ->with(['billingClient:id,organization_name'])->where('balance', '>', 0)
            ->orderBy('date_of_service')->get();

        $now = now();
        $buckets = ['0_30' => [], '31_60' => [], '61_90' => [], '91_plus' => []];
        $byPayer = [];

        foreach ($claims as $c) {
            $days = $now->diffInDays($c->date_of_service);
            $bucket = $days <= 30 ? '0_30' : ($days <= 60 ? '31_60' : ($days <= 90 ? '61_90' : '91_plus'));
            $buckets[$bucket][] = $c;
            $payer = $c->payer_name ?: 'Unknown';
            if (!isset($byPayer[$payer])) $byPayer[$payer] = ['payer' => $payer, 'total' => 0, 'count' => 0, 'days_sum' => 0];
            $byPayer[$payer]['total'] += $c->balance;
            $byPayer[$payer]['count']++;
            $byPayer[$payer]['days_sum'] += $days;
        }
        foreach ($byPayer as &$p) {
            $p['avg_days'] = $p['count'] > 0 ? round($p['days_sum'] / $p['count']) : 0;
            unset($p['days_sum']);
        }

        return response()->json(['success' => true, 'data' => [
            'total_ar' => $claims->sum('balance'),
            'avg_days_in_ar' => $claims->count() > 0 ? round($claims->avg(fn($c) => $now->diffInDays($c->date_of_service))) : 0,
            'claim_count' => $claims->count(),
            'buckets' => [
                '0_30' => ['count' => count($buckets['0_30']), 'total' => collect($buckets['0_30'])->sum('balance')],
                '31_60' => ['count' => count($buckets['31_60']), 'total' => collect($buckets['31_60'])->sum('balance')],
                '61_90' => ['count' => count($buckets['61_90']), 'total' => collect($buckets['61_90'])->sum('balance')],
                '91_plus' => ['count' => count($buckets['91_plus']), 'total' => collect($buckets['91_plus'])->sum('balance')],
            ],
            'by_payer' => array_values($byPayer),
            'claims' => $claims,
        ]]);
    }
}

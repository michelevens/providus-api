<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\BillingActivity;
use App\Models\BillingClient;
use App\Models\BillingFinancial;
use App\Models\BillingTask;
use App\Models\Claim;
use App\Models\ClaimDenial;
use App\Models\ClaimPayment;
use App\Models\ClientPaymentLedger;
use App\Models\License;
use App\Models\Provider;
use App\Services\BrandingResolver;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingServiceController extends Controller
{
    // ── Billing Clients ──

    public function clients(Request $request): JsonResponse
    {
        $clients = BillingClient::where('agency_id', $request->user()->effectiveAgencyId($request))
            ->with(['organization:id,name'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['success' => true, 'data' => $clients]);
    }

    public function showClient(Request $request, int $id): JsonResponse
    {
        $client = BillingClient::where('agency_id', $request->user()->effectiveAgencyId($request))
            ->with(['organization:id,name', 'tasks', 'activities', 'financials'])
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $client]);
    }

    public function storeClient(Request $request): JsonResponse
    {
        $request->validate([
            'organization_name' => 'required|string|max:200',
            'organization_id' => 'nullable|exists:organizations,id',
            'contact_name' => 'nullable|string|max:200',
            'contact_email' => 'nullable|email|max:200',
            'contact_phone' => 'nullable|string|max:30',
            'billing_platform' => 'nullable|string|max:50',
            'monthly_fee' => 'nullable|numeric|min:0',
            'fee_structure' => 'nullable|in:flat,per_provider,percentage,per_claim',
            'payment_mode' => 'nullable|in:agency_managed,self_managed',
            'agency_fee_percent' => 'nullable|numeric|min:0|max:100',
            'status' => 'nullable|in:onboarding,active,paused,cancelled',
            'start_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $client = BillingClient::create([
            'agency_id' => $request->user()->effectiveAgencyId($request),
            'created_by' => $request->user()->id,
            ...$request->only([
                'organization_id', 'organization_name',
                'contact_name', 'contact_email', 'contact_phone',
                'billing_platform', 'monthly_fee', 'fee_structure',
                'payment_mode', 'agency_fee_percent',
                'status', 'start_date', 'notes',
            ]),
        ]);

        return response()->json(['success' => true, 'data' => $client], 201);
    }

    public function updateClient(Request $request, int $id): JsonResponse
    {
        $client = BillingClient::where('agency_id', $request->user()->effectiveAgencyId($request))->findOrFail($id);

        $request->validate([
            'organization_name' => 'sometimes|string|max:200',
            'organization_id' => 'nullable|exists:organizations,id',
            'contact_name' => 'nullable|string|max:200',
            'contact_email' => 'nullable|email|max:200',
            'contact_phone' => 'nullable|string|max:30',
            'billing_platform' => 'nullable|string|max:50',
            'monthly_fee' => 'nullable|numeric|min:0',
            'fee_structure' => 'nullable|in:flat,per_provider,percentage,per_claim',
            'payment_mode' => 'nullable|in:agency_managed,self_managed',
            'agency_fee_percent' => 'nullable|numeric|min:0|max:100',
            'status' => 'nullable|in:onboarding,active,paused,cancelled',
            'start_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $client->update($request->only([
            'organization_id', 'organization_name',
            'contact_name', 'contact_email', 'contact_phone',
            'billing_platform', 'monthly_fee', 'fee_structure',
            'payment_mode', 'agency_fee_percent',
            'status', 'start_date', 'notes',
        ]));

        return response()->json(['success' => true, 'data' => $client]);
    }

    public function destroyClient(Request $request, int $id): JsonResponse
    {
        $client = BillingClient::where('agency_id', $request->user()->effectiveAgencyId($request))->findOrFail($id);
        $client->delete();
        return response()->json(['success' => true]);
    }

    /**
     * Monthly statement — a single document the agency hands to a
     * client org each month showing what the agency did for them.
     *
     * Combines two service lines:
     *   - RCM (when the client has claims in our ledger)
     *   - Credentialing (when the client's org has applications/providers)
     *
     * Sections auto-hide when there's nothing to report — a client who
     * only buys credentialing gets a credentialing-only statement, no
     * empty "Claims activity" panel with zeros.
     *
     * NOT a P&L: we don't see payroll/rent/taxes. NOT an invoice (those
     * live at /invoices). This is a "what we did for you this period"
     * recap, period.
     *
     * Period: 'YYYY-MM' (e.g. '2026-04'). Defaults to last calendar month.
     */
    public function monthlyStatement(Request $request, int $id): JsonResponse
    {
        $agencyId = $request->user()->effectiveAgencyId($request);
        $client = BillingClient::where('agency_id', $agencyId)
            ->with(['organization:id,name'])
            ->findOrFail($id);

        // Period parsing. Default = previous calendar month so end-of-
        // month statements work without a date picker on the happy path.
        $periodInput = $request->input('period');
        $period = $periodInput
            ? Carbon::createFromFormat('Y-m', $periodInput)?->startOfMonth()
            : Carbon::now()->subMonthNoOverflow()->startOfMonth();
        if (!$period) {
            return response()->json(['success' => false, 'error' => 'Invalid period — use YYYY-MM'], 422);
        }
        $start = $period->copy()->startOfMonth();
        $end = $period->copy()->endOfMonth();

        // ─── RCM section ─────────────────────────────────────────────
        // Scope: all claims for this billing client. We compute two
        // overlapping windows: "activity in period" (anything that was
        // submitted, paid, or denied during the month — what we did
        // for them) and "outstanding A/R" (anything still open, no
        // matter when it was submitted — what we're chasing for them).
        $claimsBase = Claim::where('agency_id', $agencyId)
            ->where('billing_client_id', $client->id);
        $totalClaimsForClient = (clone $claimsBase)->count();

        $rcm = null;
        if ($totalClaimsForClient > 0) {
            $submittedInPeriod = (clone $claimsBase)
                ->whereBetween('submitted_date', [$start, $end])
                ->get(['id', 'claim_number', 'patient_name', 'payer_name', 'total_charges', 'date_of_service', 'submitted_date', 'status']);
            $paidInPeriod = (clone $claimsBase)
                ->whereBetween('paid_date', [$start, $end])
                ->where('total_paid', '>', 0)
                ->get(['id', 'claim_number', 'patient_name', 'provider_name', 'payer_name', 'total_charges', 'total_paid', 'paid_date', 'check_number']);
            $deniedInPeriod = ClaimDenial::where('agency_id', $agencyId)
                ->whereHas('claim', fn ($q) => $q->where('billing_client_id', $client->id))
                ->whereBetween('denial_date', [$start, $end])
                ->with('claim:id,claim_number,patient_name,payer_name,total_charges')
                ->get();

            // Outstanding A/R right now (point-in-time, not period-scoped).
            $TERMINAL = ['paid', 'denied', 'written_off', 'closed', 'voided', 'rejected', 'recouped', 'cancelled'];
            $openClaims = (clone $claimsBase)
                ->where('balance', '>', 0)
                ->whereNotIn('status', $TERMINAL)
                ->get(['id', 'claim_number', 'patient_name', 'payer_name', 'date_of_service', 'submitted_date', 'total_charges', 'total_paid', 'balance', 'status', 'created_at']);
            $aging = ['0-30' => 0.0, '31-60' => 0.0, '61-90' => 0.0, '90+' => 0.0];
            $agingCounts = ['0-30' => 0, '31-60' => 0, '61-90' => 0, '90+' => 0];
            foreach ($openClaims as $c) {
                $clockFrom = $c->submitted_date ?: $c->date_of_service ?: $c->created_at;
                $days = $clockFrom ? Carbon::parse($clockFrom)->diffInDays(now()) : 0;
                $bucket = $days <= 30 ? '0-30' : ($days <= 60 ? '31-60' : ($days <= 90 ? '61-90' : '90+'));
                $aging[$bucket] += (float) $c->balance;
                $agingCounts[$bucket]++;
            }

            // Top denial reasons in period.
            $denialReasons = [];
            foreach ($deniedInPeriod as $d) {
                $reason = $d->denial_reason ?: ($d->denial_code ?: 'Unspecified');
                if (!isset($denialReasons[$reason])) $denialReasons[$reason] = ['count' => 0, 'amount' => 0.0];
                $denialReasons[$reason]['count']++;
                $denialReasons[$reason]['amount'] += (float) ($d->denied_amount ?: 0);
            }
            uasort($denialReasons, fn ($a, $b) => $b['count'] - $a['count']);
            $topDenialReasons = array_slice(
                array_map(fn ($k, $v) => ['reason' => $k, 'count' => $v['count'], 'amount' => round($v['amount'], 2)],
                    array_keys($denialReasons), array_values($denialReasons)),
                0, 5);

            // Payer mix from paid-in-period.
            $payerMix = [];
            foreach ($paidInPeriod as $p) {
                $payer = $p->payer_name ?: 'Unknown';
                if (!isset($payerMix[$payer])) $payerMix[$payer] = ['paid' => 0.0, 'claims' => 0];
                $payerMix[$payer]['paid'] += (float) $p->total_paid;
                $payerMix[$payer]['claims']++;
            }
            uasort($payerMix, fn ($a, $b) => $b['paid'] <=> $a['paid']);
            $payerMix = array_map(fn ($k, $v) => ['payer' => $k, 'paid' => round($v['paid'], 2), 'claims' => $v['claims']],
                array_keys($payerMix), array_values($payerMix));

            // Per-provider productivity.
            $byProvider = [];
            foreach ($paidInPeriod as $p) {
                $prov = $p->provider_name ?: 'Unassigned';
                if (!isset($byProvider[$prov])) $byProvider[$prov] = ['paid' => 0.0, 'claims' => 0, 'billed' => 0.0];
                $byProvider[$prov]['paid'] += (float) $p->total_paid;
                $byProvider[$prov]['billed'] += (float) $p->total_charges;
                $byProvider[$prov]['claims']++;
            }
            uasort($byProvider, fn ($a, $b) => $b['paid'] <=> $a['paid']);
            $byProvider = array_map(fn ($k, $v) => [
                'provider' => $k,
                'claims' => $v['claims'],
                'billed' => round($v['billed'], 2),
                'paid' => round($v['paid'], 2),
            ], array_keys($byProvider), array_values($byProvider));

            $rcm = [
                'submitted_count' => $submittedInPeriod->count(),
                'submitted_charges' => round($submittedInPeriod->sum('total_charges'), 2),
                'paid_count' => $paidInPeriod->count(),
                'paid_amount' => round($paidInPeriod->sum('total_paid'), 2),
                'paid_charges' => round($paidInPeriod->sum('total_charges'), 2),
                'denied_count' => $deniedInPeriod->count(),
                'denied_amount' => round($deniedInPeriod->sum('denied_amount'), 2),
                'open_ar_total' => round(array_sum($aging), 2),
                'open_ar_count' => array_sum($agingCounts),
                'aging' => array_map(fn ($k) => [
                    'bucket' => $k,
                    'amount' => round($aging[$k], 2),
                    'count' => $agingCounts[$k],
                ], array_keys($aging)),
                'top_denial_reasons' => $topDenialReasons,
                'payer_mix' => array_slice($payerMix, 0, 10),
                'by_provider' => $byProvider,
            ];
        }

        // ─── Credentialing section ───────────────────────────────────
        // Scope: applications for this client's organization. We don't
        // store applications against billing_client directly today;
        // they're org-scoped. Joining via organization_id is the right
        // bridge.
        $credentialing = null;
        if ($client->organization_id) {
            $appsBase = Application::where('agency_id', $agencyId)
                ->where('organization_id', $client->organization_id);
            $totalApps = (clone $appsBase)->count();
            if ($totalApps > 0) {
                $submittedApps = (clone $appsBase)
                    ->whereBetween('submitted_date', [$start, $end])
                    ->get(['id', 'state', 'payer_name', 'submitted_date', 'status']);
                // "Approved" can land via either status='approved' or 'credentialed'.
                $approvedApps = (clone $appsBase)
                    ->whereIn('status', ['approved', 'credentialed'])
                    ->whereBetween('effective_date', [$start, $end])
                    ->get(['id', 'state', 'payer_name', 'effective_date', 'status']);
                $inFlight = (clone $appsBase)
                    ->whereIn('status', ['submitted', 'in_review', 'pending_info', 'gathering_docs'])
                    ->get(['id', 'state', 'payer_name', 'status', 'submitted_date']);
                $needsClientAction = (clone $appsBase)
                    ->where('status', 'pending_info')
                    ->get(['id', 'state', 'payer_name', 'submitted_date']);

                // Expirations: licenses + DEA in next 90 days for the
                // org's providers. Provider list is everyone with an
                // application under this org.
                $providerIds = (clone $appsBase)->whereNotNull('provider_id')->pluck('provider_id')->unique()->all();
                $expiring = [];
                if (!empty($providerIds)) {
                    $horizon = now()->addDays(90);
                    $expiring = License::where('agency_id', $agencyId)
                        ->whereIn('provider_id', $providerIds)
                        ->whereNotNull('expiration_date')
                        ->whereBetween('expiration_date', [now(), $horizon])
                        ->orderBy('expiration_date')
                        ->with('provider:id,first_name,last_name')
                        ->get()
                        ->map(fn ($l) => [
                            'type' => 'license',
                            'state' => $l->state,
                            'license_number' => $l->license_number,
                            'license_type' => $l->license_type,
                            'expiration_date' => $l->expiration_date?->format('Y-m-d'),
                            'provider_name' => $l->provider ? trim($l->provider->first_name . ' ' . $l->provider->last_name) : null,
                        ])->all();
                }

                $providerCount = Provider::where('agency_id', $agencyId)
                    ->whereIn('id', $providerIds ?: [0])
                    ->count();

                $credentialing = [
                    'provider_count' => $providerCount,
                    'submitted_count' => $submittedApps->count(),
                    'approved_count' => $approvedApps->count(),
                    'in_flight_count' => $inFlight->count(),
                    'needs_action_count' => $needsClientAction->count(),
                    'expiring_count' => count($expiring),
                    'submitted' => $submittedApps->map(fn ($a) => [
                        'id' => $a->id, 'state' => $a->state, 'payer' => $a->payer_name,
                        'submitted_date' => $a->submitted_date?->format('Y-m-d'), 'status' => $a->status,
                    ]),
                    'approved' => $approvedApps->map(fn ($a) => [
                        'id' => $a->id, 'state' => $a->state, 'payer' => $a->payer_name,
                        'effective_date' => $a->effective_date?->format('Y-m-d'),
                    ]),
                    'in_flight' => $inFlight->map(fn ($a) => [
                        'id' => $a->id, 'state' => $a->state, 'payer' => $a->payer_name, 'status' => $a->status,
                        'submitted_date' => $a->submitted_date?->format('Y-m-d'),
                    ]),
                    'needs_client_action' => $needsClientAction->map(fn ($a) => [
                        'id' => $a->id, 'state' => $a->state, 'payer' => $a->payer_name,
                        'submitted_date' => $a->submitted_date?->format('Y-m-d'),
                    ]),
                    'expiring' => $expiring,
                ];
            }
        }

        // ─── Agency fees this period (for context, not invoice replacement) ─
        $financials = BillingFinancial::where('billing_client_id', $client->id)
            ->whereBetween('period_start', [$start, $end])
            ->get();
        $feesThisPeriod = round($financials->sum('agency_fee'), 2);

        return response()->json([
            'success' => true,
            'data' => [
                'client' => [
                    'id' => $client->id,
                    'organization_name' => $client->organization_name,
                    'contact_name' => $client->contact_name,
                    'contact_email' => $client->contact_email,
                    'display_name' => $client->display_name,
                    'logo_url' => $client->logo_url,
                ],
                'agency' => [
                    'name' => $request->user()->agency?->name,
                    'contact_email' => $request->user()->agency?->config?->public_email ?? null,
                ],
                'period' => $start->format('Y-m'),
                'period_label' => $start->format('F Y'),
                'period_start' => $start->format('Y-m-d'),
                'period_end' => $end->format('Y-m-d'),
                'generated_at' => now()->toIso8601String(),
                'rcm' => $rcm,
                'credentialing' => $credentialing,
                'fees_this_period' => $feesThisPeriod,
                'has_rcm' => $rcm !== null,
                'has_credentialing' => $credentialing !== null,
            ],
        ]);
    }

    // ── Per-practice branding override ──
    // GET returns BOTH the raw billing-client overrides (so the UI can
    // show what's been set vs inherited) AND the resolved brand (so the
    // UI can preview what a patient will actually see). Two-shape
    // response avoids a second roundtrip for the preview.

    public function getClientBranding(Request $request, int $id): JsonResponse
    {
        $client = BillingClient::where('agency_id', $request->user()->effectiveAgencyId($request))->findOrFail($id);
        $resolved = BrandingResolver::forBillingClient($client);

        return response()->json([
            'success' => true,
            'data' => [
                'overrides' => [
                    'display_name' => $client->display_name,
                    'primary_color' => $client->primary_color,
                    'accent_color' => $client->accent_color,
                    'logo_url' => $client->logo_url,
                    'public_email' => $client->public_email,
                    'public_phone' => $client->public_phone,
                    'address_street' => $client->address_street,
                    'address_city' => $client->address_city,
                    'address_state' => $client->address_state,
                    'address_zip' => $client->address_zip,
                    'email_footer' => $client->email_footer,
                ],
                'resolved' => $resolved,
            ],
        ]);
    }

    public function updateClientBranding(Request $request, int $id): JsonResponse
    {
        $client = BillingClient::where('agency_id', $request->user()->effectiveAgencyId($request))->findOrFail($id);

        $request->validate([
            'display_name' => 'nullable|string|max:200',
            // Hex colors only — same validation as the agency Branding endpoint.
            'primary_color' => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'accent_color' => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            // logo_url can be a full URL or a data: URI for small inline logos.
            // 2MB cap keeps anyone from stuffing huge base64 into the DB.
            'logo_url' => 'nullable|string|max:2097152',
            'public_email' => 'nullable|email|max:200',
            'public_phone' => 'nullable|string|max:30',
            'address_street' => 'nullable|string|max:200',
            'address_city' => 'nullable|string|max:100',
            'address_state' => 'nullable|string|size:2',
            'address_zip' => 'nullable|string|max:12',
            'email_footer' => 'nullable|string|max:1000',
        ]);

        // Nullables that arrive as empty string should null out the
        // override, NOT save "". Filter empties.
        $fields = collect($request->only([
            'display_name', 'primary_color', 'accent_color', 'logo_url',
            'public_email', 'public_phone',
            'address_street', 'address_city', 'address_state', 'address_zip',
            'email_footer',
        ]))->map(fn ($v) => $v === '' ? null : $v)->all();

        $client->update($fields);

        return response()->json([
            'success' => true,
            'data' => [
                'overrides' => $fields,
                'resolved' => BrandingResolver::forBillingClient($client->fresh()),
            ],
        ]);
    }

    public function clientStats(Request $request): JsonResponse
    {
        $agencyId = $request->user()->effectiveAgencyId($request);

        $totalClients = BillingClient::where('agency_id', $agencyId)->count();
        $activeClients = BillingClient::where('agency_id', $agencyId)->where('status', 'active')->count();
        $totalTasks = BillingTask::where('agency_id', $agencyId)->count();
        $pendingTasks = BillingTask::where('agency_id', $agencyId)->whereIn('status', ['pending', 'in_progress'])->count();
        $completedTasks = BillingTask::where('agency_id', $agencyId)->where('status', 'completed')->count();
        $totalClaims = BillingFinancial::where('agency_id', $agencyId)->sum('claims_submitted');
        $totalCollected = BillingFinancial::where('agency_id', $agencyId)->sum('amount_collected');
        $totalDenied = BillingFinancial::where('agency_id', $agencyId)->sum('denied_amount');

        return response()->json(['success' => true, 'data' => [
            'total_clients' => $totalClients,
            'active_clients' => $activeClients,
            'total_tasks' => $totalTasks,
            'pending_tasks' => $pendingTasks,
            'completed_tasks' => $completedTasks,
            'total_claims' => $totalClaims,
            'total_collected' => $totalCollected,
            'total_denied' => $totalDenied,
        ]]);
    }

    /**
     * Cross-practice portfolio view — one row per BillingClient with
     * computed KPIs from the claim ledger. The Agency Owner's Monday
     * morning screen. All math comes from Claim (the source of truth)
     * not BillingFinancial (the rollup table) so windows are exact.
     *
     * Window param: 7 / 30 / 90 / ytd. Defaults to 30.
     *
     * For each practice we compute:
     *   - billed: sum total_charges of claims submitted in window
     *   - collected: sum total_paid of claims paid in window
     *   - collection_pct: collected / billed
     *   - ar_total: outstanding balance on open claims (any DOS)
     *   - ar_90plus: outstanding balance on claims >90 days old
     *   - open_denials: count + dollar amount of unworked denials
     *   - days_in_ar: avg age of open-balance claims
     *   - clean_claim_rate: paid_on_first_pass / submitted
     *   - last_activity_at: most recent claim event (any kind)
     *   - health_score: 0-100 composite (higher = healthier)
     *   - action_chip: derived label for the operator
     *
     * Health score: deliberately simple weighted average so an operator
     * can mentally reverse-engineer the number. Not a black-box ML score.
     *   40% collection_pct (target 95%+)
     *   20% inverse of days_in_ar (target <30d)
     *   20% inverse of denial_rate (target <5%)
     *   20% inverse of ar_90plus_pct (target <10%)
     */
    public function crossPracticeView(Request $request): JsonResponse
    {
        $agencyId = $request->user()->effectiveAgencyId($request);
        $window = (string) $request->input('window', '30');

        $cutoff = match ($window) {
            '7' => now()->subDays(7),
            '90' => now()->subDays(90),
            'ytd' => now()->startOfYear(),
            default => now()->subDays(30),
        };

        $clients = BillingClient::where('agency_id', $agencyId)->get();
        $rows = [];
        $portfolio = ['billed' => 0.0, 'collected' => 0.0, 'ar_total' => 0.0, 'ar_90plus' => 0.0, 'denials_open' => 0, 'denials_open_amount' => 0.0];

        foreach ($clients as $client) {
            $claimsQuery = Claim::where('agency_id', $agencyId)
                ->where('billing_client_id', $client->id);

            // Window-scoped figures
            $billed = (clone $claimsQuery)
                ->whereNotNull('submitted_date')
                ->where('submitted_date', '>=', $cutoff)
                ->sum('total_charges');

            $collected = (clone $claimsQuery)
                ->whereNotNull('paid_date')
                ->where('paid_date', '>=', $cutoff)
                ->sum('total_paid');

            $submittedCount = (clone $claimsQuery)
                ->whereNotNull('submitted_date')
                ->where('submitted_date', '>=', $cutoff)
                ->count();

            $cleanCount = (clone $claimsQuery)
                ->whereNotNull('submitted_date')
                ->where('submitted_date', '>=', $cutoff)
                ->whereDoesntHave('denials')
                ->count();

            $cleanRate = $submittedCount > 0
                ? round(($cleanCount / $submittedCount) * 100, 1)
                : null;

            // Open A/R (any DOS, balance > 0, not paid)
            $openClaims = (clone $claimsQuery)
                ->whereIn('status', ['submitted', 'acknowledged', 'pending', 'partial_paid', 'in_process'])
                ->where('balance', '>', 0)
                ->get(['id', 'balance', 'date_of_service']);

            $arTotal = (float) $openClaims->sum('balance');
            $ar90plus = (float) $openClaims->filter(function ($c) {
                if (!$c->date_of_service) return false;
                return abs(now()->diffInDays($c->date_of_service)) > 90;
            })->sum('balance');

            $daysInAr = $openClaims->count() > 0
                ? round($openClaims->avg(fn ($c) => $c->date_of_service ? abs(now()->diffInDays($c->date_of_service)) : 0))
                : 0;

            // Open denials
            $openDenials = ClaimDenial::whereHas('claim', function ($q) use ($agencyId, $client) {
                $q->where('agency_id', $agencyId)->where('billing_client_id', $client->id);
            })
                ->whereIn('status', ['open', 'in_review', 'appealed'])
                ->get(['id', 'denied_amount']);

            $denialCount = $openDenials->count();
            $denialAmount = (float) $openDenials->sum('denied_amount');

            // Health score components (each 0-100)
            $collectionPct = $billed > 0 ? min(100.0, ($collected / $billed) * 100) : ($submittedCount === 0 ? 100 : 0);
            $arHealthScore = $daysInAr > 0 ? max(0.0, 100.0 - ($daysInAr / 60) * 100) : 100.0;  // 60d = 0, 0d = 100
            $denialRate = $submittedCount > 0 ? ($denialCount / max(1, $submittedCount)) * 100 : 0;
            $denialHealthScore = max(0.0, 100.0 - ($denialRate / 0.10));  // 10% = 0, 0% = 100
            $ar90pct = $arTotal > 0 ? ($ar90plus / $arTotal) * 100 : 0;
            $ar90HealthScore = max(0.0, 100.0 - ($ar90pct / 0.30));  // 30% = 0, 0% = 100

            $health = round(
                0.40 * $collectionPct
                + 0.20 * $arHealthScore
                + 0.20 * $denialHealthScore
                + 0.20 * $ar90HealthScore
            );

            // Action chip — most-urgent finding wins
            $action = self::deriveAction($health, $ar90pct, $denialCount, $denialAmount, $daysInAr, $submittedCount);

            // Last activity = most-recent claim updated_at on this practice
            $lastActivity = (clone $claimsQuery)
                ->orderByDesc('updated_at')
                ->value('updated_at');

            $rows[] = [
                'id' => $client->id,
                'name' => $client->display_name ?: $client->organization_name,
                'status' => $client->status,
                'health_score' => $health,
                'billed' => round($billed, 2),
                'collected' => round($collected, 2),
                'collection_pct' => round($collectionPct, 1),
                'ar_total' => round($arTotal, 2),
                'ar_90plus' => round($ar90plus, 2),
                'ar_90plus_pct' => round($ar90pct, 1),
                'days_in_ar' => $daysInAr,
                'open_denials' => $denialCount,
                'open_denials_amount' => round($denialAmount, 2),
                'clean_claim_rate' => $cleanRate,
                'submitted_count' => $submittedCount,
                'last_activity_at' => $lastActivity,
                'action' => $action,
            ];

            $portfolio['billed'] += $billed;
            $portfolio['collected'] += $collected;
            $portfolio['ar_total'] += $arTotal;
            $portfolio['ar_90plus'] += $ar90plus;
            $portfolio['denials_open'] += $denialCount;
            $portfolio['denials_open_amount'] += $denialAmount;
        }

        // Portfolio rollup
        $portfolio['collection_pct'] = $portfolio['billed'] > 0
            ? round(($portfolio['collected'] / $portfolio['billed']) * 100, 1)
            : 0;
        $portfolio['practices_total'] = count($rows);
        $portfolio['practices_at_risk'] = collect($rows)->filter(fn ($r) => $r['health_score'] < 60)->count();
        $portfolio['avg_days_in_ar'] = collect($rows)->avg('days_in_ar') ?: 0;

        return response()->json([
            'success' => true,
            'data' => [
                'window' => $window,
                'as_of' => now()->toIso8601String(),
                'portfolio' => array_map(fn ($v) => is_float($v) ? round($v, 2) : $v, $portfolio),
                'practices' => $rows,
            ],
        ]);
    }

    /** Returns one of: 'healthy', 'review_denials', 'chase_ar', 'aging_ar',
     *  'low_volume', 'stalled', 'critical'. Caller decides chip colors. */
    private static function deriveAction(int $health, float $ar90pct, int $denialCount, float $denialAmount, int $daysInAr, int $submittedCount): string
    {
        if ($submittedCount === 0) {
            return 'stalled';  // no claim activity in window
        }
        if ($health < 40) {
            return 'critical';
        }
        if ($denialCount >= 5 || $denialAmount > 2000) {
            return 'review_denials';
        }
        if ($ar90pct >= 25) {
            return 'aging_ar';
        }
        if ($daysInAr >= 45) {
            return 'chase_ar';
        }
        if ($submittedCount < 5) {
            return 'low_volume';
        }
        return 'healthy';
    }

    // ── Billing Tasks ──

    public function tasks(Request $request): JsonResponse
    {
        $query = BillingTask::where('agency_id', $request->user()->effectiveAgencyId($request))
            ->with(['billingClient:id,organization_name']);

        if ($clientId = $request->input('billing_client_id')) {
            $query->where('billing_client_id', $clientId);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $tasks = $query->orderByRaw("CASE WHEN status IN ('pending','in_progress') THEN 0 ELSE 1 END")
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderBy('due_date')
            ->get();

        return response()->json(['success' => true, 'data' => $tasks]);
    }

    public function storeTask(Request $request): JsonResponse
    {
        $request->validate([
            'billing_client_id' => 'required|exists:billing_clients,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'provider_name' => 'nullable|string|max:200',
            'category' => 'nullable|in:charge_entry,claim_submission,claim_followup,denial_management,payment_posting,eligibility_verification,patient_billing,reporting,other',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'status' => 'nullable|in:pending,in_progress,completed,on_hold,cancelled',
            'due_date' => 'nullable|date',
        ]);

        $task = BillingTask::create([
            'agency_id' => $request->user()->effectiveAgencyId($request),
            'created_by' => $request->user()->id,
            ...$request->only([
                'billing_client_id', 'title', 'description',
                'provider_name', 'category', 'priority', 'status', 'due_date',
            ]),
        ]);

        return response()->json(['success' => true, 'data' => $task->load('billingClient:id,organization_name')], 201);
    }

    public function updateTask(Request $request, int $id): JsonResponse
    {
        $task = BillingTask::where('agency_id', $request->user()->effectiveAgencyId($request))->findOrFail($id);

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'provider_name' => 'nullable|string|max:200',
            'category' => 'nullable|in:charge_entry,claim_submission,claim_followup,denial_management,payment_posting,eligibility_verification,patient_billing,reporting,other',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'status' => 'nullable|in:pending,in_progress,completed,on_hold,cancelled',
            'due_date' => 'nullable|date',
        ]);

        $data = $request->only([
            'title', 'description', 'provider_name',
            'category', 'priority', 'status', 'due_date',
        ]);

        // Auto-set completed_at when marking completed
        if (($data['status'] ?? null) === 'completed' && !$task->completed_at) {
            $data['completed_at'] = now();
        }

        $task->update($data);
        return response()->json(['success' => true, 'data' => $task]);
    }

    public function destroyTask(Request $request, int $id): JsonResponse
    {
        $task = BillingTask::where('agency_id', $request->user()->effectiveAgencyId($request))->findOrFail($id);
        $task->delete();
        return response()->json(['success' => true]);
    }

    // ── Billing Activities ──

    public function activities(Request $request): JsonResponse
    {
        $query = BillingActivity::where('agency_id', $request->user()->effectiveAgencyId($request))
            ->with(['billingClient:id,organization_name', 'creator:id,first_name,last_name']);

        if ($clientId = $request->input('billing_client_id')) {
            $query->where('billing_client_id', $clientId);
        }
        if ($type = $request->input('activity_type')) {
            $query->where('activity_type', $type);
        }

        $limit = min((int) ($request->input('limit') ?: 50), 200);
        $activities = $query->orderByDesc('activity_date')->orderByDesc('created_at')->limit($limit)->get();

        // Add user_name for convenience
        $activities->each(function ($a) {
            $a->user_name = $a->creator
                ? trim(($a->creator->first_name ?? '') . ' ' . ($a->creator->last_name ?? ''))
                : null;
        });

        return response()->json(['success' => true, 'data' => $activities]);
    }

    public function storeActivity(Request $request): JsonResponse
    {
        $request->validate([
            'billing_client_id' => 'required|exists:billing_clients,id',
            'activity_type' => 'required|in:claim_submitted,claim_followup,denial_worked,payment_posted,eligibility_check,report_generated,note',
            'provider_name' => 'nullable|string|max:200',
            'payer_name' => 'nullable|string|max:200',
            'activity_date' => 'required|date',
            'amount' => 'nullable|numeric|min:0',
            'quantity' => 'nullable|integer|min:1',
            'reference' => 'nullable|string|max:200',
            'notes' => 'required|string',
        ]);

        $activity = BillingActivity::create([
            'agency_id' => $request->user()->effectiveAgencyId($request),
            'created_by' => $request->user()->id,
            ...$request->only([
                'billing_client_id', 'activity_type',
                'provider_name', 'payer_name', 'activity_date',
                'amount', 'quantity', 'reference', 'notes',
            ]),
        ]);

        return response()->json(['success' => true, 'data' => $activity], 201);
    }

    public function updateActivity(Request $request, int $id): JsonResponse
    {
        $activity = BillingActivity::where('agency_id', $request->user()->effectiveAgencyId($request))->findOrFail($id);
        $activity->update($request->only([
            'activity_type', 'provider_name', 'payer_name',
            'activity_date', 'amount', 'quantity', 'reference', 'notes',
        ]));
        return response()->json(['success' => true, 'data' => $activity]);
    }

    public function destroyActivity(Request $request, int $id): JsonResponse
    {
        $activity = BillingActivity::where('agency_id', $request->user()->effectiveAgencyId($request))->findOrFail($id);
        $activity->delete();
        return response()->json(['success' => true]);
    }

    // ── Billing Financials ──

    public function financials(Request $request): JsonResponse
    {
        $query = BillingFinancial::where('agency_id', $request->user()->effectiveAgencyId($request))
            ->with(['billingClient:id,organization_name']);

        if ($clientId = $request->input('billing_client_id')) {
            $query->where('billing_client_id', $clientId);
        }

        $financials = $query->orderByDesc('period')->get();
        return response()->json(['success' => true, 'data' => $financials]);
    }

    public function storeFinancial(Request $request): JsonResponse
    {
        $request->validate([
            'billing_client_id' => 'required|exists:billing_clients,id',
            'period' => 'required|string|size:7', // YYYY-MM
            'claims_submitted' => 'nullable|integer|min:0',
            'amount_billed' => 'nullable|numeric|min:0',
            'amount_collected' => 'nullable|numeric|min:0',
            'denial_count' => 'nullable|integer|min:0',
            'denied_amount' => 'nullable|numeric|min:0',
            'adjustments' => 'nullable|numeric',
            'patient_responsibility' => 'nullable|numeric|min:0',
        ]);

        $financial = BillingFinancial::updateOrCreate(
            [
                'agency_id' => $request->user()->effectiveAgencyId($request),
                'billing_client_id' => $request->input('billing_client_id'),
                'period' => $request->input('period'),
            ],
            [
                'created_by' => $request->user()->id,
                ...$request->only([
                    'claims_submitted', 'amount_billed', 'amount_collected',
                    'denial_count', 'denied_amount', 'adjustments', 'patient_responsibility',
                ]),
            ]
        );

        return response()->json(['success' => true, 'data' => $financial], 201);
    }

    public function updateFinancial(Request $request, int $id): JsonResponse
    {
        $financial = BillingFinancial::where('agency_id', $request->user()->effectiveAgencyId($request))->findOrFail($id);
        $financial->update($request->only([
            'claims_submitted', 'amount_billed', 'amount_collected',
            'denial_count', 'denied_amount', 'adjustments', 'patient_responsibility',
        ]));
        return response()->json(['success' => true, 'data' => $financial]);
    }

    // ── Client Payment Ledger ──

    /**
     * Generate or refresh the payment ledger for a client.
     * Calculates collections per month from claims, applies agency fee.
     */
    public function generateLedger(Request $request, int $clientId): JsonResponse
    {
        $agencyId = $request->user()->effectiveAgencyId($request);
        $client = BillingClient::where('agency_id', $agencyId)->findOrFail($clientId);
        $feePercent = (float) ($client->agency_fee_percent ?? 0);
        $isAgencyManaged = ($client->payment_mode ?? 'self_managed') === 'agency_managed';

        // Get all paid claims for this client grouped by month
        $claims = Claim::where('agency_id', $agencyId)
            ->where('billing_client_id', $clientId)
            ->where('total_paid', '>', 0)
            ->get();

        $monthly = [];
        foreach ($claims as $c) {
            $period = substr($c->date_of_service, 0, 7);
            if (!isset($monthly[$period])) $monthly[$period] = 0;
            $monthly[$period] += (float) $c->total_paid;
        }

        $created = 0;
        $updated = 0;
        foreach ($monthly as $period => $collected) {
            $fee = $isAgencyManaged ? round($collected * $feePercent / 100, 2) : 0;
            $existing = ClientPaymentLedger::where('agency_id', $agencyId)
                ->where('billing_client_id', $clientId)
                ->where('period', $period)
                ->first();

            $data = [
                'total_collected' => round($collected, 2),
                'agency_fee' => $fee,
                'outstanding' => round($collected - $fee - ($existing->amount_remitted ?? 0), 2),
            ];

            if ($existing) {
                $existing->update($data);
                $updated++;
            } else {
                ClientPaymentLedger::create([
                    'agency_id' => $agencyId,
                    'billing_client_id' => $clientId,
                    'period' => $period,
                    'amount_remitted' => 0,
                    'status' => 'pending',
                    'created_by' => $request->user()->id,
                    ...$data,
                ]);
                $created++;
            }
        }

        return response()->json(['success' => true, 'created' => $created, 'updated' => $updated]);
    }

    /**
     * Get ledger entries for a client.
     */
    public function getLedger(Request $request, int $clientId): JsonResponse
    {
        $agencyId = $request->user()->effectiveAgencyId($request);
        $entries = ClientPaymentLedger::where('agency_id', $agencyId)
            ->where('billing_client_id', $clientId)
            ->orderByDesc('period')
            ->get();

        $totals = [
            'total_collected' => $entries->sum('total_collected'),
            'total_fee' => $entries->sum('agency_fee'),
            'total_remitted' => $entries->sum('amount_remitted'),
            'total_outstanding' => $entries->sum('outstanding'),
        ];

        return response()->json(['success' => true, 'data' => $entries, 'totals' => $totals]);
    }

    /**
     * Record a remittance (payment to the org).
     */
    public function recordRemittance(Request $request, int $ledgerId): JsonResponse
    {
        $request->validate([
            'amount_remitted' => 'required|numeric|min:0.01',
            'remittance_date' => 'required|date',
            'remittance_method' => 'nullable|in:check,ach,wire,zelle',
            'remittance_reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $entry = ClientPaymentLedger::where('agency_id', $request->user()->effectiveAgencyId($request))->findOrFail($ledgerId);
        $entry->update([
            'amount_remitted' => $request->amount_remitted,
            'outstanding' => round($entry->total_collected - $entry->agency_fee - $request->amount_remitted, 2),
            'remittance_date' => $request->remittance_date,
            'remittance_method' => $request->remittance_method,
            'remittance_reference' => $request->remittance_reference,
            'status' => $request->amount_remitted >= ($entry->total_collected - $entry->agency_fee) ? 'remitted' : 'partial',
            'notes' => $request->notes ?? $entry->notes,
        ]);

        return response()->json(['success' => true, 'data' => $entry]);
    }

    // ── Auto-Generate Tasks ──

    public function generateTasks(Request $request): JsonResponse
    {
      try {
        $aid = $request->user()->effectiveAgencyId($request);
        $uid = $request->user()->id;
        $now = now();
        $created = 0;
        $skipped = 0;

        // Default client for tasks where claim has no billing_client_id
        $defaultClientId = BillingClient::where('agency_id', $aid)->where('status', 'active')->value('id');

        // Helper: create task if source_key doesn't already exist (and not dismissed)
        $makeTask = function ($key, $data) use ($aid, $uid, $defaultClientId, &$created, &$skipped) {
            // Ensure billing_client_id is set
            if (empty($data['billing_client_id'])) $data['billing_client_id'] = $defaultClientId;
            $exists = BillingTask::where('agency_id', $aid)
                ->where('source_key', $key)
                ->where(function ($q) {
                    $q->where('dismissed', false)
                      ->orWhereIn('status', ['pending', 'in_progress']);
                })
                ->exists();
            if ($exists) { $skipped++; return; }
            // Also skip if a dismissed version exists and claim hasn't changed
            $dismissed = BillingTask::where('agency_id', $aid)->where('source_key', $key)->where('dismissed', true)->exists();
            if ($dismissed) { $skipped++; return; }

            BillingTask::create([
                'agency_id' => $aid,
                'created_by' => $uid,
                'source' => 'system',
                'source_key' => $key,
                ...$data,
            ]);
            $created++;
        };

        // Get all claims
        $claims = Claim::where('agency_id', $aid)->get();
        $denials = ClaimDenial::where('agency_id', $aid)->get();
        $payments = ClaimPayment::where('agency_id', $aid)->get();

        // ── 1. Payer Follow-Up: Claims pending 30/60/90+ days ──
        $pendingClaims = $claims->whereIn('status', ['submitted', 'pending', 'acknowledged']);
        foreach ($pendingClaims as $c) {
            $dos = $c->date_of_service;
            if (!$dos) continue;
            $days = $now->diffInDays($dos);
            $payer = $c->payer_name ?? 'Unknown Payer';
            $patient = $c->patient_name ?? 'Unknown';
            $amt = number_format((float) $c->total_charges, 2);
            $clientId = $c->billing_client_id;

            if ($days >= 90) {
                $makeTask("followup-90-{$c->id}", [
                    'billing_client_id' => $clientId,
                    'claim_id' => $c->id,
                    'title' => "URGENT: {$payer} claim #{$c->claim_number} is {$days}d old — timely filing risk",
                    'description' => "Patient: {$patient} | DOS: {$dos->format('m/d/Y')} | Charges: \${$amt}\nThis claim is over 90 days with no payment. Contact {$payer} immediately to avoid timely filing denial.",
                    'category' => 'claim_followup',
                    'priority' => 'urgent',
                    'status' => 'pending',
                    'due_date' => $now->copy()->addDays(2),
                ]);
            } elseif ($days >= 60) {
                $makeTask("followup-60-{$c->id}", [
                    'billing_client_id' => $clientId,
                    'claim_id' => $c->id,
                    'title' => "Follow up with {$payer} on claim #{$c->claim_number} — {$days}d pending",
                    'description' => "Patient: {$patient} | DOS: {$dos->format('m/d/Y')} | Charges: \${$amt}\nNo payment received after {$days} days. Call {$payer} for claim status.",
                    'category' => 'claim_followup',
                    'priority' => 'high',
                    'status' => 'pending',
                    'due_date' => $now->copy()->addDays(5),
                ]);
            } elseif ($days >= 30) {
                $makeTask("followup-30-{$c->id}", [
                    'billing_client_id' => $clientId,
                    'claim_id' => $c->id,
                    'title' => "Check status: {$payer} claim for {$patient} — {$days}d pending",
                    'description' => "Claim #{$c->claim_number} | DOS: {$dos->format('m/d/Y')} | Charges: \${$amt}\nPending {$days} days. May need payer follow-up or check if payment CSV is missing.",
                    'category' => 'claim_followup',
                    'priority' => 'normal',
                    'status' => 'pending',
                    'due_date' => $now->copy()->addDays(7),
                ]);
            }
        }

        // ── 2. Denial Management: Denied claims needing review/appeal ──
        $deniedClaims = $claims->where('status', 'denied');
        foreach ($deniedClaims as $c) {
            $denial = $denials->where('claim_id', $c->id)->first();
            $hasAppeal = $denial && in_array($denial->status, ['appeal_in_progress', 'pending_response', 'resolved_won', 'resolved_lost']);
            if ($hasAppeal) continue;

            $payer = $c->payer_name ?? 'Unknown';
            $patient = $c->patient_name ?? 'Unknown';
            $amt = number_format((float) $c->total_charges, 2);
            $reason = $c->denial_reason ?? ($denial->denial_reason ?? 'Not specified');

            $makeTask("denial-review-{$c->id}", [
                'billing_client_id' => $c->billing_client_id,
                'claim_id' => $c->id,
                'title' => "Review denial: {$patient} — {$payer} — \${$amt}",
                'description' => "Claim #{$c->claim_number} | Reason: {$reason}\nReview denial and determine if appeal is warranted.",
                'category' => 'denial_management',
                'priority' => 'high',
                'status' => 'pending',
                'due_date' => $now->copy()->addDays(5),
            ]);
        }

        // ── 3. Appeal Deadlines: Denials with approaching deadlines ──
        foreach ($denials as $d) {
            if (!$d->appeal_deadline || in_array($d->status, ['resolved_won', 'resolved_lost', 'written_off'])) continue;
            $daysUntil = $now->diffInDays($d->appeal_deadline, false);
            if ($daysUntil <= 7 && $daysUntil >= 0) {
                $claim = $claims->firstWhere('id', $d->claim_id);
                $makeTask("appeal-deadline-{$d->id}", [
                    'billing_client_id' => $d->billing_client_id,
                    'claim_id' => $d->claim_id,
                    'title' => "Appeal deadline in {$daysUntil}d: " . ($claim->patient_name ?? '') . " — \$" . number_format((float) ($d->denied_amount ?? 0), 2),
                    'description' => "Claim #" . ($claim->claim_number ?? '') . " | Deadline: " . ($d->appeal_deadline ? $d->appeal_deadline->format('m/d/Y') : '') . "\nFile appeal before deadline or amount will be lost.",
                    'category' => 'denial_management',
                    'priority' => 'urgent',
                    'status' => 'pending',
                    'due_date' => $d->appeal_deadline,
                ]);
            }
        }

        // ── 4. Patient Collections: Open patient balances 30+ days ──
        $ptBalanceClaims = $claims->filter(fn($c) => (float) $c->patient_responsibility > 0 && in_array($c->status, ['paid', 'partial_paid']));
        $byPatient = [];
        foreach ($ptBalanceClaims as $c) {
            $name = $c->patient_name ?? 'Unknown';
            if (!isset($byPatient[$name])) $byPatient[$name] = ['total' => 0, 'claims' => 0, 'client_id' => $c->billing_client_id];
            $byPatient[$name]['total'] += (float) $c->patient_responsibility;
            $byPatient[$name]['claims']++;
        }
        foreach ($byPatient as $name => $data) {
            if ($data['total'] < 1) continue;
            $amt = number_format($data['total'], 2);
            $makeTask("pt-balance-{$name}", [
                'billing_client_id' => $data['client_id'],
                'title' => "Send statement to {$name} — \${$amt} patient balance",
                'description' => "{$data['claims']} claim(s) with patient responsibility totaling \${$amt}.\nGenerate and send patient statement.",
                'category' => 'patient_billing',
                'priority' => $data['total'] > 100 ? 'high' : 'normal',
                'status' => 'pending',
                'due_date' => $now->copy()->addDays(7),
            ]);
        }

        // ── 5. Undeposited Checks: Payments received 7+ days ago without deposit date ──
        foreach ($payments as $p) {
            if ($p->deposit_date || !$p->payment_date) continue;
            $daysSince = $now->diffInDays($p->payment_date);
            if ($daysSince < 7) continue;
            $ck = $p->check_number ?? $p->trace_number ?? 'Unknown';
            $amt = number_format((float) $p->total_amount, 2);
            $makeTask("deposit-{$p->id}", [
                'billing_client_id' => $p->billing_client_id,
                'title' => "Deposit check #{$ck} from {$p->payer_name} — \${$amt}",
                'description' => "Payment received {$daysSince} days ago but not yet marked as deposited.\nConfirm deposit in the Payments tab.",
                'category' => 'payment_posting',
                'priority' => $daysSince > 14 ? 'high' : 'normal',
                'status' => 'pending',
                'due_date' => $now->copy()->addDays(2),
            ]);
        }

        return response()->json([
            'success' => true,
            'created' => $created,
            'skipped' => $skipped,
            'message' => $created > 0 ? "{$created} new tasks generated" : 'No new tasks — everything is up to date',
        ]);
      } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage(), 'line' => $e->getLine()], 500);
      }
    }

    /**
     * Dismiss a system-generated task (won't be recreated).
     */
    public function dismissTask(Request $request, int $id): JsonResponse
    {
        $task = BillingTask::where('agency_id', $request->user()->effectiveAgencyId($request))->findOrFail($id);
        $task->update(['dismissed' => true, 'status' => 'cancelled']);
        return response()->json(['success' => true]);
    }
}

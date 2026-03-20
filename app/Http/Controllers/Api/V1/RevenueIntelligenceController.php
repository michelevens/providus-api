<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Invoice;
use App\Models\Provider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RevenueIntelligenceController extends Controller
{
    /**
     * Dashboard overview — key revenue KPIs
     */
    public function dashboard(Request $request): JsonResponse
    {
        $agencyId = $request->user()->agency_id;

        // Revenue from approved/credentialed applications
        $approvedApps = Application::whereIn('status', ['approved', 'credentialed'])
            ->select(
                DB::raw('COUNT(*) as total_approved'),
                DB::raw('SUM(est_monthly_revenue) as monthly_revenue'),
                DB::raw('SUM(est_monthly_revenue) * 12 as annual_revenue'),
            )->first();

        // Pipeline revenue (submitted + in_review)
        $pipelineRevenue = Application::whereIn('status', ['submitted', 'in_review', 'pending_info'])
            ->sum('est_monthly_revenue');

        // Lost revenue (denied + withdrawn)
        $lostRevenue = Application::whereIn('status', ['denied', 'withdrawn'])
            ->sum('est_monthly_revenue');

        // Invoicing stats
        $invoiceStats = Invoice::where('type', 'invoice')
            ->select(
                DB::raw('SUM(total) as total_billed'),
                DB::raw('SUM(paid_amount) as total_collected'),
                DB::raw('SUM(balance_due) as total_outstanding'),
            )->first();

        // Time-to-credential (avg days from created_at to effective_date for approved apps)
        $avgCredDays = Application::whereIn('status', ['approved', 'credentialed'])
            ->whereNotNull('effective_date')
            ->select(DB::raw('AVG(EXTRACT(DAY FROM (effective_date - created_at::date))) as avg_days'))
            ->value('avg_days');

        // Collection rate
        $totalBilled = (float) ($invoiceStats->total_billed ?? 0);
        $totalCollected = (float) ($invoiceStats->total_collected ?? 0);
        $collectionRate = $totalBilled > 0 ? round(($totalCollected / $totalBilled) * 100, 1) : 0;

        // ROI: revenue generated vs credentialing costs (invoices)
        $annualRevenue = (float) ($approvedApps->annual_revenue ?? 0);
        $roi = $totalBilled > 0 ? round(($annualRevenue / $totalBilled) * 100, 0) : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'monthly_revenue' => round((float) ($approvedApps->monthly_revenue ?? 0), 2),
                'annual_revenue' => round($annualRevenue, 2),
                'pipeline_revenue' => round((float) $pipelineRevenue, 2),
                'lost_revenue' => round((float) $lostRevenue, 2),
                'total_approved' => (int) ($approvedApps->total_approved ?? 0),
                'avg_credential_days' => round((float) ($avgCredDays ?? 0)),
                'total_billed' => round($totalBilled, 2),
                'total_collected' => round($totalCollected, 2),
                'total_outstanding' => round((float) ($invoiceStats->total_outstanding ?? 0), 2),
                'collection_rate' => $collectionRate,
                'roi_percentage' => $roi,
            ],
        ]);
    }

    /**
     * Revenue by provider — which providers generate the most revenue
     */
    public function byProvider(Request $request): JsonResponse
    {
        $providers = Provider::select('providers.id', 'providers.first_name', 'providers.last_name', 'providers.credentials', 'providers.npi')
            ->selectRaw('COUNT(CASE WHEN applications.status IN (\'approved\', \'credentialed\') THEN 1 END) as approved_count')
            ->selectRaw('COUNT(applications.id) as total_apps')
            ->selectRaw('COALESCE(SUM(CASE WHEN applications.status IN (\'approved\', \'credentialed\') THEN applications.est_monthly_revenue END), 0) as monthly_revenue')
            ->selectRaw('AVG(CASE WHEN applications.status IN (\'approved\', \'credentialed\') AND applications.effective_date IS NOT NULL THEN EXTRACT(DAY FROM (applications.effective_date - applications.created_at::date)) END) as avg_days')
            ->leftJoin('applications', 'providers.id', '=', 'applications.provider_id')
            ->groupBy('providers.id', 'providers.first_name', 'providers.last_name', 'providers.credentials', 'providers.npi')
            ->orderByRaw('COALESCE(SUM(CASE WHEN applications.status IN (\'approved\', \'credentialed\') THEN applications.est_monthly_revenue END), 0) DESC')
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'name' => trim("{$p->first_name} {$p->last_name}"),
                    'credentials' => $p->credentials,
                    'npi' => $p->npi,
                    'approved_count' => (int) $p->approved_count,
                    'total_apps' => (int) $p->total_apps,
                    'monthly_revenue' => round((float) $p->monthly_revenue, 2),
                    'annual_revenue' => round((float) $p->monthly_revenue * 12, 2),
                    'avg_credential_days' => $p->avg_days ? round((float) $p->avg_days) : null,
                    'approval_rate' => $p->total_apps > 0 ? round(($p->approved_count / $p->total_apps) * 100, 1) : 0,
                ];
            });

        return response()->json(['success' => true, 'data' => $providers]);
    }

    /**
     * Revenue by payer — which payers are most profitable
     */
    public function byPayer(Request $request): JsonResponse
    {
        $payers = Application::select('payer_id')
            ->selectRaw('MAX(payer_name) as payer_name')
            ->selectRaw('COUNT(*) as total_apps')
            ->selectRaw('COUNT(CASE WHEN status IN (\'approved\', \'credentialed\') THEN 1 END) as approved_count')
            ->selectRaw('COUNT(CASE WHEN status = \'denied\' THEN 1 END) as denied_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN status IN (\'approved\', \'credentialed\') THEN est_monthly_revenue END), 0) as monthly_revenue')
            ->selectRaw('AVG(CASE WHEN status IN (\'approved\', \'credentialed\') AND effective_date IS NOT NULL THEN EXTRACT(DAY FROM (effective_date - created_at::date)) END) as avg_days')
            ->groupBy('payer_id')
            ->orderByRaw('COALESCE(SUM(CASE WHEN status IN (\'approved\', \'credentialed\') THEN est_monthly_revenue END), 0) DESC')
            ->get()
            ->map(function ($p) {
                $approvalRate = $p->total_apps > 0 ? round(($p->approved_count / $p->total_apps) * 100, 1) : 0;
                return [
                    'payer_id' => $p->payer_id,
                    'payer_name' => $p->payer_name,
                    'total_apps' => (int) $p->total_apps,
                    'approved_count' => (int) $p->approved_count,
                    'denied_count' => (int) $p->denied_count,
                    'approval_rate' => $approvalRate,
                    'monthly_revenue' => round((float) $p->monthly_revenue, 2),
                    'annual_revenue' => round((float) $p->monthly_revenue * 12, 2),
                    'avg_credential_days' => $p->avg_days ? round((float) $p->avg_days) : null,
                ];
            });

        return response()->json(['success' => true, 'data' => $payers]);
    }

    /**
     * Revenue by state — geographic revenue distribution
     */
    public function byState(Request $request): JsonResponse
    {
        $states = Application::select('state')
            ->selectRaw('COUNT(*) as total_apps')
            ->selectRaw('COUNT(CASE WHEN status IN (\'approved\', \'credentialed\') THEN 1 END) as approved_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN status IN (\'approved\', \'credentialed\') THEN est_monthly_revenue END), 0) as monthly_revenue')
            ->selectRaw('AVG(CASE WHEN status IN (\'approved\', \'credentialed\') AND effective_date IS NOT NULL THEN EXTRACT(DAY FROM (effective_date - created_at::date)) END) as avg_days')
            ->whereNotNull('state')
            ->groupBy('state')
            ->orderByRaw('COALESCE(SUM(CASE WHEN status IN (\'approved\', \'credentialed\') THEN est_monthly_revenue END), 0) DESC')
            ->get()
            ->map(fn($s) => [
                'state' => $s->state,
                'total_apps' => (int) $s->total_apps,
                'approved_count' => (int) $s->approved_count,
                'monthly_revenue' => round((float) $s->monthly_revenue, 2),
                'annual_revenue' => round((float) $s->monthly_revenue * 12, 2),
                'avg_credential_days' => $s->avg_days ? round((float) $s->avg_days) : null,
            ]);

        return response()->json(['success' => true, 'data' => $states]);
    }

    /**
     * Time-to-credential analysis — breakdown by payer and trend over time
     */
    public function timeToCredential(Request $request): JsonResponse
    {
        // By payer — which payers are fastest/slowest
        $byPayer = Application::whereIn('status', ['approved', 'credentialed'])
            ->whereNotNull('effective_date')
            ->select('payer_id')
            ->selectRaw('MAX(payer_name) as payer_name')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('AVG(EXTRACT(DAY FROM (effective_date - created_at::date))) as avg_days')
            ->selectRaw('MIN(EXTRACT(DAY FROM (effective_date - created_at::date))) as min_days')
            ->selectRaw('MAX(EXTRACT(DAY FROM (effective_date - created_at::date))) as max_days')
            ->groupBy('payer_id')
            ->orderByRaw('AVG(EXTRACT(DAY FROM (effective_date - created_at::date))) ASC')
            ->get()
            ->map(fn($p) => [
                'payer_id' => $p->payer_id,
                'payer_name' => $p->payer_name,
                'count' => (int) $p->count,
                'avg_days' => round((float) $p->avg_days),
                'min_days' => (int) $p->min_days,
                'max_days' => (int) $p->max_days,
            ]);

        // Monthly trend — are we getting faster?
        $trend = Application::whereIn('status', ['approved', 'credentialed'])
            ->whereNotNull('effective_date')
            ->selectRaw("TO_CHAR(effective_date, 'YYYY-MM') as month")
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('AVG(EXTRACT(DAY FROM (effective_date - created_at::date))) as avg_days')
            ->groupByRaw("TO_CHAR(effective_date, 'YYYY-MM')")
            ->orderByRaw("TO_CHAR(effective_date, 'YYYY-MM') DESC")
            ->limit(12)
            ->get()
            ->map(fn($t) => [
                'month' => $t->month,
                'count' => (int) $t->count,
                'avg_days' => round((float) $t->avg_days),
            ]);

        // Revenue lost from delays (apps taking >90 days to credential)
        $delayedApps = Application::whereIn('status', ['approved', 'credentialed'])
            ->whereNotNull('effective_date')
            ->whereRaw('EXTRACT(DAY FROM (effective_date - created_at::date)) > 90')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('SUM(est_monthly_revenue) as monthly_revenue')
            ->selectRaw('AVG(EXTRACT(DAY FROM (effective_date - created_at::date))) as avg_days')
            ->first();

        $delayedMonths = max(0, round(((float) ($delayedApps->avg_days ?? 0) - 90) / 30, 1));
        $revenueLostFromDelays = round((float) ($delayedApps->monthly_revenue ?? 0) * $delayedMonths, 2);

        return response()->json([
            'success' => true,
            'data' => [
                'by_payer' => $byPayer,
                'monthly_trend' => $trend,
                'delayed_apps' => [
                    'count' => (int) ($delayedApps->count ?? 0),
                    'avg_days' => round((float) ($delayedApps->avg_days ?? 0)),
                    'revenue_lost_from_delays' => $revenueLostFromDelays,
                ],
            ],
        ]);
    }
}

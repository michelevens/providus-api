<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FundingApplication;
use App\Models\FundingOpportunity;
use App\Services\FundingScraperService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FundingController extends Controller
{
    /**
     * List funding opportunities with filters.
     */
    public function opportunities(Request $request): JsonResponse
    {
        $query = FundingOpportunity::query();

        // Source filter
        if ($source = $request->input('source')) {
            $query->bySource($source);
        }

        // Status filter (default: open)
        $status = $request->input('status', 'open');
        if ($status === 'open') {
            $query->open();
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        // Category filter
        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                  ->orWhere('description', 'ilike', "%{$search}%")
                  ->orWhere('agency_source', 'ilike', "%{$search}%");
            });
        }

        // Sort
        $sort = $request->input('sort', 'close_date');
        $order = $request->input('order', 'asc');
        $query->orderByRaw("CASE WHEN {$sort} IS NULL THEN 1 ELSE 0 END")->orderBy($sort, $order);

        $opportunities = $query->paginate($request->input('per_page', 50));

        return response()->json([
            'success' => true,
            'data' => $opportunities->items(),
            'meta' => [
                'total' => $opportunities->total(),
                'page' => $opportunities->currentPage(),
                'per_page' => $opportunities->perPage(),
                'last_page' => $opportunities->lastPage(),
            ],
        ]);
    }

    /**
     * Get a single opportunity with full details.
     */
    public function show(FundingOpportunity $fundingOpportunity): JsonResponse
    {
        // Find related opportunities (same agency or category)
        $related = FundingOpportunity::open()
            ->where('id', '!=', $fundingOpportunity->id)
            ->where(function ($q) use ($fundingOpportunity) {
                $q->where('agency_source', $fundingOpportunity->agency_source)
                  ->orWhere('category', $fundingOpportunity->category);
            })
            ->limit(5)
            ->get(['id', 'title', 'source', 'agency_source', 'amount_display', 'close_date']);

        // Find past awards for this program from USASpending data
        $pastAwards = [];
        if ($fundingOpportunity->cfda_number) {
            $pastAwards = FundingOpportunity::where('source', 'usaspending')
                ->where('cfda_number', 'like', "%{$fundingOpportunity->cfda_number}%")
                ->limit(10)
                ->get(['title', 'agency_source', 'amount_display', 'amount_max', 'open_date']);
        }

        return response()->json([
            'success' => true,
            'data' => $fundingOpportunity,
            'related' => $related,
            'past_awards' => $pastAwards,
        ]);
    }

    /**
     * Get summary stats for the dashboard.
     */
    public function summary(Request $request): JsonResponse
    {
        $agencyId = $request->user()->agency_id;

        $openCount = FundingOpportunity::open()->count();
        $urgentCount = FundingOpportunity::open()
            ->whereNotNull('close_date')
            ->where('close_date', '<=', now()->addDays(30))
            ->count();

        $applications = FundingApplication::where('agency_id', $agencyId);
        $pipeline = [
            'identified' => (clone $applications)->where('stage', 'identified')->count(),
            'preparing' => (clone $applications)->where('stage', 'preparing')->count(),
            'submitted' => (clone $applications)->where('stage', 'submitted')->count(),
            'under_review' => (clone $applications)->where('stage', 'under_review')->count(),
            'awarded' => (clone $applications)->where('stage', 'awarded')->count(),
            'denied' => (clone $applications)->where('stage', 'denied')->count(),
        ];
        $totalAwarded = (clone $applications)->where('stage', 'awarded')->sum('amount_awarded');

        $bySource = FundingOpportunity::open()
            ->selectRaw("source, count(*) as count")
            ->groupBy('source')
            ->pluck('count', 'source');

        $deadlines = FundingOpportunity::open()
            ->whereNotNull('close_date')
            ->where('close_date', '>=', now())
            ->orderBy('close_date')
            ->limit(10)
            ->get(['id', 'title', 'source', 'agency_source', 'close_date', 'amount_display']);

        return response()->json([
            'success' => true,
            'data' => [
                'open_opportunities' => $openCount,
                'urgent_deadlines' => $urgentCount,
                'pipeline' => $pipeline,
                'total_awarded' => $totalAwarded,
                'by_source' => $bySource,
                'upcoming_deadlines' => $deadlines,
                'last_scraped' => FundingOpportunity::max('scraped_at'),
            ],
        ]);
    }

    /**
     * Get spending trends / intelligence data.
     */
    public function intelligence(Request $request): JsonResponse
    {
        $byAgency = FundingOpportunity::query()
            ->whereNotNull('agency_source')
            ->where('status', 'awarded')
            ->selectRaw("agency_source, count(*) as count, sum(amount_max) as total_amount")
            ->groupBy('agency_source')
            ->orderByDesc('total_amount')
            ->limit(10)
            ->get();

        $byCategory = FundingOpportunity::open()
            ->selectRaw("category, count(*) as count")
            ->groupBy('category')
            ->orderByDesc('count')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'top_funders' => $byAgency,
                'by_category' => $byCategory,
            ],
        ]);
    }

    /**
     * Trigger a manual scrape (agency+ role).
     */
    public function scrape(Request $request, FundingScraperService $scraper): JsonResponse
    {
        $source = $request->input('source');

        if ($source) {
            $result = match ($source) {
                'grants_gov' => $scraper->scrapeGrantsGov(),
                'sam_gov' => $scraper->scrapeSamGov(),
                'nih' => $scraper->scrapeNihReporter(),
                'usaspending' => $scraper->scrapeUsaSpending(),
                'samhsa' => $scraper->scrapeSamhsa(),
                'propublica', 'foundation' => $scraper->scrapeProPublica990s(),
                default => ['error' => 'Unknown source'],
            };
            $results = [$result];
        } else {
            $results = $scraper->scrapeAll();
        }

        return response()->json([
            'success' => true,
            'message' => 'Scrape completed',
            'results' => $results,
        ]);
    }

    // ─── Application Pipeline ────────────────────────────────

    /**
     * List agency's funding applications.
     */
    public function applications(Request $request): JsonResponse
    {
        $apps = FundingApplication::where('agency_id', $request->user()->agency_id)
            ->with(['opportunity:id,title,source,agency_source,close_date,amount_display', 'assignee:id,first_name,last_name'])
            ->orderByRaw("CASE stage
                WHEN 'identified' THEN 1
                WHEN 'preparing' THEN 2
                WHEN 'submitted' THEN 3
                WHEN 'under_review' THEN 4
                WHEN 'awarded' THEN 5
                WHEN 'denied' THEN 6
                END")
            ->orderBy('deadline')
            ->get();

        return response()->json(['success' => true, 'data' => $apps]);
    }

    /**
     * Create a funding application.
     */
    public function storeApplication(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'funding_opportunity_id' => 'nullable|exists:funding_opportunities,id',
            'title' => 'required|string|max:255',
            'stage' => 'nullable|string|in:identified,preparing,submitted,under_review,awarded,denied',
            'amount_requested' => 'nullable|numeric|min:0',
            'deadline' => 'nullable|date',
            'notes' => 'nullable|string',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $validated['agency_id'] = $request->user()->agency_id;
        $validated['stage'] = $validated['stage'] ?? 'identified';

        $app = FundingApplication::create($validated);

        return response()->json(['success' => true, 'data' => $app->load('opportunity')], 201);
    }

    /**
     * Update a funding application.
     */
    public function updateApplication(Request $request, FundingApplication $fundingApplication): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'stage' => 'sometimes|string|in:identified,preparing,submitted,under_review,awarded,denied',
            'amount_requested' => 'nullable|numeric|min:0',
            'amount_awarded' => 'nullable|numeric|min:0',
            'deadline' => 'nullable|date',
            'submitted_at' => 'nullable|date',
            'notes' => 'nullable|string',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $fundingApplication->update($validated);

        return response()->json(['success' => true, 'data' => $fundingApplication->fresh()->load('opportunity')]);
    }

    /**
     * Delete a funding application.
     */
    public function destroyApplication(FundingApplication $fundingApplication): JsonResponse
    {
        $fundingApplication->delete();
        return response()->json(['success' => true, 'message' => 'Deleted']);
    }
}

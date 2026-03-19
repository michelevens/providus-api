<?php

namespace App\Services;

use App\Models\FundingOpportunity;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FundingScraperService
{
    /**
     * Mental health / behavioral health keywords for filtering.
     */
    private const MH_KEYWORDS = [
        'mental health', 'behavioral health', 'substance abuse', 'substance use',
        'psychiatr', 'psycholog', 'opioid', 'suicide', 'crisis intervention',
        'SAMHSA', 'community mental', 'behavioral', 'SUD', 'SMI', 'SED',
        'counseling', 'therapy', 'addiction', 'recovery', 'CCBHC',
        'telehealth mental', 'veteran mental', 'youth mental',
        'trauma', 'PTSD', 'anxiety', 'depression',
    ];

    /**
     * Run all scrapers and return summary.
     */
    public function scrapeAll(): array
    {
        $results = [];
        $results['grants_gov'] = $this->scrapeGrantsGov();
        $results['sam_gov'] = $this->scrapeSamGov();
        $results['nih'] = $this->scrapeNihReporter();
        $results['usaspending'] = $this->scrapeUsaSpending();
        $results['samhsa'] = $this->scrapeSamhsa();
        $results['propublica'] = $this->scrapeProPublica990s();
        return $results;
    }

    /**
     * Grants.gov — the primary federal grants database.
     * API docs: https://www.grants.gov/web/grants/xml-extract.html
     * REST endpoint: https://apply07.grants.gov/grantsws/rest/opportunities/search/
     */
    public function scrapeGrantsGov(): array
    {
        $apiKey = config('services.funding.grants_gov_api_key');
        $imported = 0;

        // Search for each MH keyword group
        $searchTerms = [
            'mental health', 'behavioral health', 'substance abuse',
            'SAMHSA', 'psychiatry', 'opioid', 'suicide prevention',
            'community mental health', 'CCBHC',
        ];

        foreach ($searchTerms as $keyword) {
            try {
                $headers = ['Content-Type' => 'application/json'];
                if ($apiKey) {
                    $headers['Authorization'] = "Bearer {$apiKey}";
                }

                $response = Http::withHeaders($headers)
                    ->timeout(30)
                    ->post('https://apply07.grants.gov/grantsws/rest/opportunities/search/', [
                        'keyword' => $keyword,
                        'oppStatuses' => 'forecasted|posted',
                        'sortBy' => 'closeDateAsc',
                        'rows' => 50,
                    ]);

                if (!$response->successful()) {
                    Log::warning("Grants.gov search failed for '{$keyword}'", ['status' => $response->status()]);
                    continue;
                }

                $data = $response->json();
                $opportunities = $data['oppHits'] ?? [];

                foreach ($opportunities as $opp) {
                    // Skip isRelevant check — keyword search already filters
                    $imported += $this->upsertOpportunity([
                        'source' => 'grants_gov',
                        'external_id' => (string) ($opp['id'] ?? $opp['oppNumber'] ?? Str::random(12)),
                        'title' => $opp['title'] ?? 'Untitled',
                        'description' => Str::limit($opp['synopsis'] ?? "Federal grant opportunity for: {$keyword}", 1000),
                        'agency_source' => $opp['agency'] ?? $opp['agencyCode'] ?? null,
                        'cfda_number' => $opp['cfdaList'] ?? $opp['cfdaNumber'] ?? null,
                        'funding_type' => $this->mapFundingType($opp['oppCategory'] ?? ''),
                        'amount_display' => $this->formatAmount($opp['awardCeiling'] ?? null, $opp['awardFloor'] ?? null),
                        'amount_min' => $opp['awardFloor'] ?? null,
                        'amount_max' => $opp['awardCeiling'] ?? null,
                        'open_date' => $this->parseDate($opp['openDate'] ?? null),
                        'close_date' => $this->parseDate($opp['closeDate'] ?? null),
                        'status' => 'open',
                        'url' => "https://www.grants.gov/search-results-detail/{$opp['id']}",
                        'category' => $this->categorize($opp['title'] ?? '', $opp['synopsis'] ?? ''),
                        'keywords' => $this->extractKeywords($opp['title'] ?? '', $opp['synopsis'] ?? ''),
                        'raw_data' => $opp,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error("Grants.gov scrape error for '{$keyword}'", ['error' => $e->getMessage()]);
            }
        }

        return ['source' => 'grants_gov', 'imported' => $imported];
    }

    /**
     * SAM.gov — federal contract opportunities.
     * API: https://api.sam.gov/opportunities/v2/search
     * Free API key from api.data.gov
     */
    public function scrapeSamGov(): array
    {
        $apiKey = config('services.funding.sam_gov_api_key');
        if (!$apiKey) {
            Log::info('SAM.gov API key not configured, skipping');
            return ['source' => 'sam_gov', 'imported' => 0, 'skipped' => 'no_api_key'];
        }

        $imported = 0;
        $searchTerms = ['mental health', 'behavioral health', 'substance abuse', 'psychiatry'];

        foreach ($searchTerms as $keyword) {
            try {
                $response = Http::timeout(15)->get('https://api.sam.gov/opportunities/v2/search', [
                    'api_key' => $apiKey,
                    'q' => $keyword,
                    'postedFrom' => now()->subMonths(3)->format('m/d/Y'),
                    'postedTo' => now()->format('m/d/Y'),
                    'limit' => 50,
                    'offset' => 0,
                ]);

                if (!$response->successful()) {
                    Log::warning("SAM.gov search failed for '{$keyword}'", ['status' => $response->status()]);
                    continue;
                }

                $data = $response->json();
                $opportunities = $data['opportunitiesData'] ?? [];

                foreach ($opportunities as $opp) {
                    if (!$this->isRelevant($opp['title'] ?? '', $opp['description'] ?? '')) continue;

                    $imported += $this->upsertOpportunity([
                        'source' => 'sam_gov',
                        'external_id' => $opp['noticeId'] ?? Str::random(12),
                        'title' => $opp['title'] ?? 'Untitled',
                        'description' => Str::limit($opp['description'] ?? '', 1000),
                        'agency_source' => $opp['fullParentPathName'] ?? $opp['department'] ?? null,
                        'funding_type' => 'contract',
                        'close_date' => $this->parseDate($opp['responseDeadLine'] ?? null),
                        'status' => 'open',
                        'url' => "https://sam.gov/opp/{$opp['noticeId']}/view",
                        'category' => $this->categorize($opp['title'] ?? '', $opp['description'] ?? ''),
                        'keywords' => $this->extractKeywords($opp['title'] ?? '', $opp['description'] ?? ''),
                        'raw_data' => $opp,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error("SAM.gov scrape error for '{$keyword}'", ['error' => $e->getMessage()]);
            }
        }

        return ['source' => 'sam_gov', 'imported' => $imported];
    }

    /**
     * NIH RePORTER — research grants.
     * API: https://api.reporter.nih.gov/v2/projects/search
     * No API key needed.
     */
    public function scrapeNihReporter(): array
    {
        $imported = 0;

        try {
            $response = Http::timeout(30)->post('https://api.reporter.nih.gov/v2/projects/search', [
                'criteria' => [
                    'advanced_text_search' => [
                        'operator' => 'or',
                        'search_field' => 'terms',
                        'search_text' => 'mental health behavioral health psychiatry substance use disorder',
                    ],
                    'is_active' => true,
                    'agencies' => ['NIMH', 'NIDA', 'NIAAA', 'SAMHSA'],
                    'fiscal_years' => [2026, 2025],
                ],
                'offset' => 0,
                'limit' => 50,
                'sort_field' => 'project_start_date',
                'sort_order' => 'desc',
            ]);

            if (!$response->successful()) {
                Log::warning('NIH RePORTER search failed', ['status' => $response->status()]);
                return ['source' => 'nih', 'imported' => 0];
            }

            $data = $response->json();
            $projects = $data['results'] ?? [];

            foreach ($projects as $project) {
                $imported += $this->upsertOpportunity([
                    'source' => 'nih',
                    'external_id' => $project['project_num'] ?? $project['appl_id'] ?? Str::random(12),
                    'title' => $project['project_title'] ?? 'Untitled',
                    'description' => Str::limit($project['abstract_text'] ?? $project['phr_text'] ?? '', 1000),
                    'agency_source' => $project['agency_ic_fundings'][0]['name'] ?? 'NIH',
                    'cfda_number' => $project['cfda_code'] ?? null,
                    'funding_type' => 'grant',
                    'amount_min' => $project['award_amount'] ?? null,
                    'amount_max' => $project['award_amount'] ?? null,
                    'amount_display' => $project['award_amount'] ? '$' . number_format($project['award_amount']) : null,
                    'open_date' => $this->parseDate($project['project_start_date'] ?? null),
                    'close_date' => null, // project_end_date is project duration, not application deadline
                    'status' => 'open',
                    'url' => "https://reporter.nih.gov/project-details/{$project['appl_id']}",
                    'category' => 'mental_health',
                    'keywords' => $this->extractKeywords($project['project_title'] ?? '', $project['abstract_text'] ?? ''),
                    'raw_data' => $project,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('NIH RePORTER scrape error', ['error' => $e->getMessage()]);
        }

        return ['source' => 'nih', 'imported' => $imported];
    }

    /**
     * USASpending.gov — federal spending/award data for intelligence.
     * API: https://api.usaspending.gov/api/v2/
     * No API key needed.
     */
    public function scrapeUsaSpending(): array
    {
        $imported = 0;

        try {
            // Get spending by CFDA programs related to mental health
            $response = Http::timeout(30)->post('https://api.usaspending.gov/api/v2/search/spending_by_award/', [
                'filters' => [
                    'keywords' => ['mental health', 'behavioral health', 'substance abuse', 'SAMHSA'],
                    'time_period' => [
                        ['start_date' => now()->subYear()->format('Y-m-d'), 'end_date' => now()->format('Y-m-d')],
                    ],
                    'award_type_codes' => ['02', '03', '04', '05'], // grants
                ],
                'fields' => [
                    'Award ID', 'Recipient Name', 'Award Amount',
                    'Description', 'Start Date', 'End Date',
                    'Awarding Agency', 'CFDA Number',
                ],
                'limit' => 50,
                'page' => 1,
                'sort' => 'Award Amount',
                'order' => 'desc',
            ]);

            if (!$response->successful()) {
                Log::warning('USASpending search failed', ['status' => $response->status()]);
                return ['source' => 'usaspending', 'imported' => 0];
            }

            $data = $response->json();
            $awards = $data['results'] ?? [];

            foreach ($awards as $award) {
                $imported += $this->upsertOpportunity([
                    'source' => 'usaspending',
                    'external_id' => $award['Award ID'] ?? Str::random(12),
                    'title' => Str::limit($award['Description'] ?? $award['Recipient Name'] ?? 'Untitled', 255),
                    'description' => $award['Description'] ?? null,
                    'agency_source' => $award['Awarding Agency'] ?? null,
                    'cfda_number' => $award['CFDA Number'] ?? null,
                    'funding_type' => 'grant',
                    'amount_min' => abs($award['Award Amount'] ?? 0),
                    'amount_max' => abs($award['Award Amount'] ?? 0),
                    'amount_display' => isset($award['Award Amount']) ? '$' . number_format(abs($award['Award Amount'])) : null,
                    'open_date' => $this->parseDate($award['Start Date'] ?? null),
                    'close_date' => $this->parseDate($award['End Date'] ?? null),
                    'status' => 'awarded',
                    'url' => "https://www.usaspending.gov/award/{$award['internal_id']}",
                    'category' => $this->categorize($award['Description'] ?? '', ''),
                    'raw_data' => $award,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('USASpending scrape error', ['error' => $e->getMessage()]);
        }

        return ['source' => 'usaspending', 'imported' => $imported];
    }

    /**
     * SAMHSA.gov — scrape their grant announcements page.
     * No API available, so we pull their RSS/listing page.
     */
    public function scrapeSamhsa(): array
    {
        $imported = 0;

        try {
            // SAMHSA publishes grant announcements on their site
            $response = Http::timeout(30)->get('https://www.samhsa.gov/grants/grants-dashboard');

            if (!$response->successful()) {
                // Fallback: search Grants.gov specifically for SAMHSA
                $response = Http::timeout(30)->post('https://apply07.grants.gov/grantsws/rest/opportunities/search/', [
                    'keyword' => 'SAMHSA',
                    'oppStatuses' => 'forecasted|posted',
                    'sortBy' => 'closeDateAsc',
                    'rows' => 50,
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    foreach ($data['oppHits'] ?? [] as $opp) {
                        $imported += $this->upsertOpportunity([
                            'source' => 'samhsa',
                            'external_id' => 'samhsa-' . ($opp['id'] ?? Str::random(12)),
                            'title' => $opp['title'] ?? 'Untitled',
                            'description' => Str::limit($opp['synopsis'] ?? '', 1000),
                            'agency_source' => 'SAMHSA',
                            'cfda_number' => $opp['cfdaList'] ?? null,
                            'funding_type' => $this->mapFundingType($opp['oppCategory'] ?? ''),
                            'amount_display' => $this->formatAmount($opp['awardCeiling'] ?? null, $opp['awardFloor'] ?? null),
                            'amount_min' => $opp['awardFloor'] ?? null,
                            'amount_max' => $opp['awardCeiling'] ?? null,
                            'open_date' => $this->parseDate($opp['openDate'] ?? null),
                            'close_date' => $this->parseDate($opp['closeDate'] ?? null),
                            'status' => 'open',
                            'url' => "https://www.grants.gov/search-results-detail/{$opp['id']}",
                            'category' => $this->categorize($opp['title'] ?? '', $opp['synopsis'] ?? ''),
                            'keywords' => $this->extractKeywords($opp['title'] ?? '', $opp['synopsis'] ?? ''),
                            'raw_data' => $opp,
                        ]);
                    }
                }

                return ['source' => 'samhsa', 'imported' => $imported];
            }

            // Parse the SAMHSA grants dashboard HTML for grant listings
            $html = $response->body();
            preg_match_all('/<a[^>]*href="([^"]*grant[^"]*)"[^>]*>([^<]+)<\/a>/i', $html, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $url = $match[1];
                $title = trim($match[2]);
                if (!$this->isRelevant($title, '')) continue;
                if (strlen($title) < 10) continue;

                $imported += $this->upsertOpportunity([
                    'source' => 'samhsa',
                    'external_id' => 'samhsa-' . md5($url),
                    'title' => $title,
                    'description' => 'SAMHSA grant opportunity — visit link for full details.',
                    'agency_source' => 'SAMHSA',
                    'funding_type' => 'grant',
                    'status' => 'open',
                    'url' => str_starts_with($url, 'http') ? $url : "https://www.samhsa.gov{$url}",
                    'category' => $this->categorize($title, ''),
                    'keywords' => $this->extractKeywords($title, ''),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('SAMHSA scrape error', ['error' => $e->getMessage()]);
        }

        return ['source' => 'samhsa', 'imported' => $imported];
    }

    /**
     * ProPublica Nonprofit Explorer — foundation 990 data.
     * API: https://projects.propublica.org/nonprofits/api
     * Free, no key needed.
     * Finds foundations that fund mental health.
     */
    public function scrapeProPublica990s(): array
    {
        $imported = 0;
        $searchTerms = ['mental health foundation', 'behavioral health fund', 'psychiatric foundation', 'substance abuse foundation'];

        foreach ($searchTerms as $keyword) {
            try {
                $response = Http::timeout(30)->get('https://projects.propublica.org/nonprofits/api/v2/search.json', [
                    'q' => $keyword,
                ]);

                if (!$response->successful()) {
                    Log::warning("ProPublica search failed for '{$keyword}'", ['status' => $response->status()]);
                    continue;
                }

                $data = $response->json();
                $orgs = $data['organizations'] ?? [];

                foreach ($orgs as $org) {
                    if (!$org['name'] || !($org['total_revenue'] ?? 0)) continue;

                    $revenue = $org['total_revenue'] ?? 0;
                    if ($revenue < 50000) continue; // Skip tiny orgs

                    $imported += $this->upsertOpportunity([
                        'source' => 'foundation',
                        'external_id' => 'pp-' . ($org['ein'] ?? Str::random(12)),
                        'title' => $org['name'],
                        'description' => "Foundation with " . ($revenue > 0 ? '$' . number_format($revenue) : 'undisclosed') . " in revenue. NTEE: {$org['ntee_code']} — {$org['city']}, {$org['state']}. Check their website or 990 filings for grant programs.",
                        'agency_source' => $org['name'],
                        'funding_type' => 'grant',
                        'amount_display' => $revenue > 1000000 ? '$' . round($revenue / 1000000, 1) . 'M revenue' : '$' . round($revenue / 1000) . 'K revenue',
                        'status' => 'open',
                        'url' => "https://projects.propublica.org/nonprofits/organizations/{$org['ein']}",
                        'category' => 'mental_health',
                        'keywords' => ['foundation', 'mental health', $org['state'] ?? ''],
                        'raw_data' => $org,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error("ProPublica scrape error for '{$keyword}'", ['error' => $e->getMessage()]);
            }
        }

        return ['source' => 'propublica', 'imported' => $imported];
    }

    /**
     * Get spending trends from USASpending for the intelligence page.
     */
    public function getSpendingTrends(): array
    {
        try {
            $agencies = ['SAMHSA' => '7529', 'HRSA' => '7530', 'NIMH' => '7529'];
            $trends = [];

            $response = Http::timeout(30)->post('https://api.usaspending.gov/api/v2/search/spending_by_category/cfda', [
                'filters' => [
                    'keywords' => ['mental health', 'behavioral health'],
                    'time_period' => [
                        ['start_date' => now()->subYear()->format('Y-m-d'), 'end_date' => now()->format('Y-m-d')],
                    ],
                ],
                'limit' => 10,
                'page' => 1,
            ]);

            if ($response->successful()) {
                $trends = $response->json()['results'] ?? [];
            }

            return $trends;
        } catch (\Exception $e) {
            Log::error('USASpending trends error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    // ─── Helpers ──────────────────────────────────────────────

    private function upsertOpportunity(array $data): int
    {
        $data['scraped_at'] = now();

        // Handle cfda_number if it's an array
        if (is_array($data['cfda_number'] ?? null)) {
            $data['cfda_number'] = implode(', ', $data['cfda_number']);
        }

        FundingOpportunity::updateOrCreate(
            ['source' => $data['source'], 'external_id' => $data['external_id']],
            $data
        );

        return 1;
    }

    private function isRelevant(string $title, string $description): bool
    {
        $text = strtolower($title . ' ' . $description);
        foreach (self::MH_KEYWORDS as $kw) {
            if (str_contains($text, strtolower($kw))) return true;
        }
        return false;
    }

    private function categorize(string $title, string $description): string
    {
        $text = strtolower($title . ' ' . $description);
        if (str_contains($text, 'substance') || str_contains($text, 'opioid') || str_contains($text, 'addiction')) return 'substance_use';
        if (str_contains($text, 'workforce') || str_contains($text, 'training')) return 'workforce';
        if (str_contains($text, 'suicide') || str_contains($text, 'crisis')) return 'crisis';
        if (str_contains($text, 'veteran') || str_contains($text, 'va ')) return 'veterans';
        if (str_contains($text, 'youth') || str_contains($text, 'child')) return 'youth';
        if (str_contains($text, 'telehealth') || str_contains($text, 'telepsych')) return 'telehealth';
        return 'mental_health';
    }

    private function extractKeywords(string $title, string $description): array
    {
        $text = strtolower($title . ' ' . $description);
        $found = [];
        foreach (self::MH_KEYWORDS as $kw) {
            if (str_contains($text, strtolower($kw))) $found[] = $kw;
        }
        return array_unique($found);
    }

    private function mapFundingType(?string $type): string
    {
        $type = strtolower($type ?? '');
        if (str_contains($type, 'discretionary') || str_contains($type, 'grant')) return 'grant';
        if (str_contains($type, 'cooperative')) return 'cooperative_agreement';
        if (str_contains($type, 'contract')) return 'contract';
        return 'grant';
    }

    private function formatAmount($ceiling, $floor): ?string
    {
        if (!$ceiling && !$floor) return null;
        $fmt = fn ($v) => $v >= 1000000 ? '$' . round($v / 1000000, 1) . 'M' : '$' . round($v / 1000) . 'K';
        if ($floor && $ceiling && $floor != $ceiling) return $fmt($floor) . '–' . $fmt($ceiling);
        return $fmt($ceiling ?: $floor);
    }

    private function parseDate($date): ?string
    {
        if (!$date) return null;
        try {
            // Handle various formats: MM/DD/YYYY, YYYY-MM-DD, timestamps
            if (is_numeric($date)) return date('Y-m-d', $date / 1000);
            return date('Y-m-d', strtotime($date));
        } catch (\Exception $e) {
            return null;
        }
    }
}

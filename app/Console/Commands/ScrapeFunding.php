<?php

namespace App\Console\Commands;

use App\Services\FundingScraperService;
use Illuminate\Console\Command;

class ScrapeFunding extends Command
{
    protected $signature = 'funding:scrape {--source= : Scrape a specific source (grants_gov, sam_gov, nih, usaspending)}';
    protected $description = 'Scrape mental health funding opportunities from government APIs';

    public function handle(FundingScraperService $scraper): int
    {
        $source = $this->option('source');

        if ($source) {
            $this->info("Scraping {$source}...");
            $result = match ($source) {
                'grants_gov' => $scraper->scrapeGrantsGov(),
                'sam_gov' => $scraper->scrapeSamGov(),
                'nih' => $scraper->scrapeNihReporter(),
                'usaspending' => $scraper->scrapeUsaSpending(),
                default => null,
            };

            if (!$result) {
                $this->error("Unknown source: {$source}");
                return Command::FAILURE;
            }

            $this->info("Done: {$result['imported']} imported from {$result['source']}");
            return Command::SUCCESS;
        }

        $this->info('Scraping all funding sources...');
        $results = $scraper->scrapeAll();

        $total = 0;
        foreach ($results as $result) {
            $count = $result['imported'] ?? 0;
            $total += $count;
            $this->line("  {$result['source']}: {$count} imported" . (isset($result['skipped']) ? " (skipped: {$result['skipped']})" : ''));
        }

        $this->info("Total: {$total} opportunities imported/updated.");
        return Command::SUCCESS;
    }
}

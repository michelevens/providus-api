<?php

namespace App\Console\Commands;

use App\Models\Agency;
use App\Models\Provider;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RunExclusionScreening extends Command
{
    protected $signature = 'exclusions:screen-all {--agency= : Screen a specific agency only}';
    protected $description = 'Run OIG/SAM exclusion screening for all active providers';

    public function handle(): int
    {
        $query = Provider::where('is_active', true)->with('agency');

        if ($agencyId = $this->option('agency')) {
            $query->where('agency_id', (int) $agencyId);
        }

        $providers = $query->get();
        $this->info("Screening {$providers->count()} providers...");

        $matches = 0;
        $screened = 0;

        foreach ($providers as $provider) {
            if (!$provider->first_name || !$provider->last_name) continue;

            try {
                $result = $this->checkOIG($provider);
                $screened++;

                if ($result) {
                    $matches++;
                    $provName = "{$provider->first_name} {$provider->last_name}";
                    NotificationService::send($provider->agency_id, 'license_expiring', "⚠ Exclusion match: {$provName}", [
                        'body' => "Potential match found on OIG LEIE. Immediate review required.",
                        'link' => '#exclusions',
                        'linkable_type' => 'provider',
                        'linkable_id' => $provider->id,
                    ]);
                    Log::warning("Exclusion match found for provider {$provider->id}: {$provName}");
                }
            } catch (\Exception $e) {
                Log::error("Exclusion screening failed for provider {$provider->id}: {$e->getMessage()}");
            }
        }

        $this->info("Screened {$screened} providers. {$matches} potential matches found.");
        return Command::SUCCESS;
    }

    private function checkOIG(Provider $provider): bool
    {
        try {
            $response = Http::timeout(10)
                ->get('https://exclusions.oig.hhs.gov/exclusions/search', [
                    'firstname' => $provider->first_name,
                    'lastname' => $provider->last_name,
                    'stype' => 'ind',
                ]);

            if ($response->ok()) {
                $body = strtolower($response->body());
                return str_contains($body, strtolower($provider->last_name))
                    && str_contains($body, 'excluded');
            }
        } catch (\Exception $e) {
            // Silent fail for individual checks
        }

        return false;
    }
}

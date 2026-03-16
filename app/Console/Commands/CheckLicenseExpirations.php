<?php

namespace App\Console\Commands;

use App\Services\LicenseMonitoringService;
use Illuminate\Console\Command;

class CheckLicenseExpirations extends Command
{
    protected $signature = 'licenses:check-expirations {--agency= : Check a specific agency only}';
    protected $description = 'Check all licenses and DEA registrations for upcoming expirations and send alerts';

    public function handle(LicenseMonitoringService $service): int
    {
        $agencyId = $this->option('agency') ? (int) $this->option('agency') : null;

        $this->info('Checking license expirations...');
        $alerts = $service->checkExpirations($agencyId);

        $this->table(
            ['Alert Type', 'Count'],
            [
                ['Expired', $alerts['expired']],
                ['Critical (≤30 days)', $alerts['critical_30']],
                ['Warning (≤60 days)', $alerts['warning_60']],
                ['Notice (≤90 days)', $alerts['notice_90']],
            ]
        );

        $total = array_sum($alerts);
        $this->info("Done. {$total} alerts generated.");

        return Command::SUCCESS;
    }
}

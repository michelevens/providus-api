<?php

namespace App\Console\Commands;

use App\Services\LicenseMonitoringService;
use Illuminate\Console\Command;

class VerifyLicenses extends Command
{
    protected $signature = 'licenses:verify {--agency= : Verify licenses for a specific agency}';
    protected $description = 'Bulk verify all licenses against NPPES for an agency or all agencies';

    public function handle(LicenseMonitoringService $service): int
    {
        $agencyId = $this->option('agency') ? (int) $this->option('agency') : null;

        if (!$agencyId) {
            $agencyIds = \App\Models\Agency::where('is_active', true)->pluck('id');
            $this->info("Verifying licenses for {$agencyIds->count()} agencies...");

            $totals = ['verified' => 0, 'mismatch' => 0, 'error' => 0, 'total' => 0];
            foreach ($agencyIds as $id) {
                $results = $service->verifyAllForAgency($id);
                foreach ($totals as $key => &$val) {
                    $val += $results[$key] ?? 0;
                }
                $this->line("  Agency #{$id}: {$results['total']} licenses checked");
            }
        } else {
            $this->info("Verifying licenses for agency #{$agencyId}...");
            $totals = $service->verifyAllForAgency($agencyId);
        }

        $this->table(
            ['Status', 'Count'],
            [
                ['Total', $totals['total']],
                ['Verified', $totals['verified']],
                ['Mismatch', $totals['mismatch']],
                ['Error', $totals['error']],
            ]
        );

        return Command::SUCCESS;
    }
}

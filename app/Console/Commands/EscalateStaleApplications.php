<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class EscalateStaleApplications extends Command
{
    protected $signature = 'applications:escalate-stale {--days=30 : Days without activity before escalation}';
    protected $description = 'Flag applications with no activity for N days and notify agency';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        // Find active applications with no updates in N days
        $stale = Application::whereNotIn('status', ['approved', 'credentialed', 'denied', 'withdrawn'])
            ->where('updated_at', '<', $cutoff)
            ->with(['provider', 'payer'])
            ->get();

        $count = 0;
        foreach ($stale as $app) {
            $provName = $app->provider ? "{$app->provider->first_name} {$app->provider->last_name}" : 'Unknown';
            $payerName = $app->payer?->name ?? $app->payer_name ?? 'Unknown Payer';
            $daysSince = (int) $app->updated_at->diffInDays(now());

            NotificationService::send($app->agency_id, 'app_status', "Stale application: {$provName} — {$payerName}", [
                'body' => "No activity for {$daysSince} days. Status: {$app->status}. Consider following up.",
                'link' => '#applications',
                'linkable_type' => 'application',
                'linkable_id' => $app->id,
            ]);
            $count++;
        }

        $this->info("Flagged {$count} stale applications (>{$days} days inactive).");
        return Command::SUCCESS;
    }
}

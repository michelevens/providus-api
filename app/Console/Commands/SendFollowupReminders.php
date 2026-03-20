<?php

namespace App\Console\Commands;

use App\Models\Agency;
use App\Models\Followup;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendFollowupReminders extends Command
{
    protected $signature = 'followups:send-reminders';
    protected $description = 'Send notifications for overdue and upcoming follow-ups';

    public function handle(): int
    {
        $today = now()->toDateString();
        $upcoming = now()->addDays(3)->toDateString();

        // Overdue follow-ups (due_date < today, not completed)
        $overdue = Followup::where('is_completed', false)
            ->whereNotNull('due_date')
            ->where('due_date', '<', $today)
            ->with(['application.provider', 'application.payer'])
            ->get();

        $overdueCount = 0;
        foreach ($overdue as $f) {
            $provider = $f->application?->provider;
            $payer = $f->application?->payer;
            $provName = $provider ? "{$provider->first_name} {$provider->last_name}" : 'Unknown';
            $payerName = $payer?->name ?? $f->application?->payer_name ?? 'Unknown Payer';

            NotificationService::send($f->agency_id, 'followup_overdue', "Overdue follow-up: {$provName}", [
                'body' => "{$payerName} — {$f->type}: due {$f->due_date->format('M j')}",
                'link' => '#followups',
                'linkable_type' => 'followup',
                'linkable_id' => $f->id,
            ]);
            $overdueCount++;
        }

        // Upcoming follow-ups (due in next 3 days)
        $upcomingSoon = Followup::where('is_completed', false)
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$today, $upcoming])
            ->with(['application.provider', 'application.payer'])
            ->get();

        $upcomingCount = 0;
        foreach ($upcomingSoon as $f) {
            $provider = $f->application?->provider;
            $provName = $provider ? "{$provider->first_name} {$provider->last_name}" : 'Unknown';
            $payerName = $f->application?->payer?->name ?? $f->application?->payer_name ?? 'Unknown';

            NotificationService::send($f->agency_id, 'followup_overdue', "Follow-up due soon: {$provName}", [
                'body' => "{$payerName} — due {$f->due_date->format('M j, Y')}",
                'link' => '#followups',
            ]);
            $upcomingCount++;
        }

        $this->info("Sent {$overdueCount} overdue + {$upcomingCount} upcoming follow-up reminders.");
        return Command::SUCCESS;
    }
}

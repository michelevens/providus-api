<?php

namespace App\Console\Commands;

use App\Mail\LicenseExpirationReminder;
use App\Models\License;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendLicenseExpirationReminders extends Command
{
    protected $signature = 'notifications:license-expiry';
    protected $description = 'Send email reminders for licenses expiring within 30/60/90 days';

    public function handle(): int
    {
        $thresholds = [30, 60, 90];
        $totalSent = 0;

        foreach ($thresholds as $days) {
            $targetDate = now()->addDays($days)->toDateString();
            $licenses = License::whereDate('expiration_date', $targetDate)
                ->where('status', '!=', 'inactive')
                ->with('provider')
                ->get();

            foreach ($licenses as $license) {
                $admins = User::where('agency_id', $license->agency_id)
                    ->whereIn('role', ['owner', 'agency'])
                    ->where('is_active', true)
                    ->get();

                foreach ($admins as $admin) {
                    try {
                        Mail::to($admin->email)->send(
                            new LicenseExpirationReminder(
                                $license,
                                $license->provider?->full_name ?? 'Unknown Provider',
                                $days
                            )
                        );
                        $totalSent++;
                    } catch (\Throwable $e) {
                        \Log::warning("License expiry email failed for license {$license->id}: {$e->getMessage()}");
                    }
                }
            }

            $this->info("Sent {$licenses->count()} reminders for {$days}-day expiration");
        }

        $this->info("Total emails sent: {$totalSent}");
        return Command::SUCCESS;
    }
}

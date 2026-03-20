<?php

namespace App\Console\Commands;

use App\Models\DeaRegistration;
use App\Models\ProviderBoard;
use App\Models\ProviderMalpractice;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class CheckDocumentExpirations extends Command
{
    protected $signature = 'documents:check-expirations';
    protected $description = 'Check DEA registrations, board certifications, and malpractice policies for upcoming expirations';

    public function handle(): int
    {
        $alerts = 0;

        // DEA Registration expirations
        $alerts += $this->checkDea();

        // Board certification expirations
        $alerts += $this->checkBoards();

        // Malpractice insurance expirations
        $alerts += $this->checkMalpractice();

        $this->info("Generated {$alerts} document expiration alerts.");
        return Command::SUCCESS;
    }

    private function checkDea(): int
    {
        $count = 0;
        $cutoff90 = now()->addDays(90)->toDateString();

        $expiring = DeaRegistration::whereNotNull('expiration_date')
            ->where('expiration_date', '<=', $cutoff90)
            ->where('expiration_date', '>', now()->toDateString())
            ->with('provider')
            ->get();

        foreach ($expiring as $dea) {
            $days = (int) now()->diffInDays($dea->expiration_date, false);
            $provName = $dea->provider ? "{$dea->provider->first_name} {$dea->provider->last_name}" : 'Unknown';
            $severity = $days <= 30 ? 'critical' : ($days <= 60 ? 'warning' : 'notice');

            NotificationService::send($dea->agency_id, 'license_expiring', "DEA expiring: {$provName}", [
                'body' => "DEA #{$dea->dea_number} — {$dea->state} expires in {$days} days ({$dea->expiration_date->format('M j, Y')})",
                'link' => '#licenses',
            ]);
            $count++;
        }

        // Expired
        $expired = DeaRegistration::whereNotNull('expiration_date')
            ->where('expiration_date', '<', now()->toDateString())
            ->where('status', '!=', 'inactive')
            ->with('provider')
            ->get();

        foreach ($expired as $dea) {
            $provName = $dea->provider ? "{$dea->provider->first_name} {$dea->provider->last_name}" : 'Unknown';
            NotificationService::send($dea->agency_id, 'license_expiring', "DEA EXPIRED: {$provName}", [
                'body' => "DEA #{$dea->dea_number} expired {$dea->expiration_date->format('M j, Y')}. Immediate renewal required.",
                'link' => '#licenses',
            ]);
            $count++;
        }

        $this->info("  DEA: {$count} alerts");
        return $count;
    }

    private function checkBoards(): int
    {
        $count = 0;
        $cutoff90 = now()->addDays(90)->toDateString();

        try {
            $expiring = ProviderBoard::whereNotNull('expiration_date')
                ->where('expiration_date', '<=', $cutoff90)
                ->where('expiration_date', '>', now()->toDateString())
                ->with('provider')
                ->get();

            foreach ($expiring as $board) {
                $days = (int) now()->diffInDays($board->expiration_date, false);
                $provName = $board->provider ? "{$board->provider->first_name} {$board->provider->last_name}" : 'Unknown';

                NotificationService::send($board->agency_id, 'license_expiring', "Board cert expiring: {$provName}", [
                    'body' => "{$board->board_name} expires in {$days} days",
                    'link' => '#licenses',
                ]);
                $count++;
            }
        } catch (\Exception $e) {
            $this->warn("  Board check skipped: {$e->getMessage()}");
        }

        $this->info("  Boards: {$count} alerts");
        return $count;
    }

    private function checkMalpractice(): int
    {
        $count = 0;
        $cutoff90 = now()->addDays(90)->toDateString();

        try {
            $expiring = ProviderMalpractice::whereNotNull('expiration_date')
                ->where('expiration_date', '<=', $cutoff90)
                ->where('expiration_date', '>', now()->toDateString())
                ->with('provider')
                ->get();

            foreach ($expiring as $mal) {
                $days = (int) now()->diffInDays($mal->expiration_date, false);
                $provName = $mal->provider ? "{$mal->provider->first_name} {$mal->provider->last_name}" : 'Unknown';

                NotificationService::send($mal->agency_id, 'license_expiring', "Malpractice COI expiring: {$provName}", [
                    'body' => "{$mal->carrier} policy expires in {$days} days",
                    'link' => '#licenses',
                ]);
                $count++;
            }
        } catch (\Exception $e) {
            $this->warn("  Malpractice check skipped: {$e->getMessage()}");
        }

        $this->info("  Malpractice: {$count} alerts");
        return $count;
    }
}

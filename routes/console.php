<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('promote-superadmin {email}', function (string $email) {
    $user = \App\Models\User::where('email', $email)->firstOrFail();
    $user->role = 'superadmin';
    $user->save();
    $this->info("User {$email} promoted to superadmin (id: {$user->id})");
})->purpose('Promote a user to superadmin role');

Artisan::command('reset-password {email} {password}', function (string $email, string $password) {
    $user = \App\Models\User::where('email', $email)->firstOrFail();
    $user->password = $password;
    $user->save();
    $this->info("Password reset for {$email} (id: {$user->id})");
})->purpose('Reset a user password');

Artisan::command('change-email {old} {new}', function (string $old, string $new) {
    $user = \App\Models\User::where('email', $old)->firstOrFail();
    $user->email = $new;
    $user->save();
    $this->info("Email changed from {$old} to {$new} (id: {$user->id})");
})->purpose('Change a user email address');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
*/

// ── License & Credential Monitoring ──
Schedule::command('licenses:check-expirations')->dailyAt('07:00');        // License alerts
Schedule::command('notifications:license-expiry')->dailyAt('08:00');      // License email reminders (30/60/90 day)
Schedule::command('documents:check-expirations')->dailyAt('07:30');       // DEA, board cert, malpractice COI alerts
Schedule::command('licenses:verify')->weeklyOn(1, '03:00');               // Bulk NPPES verification

// ── Follow-up & Task Reminders ──
Schedule::command('followups:send-reminders')->dailyAt('08:30');          // Overdue + upcoming (3 day) follow-ups
Schedule::command('tasks:send-reminders')->dailyAt('09:00');              // Overdue + due-today tasks

// ── Application Monitoring ──
Schedule::command('applications:escalate-stale --days=30')->dailyAt('09:30'); // Flag apps with no activity in 30 days

// ── Exclusion Screening ──
Schedule::command('exclusions:screen-all')->monthlyOn(1, '02:00');        // Monthly OIG/SAM screening

// ── Funding ──
Schedule::command('funding:scrape')->dailyAt('06:00');                    // Scrape funding opportunities

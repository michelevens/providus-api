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

// Daily at 7am UTC — check license expirations and send alerts
Schedule::command('licenses:check-expirations')->dailyAt('07:00');

// Daily at 8am UTC — send license expiration email reminders (30/60/90 day)
Schedule::command('notifications:license-expiry')->dailyAt('08:00');

// Weekly on Monday at 3am UTC — bulk verify licenses against NPPES
Schedule::command('licenses:verify')->weeklyOn(1, '03:00');

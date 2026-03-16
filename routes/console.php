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

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
*/

// Daily at 7am UTC — check license expirations and send alerts
Schedule::command('licenses:check-expirations')->dailyAt('07:00');

// Weekly on Monday at 3am UTC — bulk verify licenses against NPPES
Schedule::command('licenses:verify')->weeklyOn(1, '03:00');

<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PromoteSuperAdmin extends Command
{
    protected $signature = 'user:promote-superadmin {email}';
    protected $description = 'Promote a user to superadmin role';

    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email {$email} not found.");
            return 1;
        }

        $user->update(['role' => 'superadmin']);
        $this->info("User {$user->full_name} ({$email}) promoted to superadmin.");
        return 0;
    }
}

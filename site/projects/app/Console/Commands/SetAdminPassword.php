<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SetAdminPassword extends Command
{
    protected $signature = 'security:set-admin-password
        {email : Existing administrator email address}
        {--password= : Password value; omit to enter it without terminal echo}';

    protected $description = 'Set or rotate an existing administrator password';

    public function handle(): int
    {
        $user = User::where('email', (string) $this->argument('email'))->first();
        if (!$user) {
            $this->error('No user exists with that email address.');
            return self::FAILURE;
        }

        $password = (string) ($this->option('password') ?: $this->secret('New password'));
        if (strlen($password) < 12) {
            $this->error('The password must contain at least 12 characters.');
            return self::FAILURE;
        }

        if (!$this->option('password')) {
            $confirmation = (string) $this->secret('Confirm password');
            if (!hash_equals($password, $confirmation)) {
                $this->error('The passwords do not match.');
                return self::FAILURE;
            }
        }

        $user->forceFill(['password' => Hash::make($password)])->save();
        $this->info("Password rotated for {$user->email}.");

        return self::SUCCESS;
    }
}

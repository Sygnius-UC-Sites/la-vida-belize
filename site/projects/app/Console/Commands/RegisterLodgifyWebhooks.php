<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Backward-compatible alias for the unified idempotent registration command.
 */
class RegisterLodgifyWebhooks extends Command
{
    protected $signature = 'lodgify:register-webhooks
                            {--list : Deprecated; registration now lists and reuses existing subscriptions automatically}
                            {--delete-all : Unsupported safety option}';

    protected $description = 'Register Lodgify webhooks using the unified webhook registrar';

    public function handle(): int
    {
        if ($this->option('delete-all')) {
            $this->error('Bulk webhook deletion is intentionally disabled. Remove individual subscriptions in Lodgify.');
            return self::FAILURE;
        }

        if ($this->option('list')) {
            $this->warn('Use webhooks:register --pms=lodgify --dry-run to inspect the configured callback URLs.');
            return self::SUCCESS;
        }

        return Artisan::call('webhooks:register', ['--pms' => 'lodgify']);
    }
}

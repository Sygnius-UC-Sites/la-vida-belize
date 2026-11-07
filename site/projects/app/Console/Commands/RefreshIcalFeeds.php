<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use LiveCart;

class RefreshIcalFeeds extends Command
{
    protected $signature = 'ical:refresh-feeds';
    protected $description = 'Refresh all imported iCal feeds for properties.';

    public function handle(): int
    {
        try {
            LiveCart::allIcalImportListRefresh();
            $this->info('iCal feeds refreshed.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('iCal refresh failed: ' . $e->getMessage());
            logger()->error('RefreshIcalFeeds failed', ['exception' => $e]);
            return self::FAILURE;
        }
    }
}

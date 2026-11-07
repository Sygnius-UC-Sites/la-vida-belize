<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * php projects/artisan pms:reset-and-sync
 *
 * Wipes all PMS property/sync data from the database (booking_requests and
 * payments are NOT touched) then re-runs the full sync from all 4 PMS APIs.
 */
class ResetAndSyncPms extends Command
{
    protected $signature   = 'pms:reset-and-sync';
    protected $description = 'Clear all PMS property data and re-sync fresh from all APIs';

    public function handle(): int
    {
        $this->warn('This will DELETE all synced PMS property data.');
        $this->warn('booking_requests, payments, and settings are NOT affected.');

        if (!$this->confirm('Are you sure you want to proceed?')) {
            $this->info('Cancelled.');
            return 0;
        }

        // ── 1. Truncate PMS data tables ───────────────────────────────────────
        $tables = [
            'pms_images',
            'pms_amenity_group_items',
            'pms_amenity_groups',
            'pms_rates',
            'pms_unavailable_dates',
            'pms_bookings',
            'pms_rooms',
            'pms_properties',
            'pms_hostfully_data',
            'pms_hostaway_data',
            'pms_guesty_data',
            'pms_lodgify_data',
            'ical_events',
            'ical_import_list',
        ];

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        foreach ($tables as $table) {
            try {
                DB::table($table)->truncate();
                $this->line("  Cleared: {$table}");
            } catch (\Throwable $e) {
                $this->warn("  Skipped {$table}: " . $e->getMessage());
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->info('All PMS tables cleared.');

        // ── 2. Re-sync from all PMS APIs ─────────────────────────────────────
        $this->newLine();
        $this->info('Starting full sync from all 4 PMS accounts...');
        $this->newLine();

        $this->call('pms:sync-all');

        $this->newLine();
        $this->info('Done. Go to Admin → Rooms List to see all synced properties.');
        $this->info('Set status = true on any property you want to show on the website.');

        return 0;
    }
}

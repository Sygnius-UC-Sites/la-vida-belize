<?php

namespace App\Console\Commands;

use App\Services\Scraping\ApifyClient;
use App\Services\Scraping\ScrapeOrchestrator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Localhost / no-webhook fallback. Apify webhooks need a publicly reachable
 * URL — on dev installs without one (XAMPP localhost), schedule this command
 * (e.g. * * * * * `php artisan importer:poll-runs`) so in-progress runs still
 * get finalised when they complete.
 */
class ApifyPollRuns extends Command
{
    protected $signature = 'importer:poll-runs {--max=20 : maximum number of runs to check this tick}';
    protected $description = 'Poll Apify for in-progress scrape runs and finalise any that have completed.';

    public function handle(ApifyClient $client, ScrapeOrchestrator $orchestrator): int
    {
        $max = (int) $this->option('max');

        $rows = DB::table('scrape_runs')
            ->whereNotIn('status', ['SUCCEEDED', 'FAILED', 'TIMED-OUT', 'ABORTED'])
            ->whereNotNull('provider_run_id')
            ->orderBy('id')
            ->limit($max)
            ->get(['id', 'provider_run_id', 'status']);

        if ($rows->isEmpty()) {
            $this->info('No in-progress runs.');
            return self::SUCCESS;
        }

        $finalised = 0;
        foreach ($rows as $row) {
            try {
                $live = $client->getRun((string) $row->provider_run_id);
                $status = (string) ($live['status'] ?? '');
                if (in_array($status, ['SUCCEEDED', 'FAILED', 'TIMED-OUT', 'ABORTED'], true)) {
                    $this->line("Finalising run {$row->id} (apify={$row->provider_run_id}, status={$status})");
                    if ($orchestrator->finalise((int) $row->id)) {
                        $finalised++;
                    }
                } else {
                    DB::table('scrape_runs')->where('id', $row->id)->update([
                        'status' => $status ?: $row->status,
                        'updated_at' => now(),
                    ]);
                }
            } catch (\Throwable $e) {
                $this->warn("Run {$row->id} poll failed: " . $e->getMessage());
            }
        }

        $this->info("Polled " . $rows->count() . " run(s); finalised $finalised.");
        return self::SUCCESS;
    }
}

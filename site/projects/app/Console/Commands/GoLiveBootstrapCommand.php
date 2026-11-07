<?php

namespace App\Console\Commands;

use App\Models\BasicSetting;
use App\Services\Operations\OperationsRecorder;
use App\Support\WebhookConfiguration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class GoLiveBootstrapCommand extends Command
{
    protected $signature = 'ops:go-live-bootstrap
                            {--url= : Public HTTPS site URL, e.g. https://example.com}
                            {--dry-run-webhooks : Print PMS webhook URLs without calling provider APIs}
                            {--skip-webhooks : Do not run webhook registration}
                            {--skip-queue : Do not dispatch the queue worker probe}
                            {--queue-wait=30 : Seconds to wait for the queue probe}';

    protected $description = 'Run idempotent go-live operations: webhook base URL, webhook registration, scheduler heartbeat, and queue proof';

    public function handle(OperationsRecorder $recorder): int
    {
        $run = $recorder->start(
            'go_live_bootstrap',
            'running',
            'ops:go-live-bootstrap',
            'console',
            null,
            null,
            'Go-live bootstrap started',
            [
                'url' => $this->option('url') ?: WebhookConfiguration::baseUrl() ?: config('app.url'),
                'dry_run_webhooks' => (bool) $this->option('dry-run-webhooks'),
                'skip_webhooks' => (bool) $this->option('skip-webhooks'),
                'skip_queue' => (bool) $this->option('skip-queue'),
            ]
        );

        $allOk = true;
        $output = [];

        try {
            $url = rtrim((string) ($this->option('url') ?: WebhookConfiguration::baseUrl() ?: config('app.url')), '/');

            if (!filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with($url, 'https://')) {
                $this->error('A public HTTPS URL is required. Use --url=https://your-domain.com');
                $recorder->finish($run, 'failure', 'Go-live bootstrap failed: invalid HTTPS URL', implode("\n", $output), 'Invalid HTTPS URL');
                return self::FAILURE;
            }

            BasicSetting::query()->updateOrCreate(
                ['name' => 'webhook_base_url'],
                ['value' => $url]
            );
            putenv("WEBHOOK_BASE_URL={$url}");
            $_ENV['WEBHOOK_BASE_URL'] = $url;
            config(['app.url' => $url]);

            $this->info("WEBHOOK_BASE_URL={$url}");
            $output[] = "WEBHOOK_BASE_URL={$url}";

            if (!$this->option('skip-webhooks')) {
                $args = [];
                if ($this->option('dry-run-webhooks')) {
                    $args['--dry-run'] = true;
                }
                $code = Artisan::call('webhooks:register', $args);
                $cmdOutput = trim(Artisan::output());
                $this->line($cmdOutput);
                $output[] = "webhooks:register exit={$code}";
                $output[] = $cmdOutput;
                $allOk = $allOk && $code === 0;
            } else {
                $this->warn('Webhook registration skipped by option.');
                $output[] = 'webhooks:register skipped';
            }

            $heartbeatCode = Artisan::call('ops:scheduler-heartbeat');
            $heartbeatOutput = trim(Artisan::output());
            $this->line($heartbeatOutput);
            $output[] = "ops:scheduler-heartbeat exit={$heartbeatCode}";
            $output[] = $heartbeatOutput;
            $allOk = $allOk && $heartbeatCode === 0;

            if (!$this->option('skip-queue')) {
                $queueCode = Artisan::call('ops:queue-probe', [
                    '--wait' => max(1, min(300, (int) $this->option('queue-wait'))),
                    '--keep' => true,
                ]);
                $queueOutput = trim(Artisan::output());
                $this->line($queueOutput);
                $output[] = "ops:queue-probe exit={$queueCode}";
                $output[] = $queueOutput;
                $allOk = $allOk && $queueCode === 0;
            } else {
                $this->warn('Queue probe skipped by option.');
                $output[] = 'ops:queue-probe skipped';
            }

            Artisan::call('schedule:list', ['--json' => true]);
            $output[] = 'schedule:list captured';

            $status = $allOk ? 'success' : 'failure';
            $summary = $allOk
                ? 'Go-live bootstrap completed'
                : 'Go-live bootstrap completed with failures';

            $recorder->finish($run, $status, $summary, implode("\n", $output));
            $this->info(strtoupper($status) . ': ' . $summary);

            return $allOk ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $e) {
            $recorder->finish($run, 'failure', 'Go-live bootstrap failed', implode("\n", $output), $e->getMessage());
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}

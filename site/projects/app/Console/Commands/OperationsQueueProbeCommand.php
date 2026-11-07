<?php

namespace App\Console\Commands;

use App\Jobs\OperationsQueueProbe;
use App\Services\Operations\OperationsRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class OperationsQueueProbeCommand extends Command
{
    protected $signature = 'ops:queue-probe
                            {--wait=30 : Seconds to wait for a worker to process the probe}
                            {--keep : Keep the evidence file after a successful probe}';

    protected $description = 'Dispatch a harmless job and verify that a queue worker processes it';

    public function handle(OperationsRecorder $recorder): int
    {
        $wait = max(1, min(300, (int) $this->option('wait')));
        $probeId = (string) Str::uuid();
        $evidence = storage_path("app/ops/queue-probes/{$probeId}.json");

        OperationsQueueProbe::dispatch($probeId);

        $this->line("QUEUE_PROBE_ID={$probeId}");
        $this->line('QUEUE_CONNECTION=' . config('queue.default'));

        $deadline = microtime(true) + $wait;
        do {
            if (is_file($evidence)) {
                $this->info('QUEUE_PROBE_STATUS=PASS');
                $this->line("QUEUE_PROBE_EVIDENCE={$evidence}");
                $recorder->record(
                    'queue_probe',
                    'success',
                    'Queue worker processed probe',
                    "QUEUE_PROBE_ID={$probeId}\nQUEUE_PROBE_EVIDENCE={$evidence}",
                    null,
                    [
                        'probe_id' => $probeId,
                        'evidence' => $evidence,
                        'queue_connection' => config('queue.default'),
                    ],
                    'ops:queue-probe',
                    'console'
                );

                if (!$this->option('keep')) {
                    @unlink($evidence);
                }

                return self::SUCCESS;
            }

            usleep(250000);
        } while (microtime(true) < $deadline);

        $this->error('QUEUE_PROBE_STATUS=FAIL');
        $this->line("No worker processed the probe within {$wait} seconds.");
        $this->line('Confirm Supervisor/systemd is running queue:work, then retry.');
        $recorder->record(
            'queue_probe',
            'failure',
            'Queue worker did not process probe',
            "QUEUE_PROBE_ID={$probeId}",
            "No worker processed the probe within {$wait} seconds.",
            [
                'probe_id' => $probeId,
                'queue_connection' => config('queue.default'),
            ],
            'ops:queue-probe',
            'console'
        );

        return self::FAILURE;
    }
}

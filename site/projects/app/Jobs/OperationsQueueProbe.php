<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class OperationsQueueProbe implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 30;

    public function __construct(public readonly string $probeId)
    {
    }

    public function handle(): void
    {
        $directory = storage_path('app/ops/queue-probes');
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException("Unable to create queue probe directory: {$directory}");
        }

        $payload = [
            'probe_id' => $this->probeId,
            'processed_at' => now()->toIso8601String(),
            'queue_connection' => config('queue.default'),
            'hostname' => gethostname() ?: 'unknown',
        ];

        file_put_contents(
            "{$directory}/{$this->probeId}.json",
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL,
            LOCK_EX
        );
    }
}

<?php

namespace App\Console\Commands;

use App\Services\Operations\OperationsRecorder;
use Illuminate\Console\Command;

class OperationsHeartbeatCommand extends Command
{
    protected $signature = 'ops:scheduler-heartbeat';

    protected $description = 'Write a timestamp proving the Laravel scheduler executed';

    public function handle(OperationsRecorder $recorder): int
    {
        $directory = storage_path('app/ops');
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            $this->error("Unable to create operations directory: {$directory}");
            $recorder->record(
                'scheduler_heartbeat',
                'failure',
                'Scheduler heartbeat failed',
                null,
                "Unable to create operations directory: {$directory}",
                [],
                'ops:scheduler-heartbeat',
                'console'
            );
            return self::FAILURE;
        }

        $evidence = "{$directory}/scheduler-heartbeat.json";
        file_put_contents($evidence, json_encode([
            'executed_at' => now()->toIso8601String(),
            'hostname' => gethostname() ?: 'unknown',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, LOCK_EX);

        $this->line("SCHEDULER_HEARTBEAT={$evidence}");
        $recorder->record(
            'scheduler_heartbeat',
            'success',
            'Scheduler heartbeat written',
            "SCHEDULER_HEARTBEAT={$evidence}",
            null,
            ['evidence' => $evidence],
            'ops:scheduler-heartbeat',
            'console'
        );

        return self::SUCCESS;
    }
}

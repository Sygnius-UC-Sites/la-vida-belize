<?php

namespace App\Console\Commands;

use App\Services\Operations\OperationsRecorder;
use Illuminate\Console\Command;

class RecordOperationsCheckCommand extends Command
{
    protected $signature = 'ops:record-check
                            {type : Evidence type, e.g. test_suite, browser_smoke, production_http_check}
                            {status : success|failure|pass|fail|warn}
                            {--summary= : Short summary shown in admin}
                            {--detail= : Longer output/detail}
                            {--source=manual : Evidence source label}';

    protected $description = 'Record external go-live evidence so the admin checklist can display it';

    public function handle(OperationsRecorder $recorder): int
    {
        $type = (string) $this->argument('type');
        $status = strtolower((string) $this->argument('status'));
        if (!in_array($status, ['success', 'failure', 'pass', 'fail', 'warn'], true)) {
            $this->error('Status must be one of: success, failure, pass, fail, warn.');
            return self::FAILURE;
        }

        $normalized = match ($status) {
            'pass' => 'success',
            'fail' => 'failure',
            default => $status,
        };

        $recorder->record(
            $type,
            $normalized,
            (string) ($this->option('summary') ?: "{$type} {$normalized}"),
            (string) ($this->option('detail') ?: ''),
            $normalized === 'failure' ? (string) ($this->option('detail') ?: '') : null,
            [],
            'ops:record-check',
            (string) $this->option('source')
        );

        $this->info("Recorded {$type}: {$normalized}");
        return $normalized === 'failure' ? self::FAILURE : self::SUCCESS;
    }
}

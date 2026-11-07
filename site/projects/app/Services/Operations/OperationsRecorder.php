<?php

namespace App\Services\Operations;

use App\Helper\LogRedactor;
use App\Models\OperationsRun;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class OperationsRecorder
{
    public function start(
        string $runType,
        string $status = 'running',
        ?string $command = null,
        string $source = 'system',
        ?string $provider = null,
        ?string $event = null,
        ?string $summary = null,
        array $context = []
    ): ?OperationsRun {
        if (!$this->ready()) {
            return null;
        }

        try {
            return OperationsRun::create([
                'run_type' => $runType,
                'source' => $source,
                'provider' => $provider,
                'event' => $event,
                'command' => $command,
                'status' => $status,
                'started_at' => now(),
                'summary' => $this->limit($summary),
                'context' => $this->cleanContext($context),
            ]);
        } catch (\Throwable) {
            return null;
        }
    }

    public function finish(
        ?OperationsRun $run,
        string $status,
        ?string $summary = null,
        ?string $output = null,
        ?string $error = null,
        array $context = []
    ): void {
        if (!$run || !$this->ready()) {
            return;
        }

        try {
            $finishedAt = now();
            $startedAt = $run->started_at ?: $finishedAt;
            $run->update([
                'status' => $status,
                'finished_at' => $finishedAt,
                'duration_ms' => max(0, $finishedAt->diffInMilliseconds($startedAt)),
                'summary' => $this->limit($summary ?: $run->summary),
                'output' => $this->redactText($output),
                'error' => $this->redactText($error),
                'context' => $this->cleanContext(array_merge($run->context ?: [], $context)),
            ]);
        } catch (\Throwable) {
            // Operational evidence must never break the user-facing workflow.
        }
    }

    public function record(
        string $runType,
        string $status,
        ?string $summary = null,
        ?string $output = null,
        ?string $error = null,
        array $context = [],
        ?string $command = null,
        string $source = 'system',
        ?string $provider = null,
        ?string $event = null
    ): void {
        $run = $this->start($runType, $status, $command, $source, $provider, $event, $summary, $context);
        $this->finish($run, $status, $summary, $output, $error, $context);
    }

    public function recordWebhook(
        string $provider,
        string $event,
        string $status,
        array $payload,
        ?string $summary = null,
        ?string $error = null
    ): void {
        $payloadSummary = LogRedactor::summary($payload, 30);
        $this->record(
            'webhook_received',
            $status,
            $summary ?: "{$provider} {$event}",
            null,
            $error,
            ['payload' => $payloadSummary],
            null,
            'webhook',
            $provider,
            $event
        );
    }

    public static function scheduledStarted(string $command): void
    {
        try {
            $run = app(self::class)->start(
                'scheduler',
                'running',
                $command,
                'scheduler',
                null,
                null,
                "Scheduled command started: {$command}"
            );
            if ($run) {
                Cache::put(self::scheduleCacheKey($command), $run->id, now()->addHours(6));
            }
        } catch (\Throwable) {
        }
    }

    public static function scheduledFinished(string $command, string $status, ?string $error = null): void
    {
        try {
            $recorder = app(self::class);
            $run = null;
            $id = Cache::pull(self::scheduleCacheKey($command));
            if ($id) {
                $run = OperationsRun::find($id);
            }
            if (!$run) {
                $run = $recorder->start('scheduler', 'running', $command, 'scheduler');
            }
            $recorder->finish(
                $run,
                $status,
                "Scheduled command {$status}: {$command}",
                null,
                $error
            );
        } catch (\Throwable) {
        }
    }

    private static function scheduleCacheKey(string $command): string
    {
        return 'ops:schedule-run:' . sha1($command);
    }

    private function ready(): bool
    {
        try {
            return Schema::hasTable('ops_runs');
        } catch (\Throwable) {
            return false;
        }
    }

    private function cleanContext(array $context): array
    {
        $clean = LogRedactor::clean($context);
        return is_array($clean) ? $clean : [];
    }

    private function redactText(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        $text = preg_replace('/([?&](?:token|signature|secret|key)=)[^&\s]+/i', '$1[REDACTED]', $text) ?? $text;
        $text = preg_replace('#(/webhooks/guesty/)[^/?\s]+#i', '$1[REDACTED]', $text) ?? $text;
        $text = preg_replace('/\b(sk|pk)_(live|test)_[A-Za-z0-9_]+/i', '$1_$2_[REDACTED]', $text) ?? $text;
        $text = preg_replace('/\bwhsec_[A-Za-z0-9_]+/i', 'whsec_[REDACTED]', $text) ?? $text;

        return Str::limit($text, 20000, "\n[truncated]");
    }

    private function limit(?string $text): ?string
    {
        return $text === null ? null : Str::limit($text, 500, '');
    }
}

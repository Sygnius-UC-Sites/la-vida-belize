<?php

namespace Tests\Feature;

use Tests\TestCase;

class OperationsHeartbeatTest extends TestCase
{
    public function test_scheduler_heartbeat_writes_timestamped_evidence(): void
    {
        $path = storage_path('app/ops/scheduler-heartbeat.json');
        @unlink($path);

        $this->artisan('ops:scheduler-heartbeat')->assertSuccessful();

        $this->assertFileExists($path);
        $payload = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $this->assertNotEmpty($payload['executed_at'] ?? null);

        @unlink($path);
    }
}

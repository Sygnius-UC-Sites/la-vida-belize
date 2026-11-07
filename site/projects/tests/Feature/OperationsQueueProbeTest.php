<?php

namespace Tests\Feature;

use Tests\TestCase;

class OperationsQueueProbeTest extends TestCase
{
    public function test_queue_probe_proves_the_job_was_processed(): void
    {
        $this->artisan('ops:queue-probe', ['--wait' => 1])
            ->expectsOutputToContain('QUEUE_PROBE_STATUS=PASS')
            ->assertSuccessful();
    }
}

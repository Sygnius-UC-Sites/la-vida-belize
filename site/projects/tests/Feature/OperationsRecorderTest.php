<?php

namespace Tests\Feature;

use App\Services\Operations\OperationsRecorder;
use Tests\TestCase;

class OperationsRecorderTest extends TestCase
{
    public function test_webhook_payloads_are_redacted_before_storage(): void
    {
        app(OperationsRecorder::class)->recordWebhook('lodgify', 'booking_change', 'success', [
            'booking_id' => 'abc123',
            'guestEmail' => 'guest@example.test',
            'token' => 'secret-token',
        ], 'Booking webhook processed');

        $this->assertDatabaseHas('ops_runs', [
            'run_type' => 'webhook_received',
            'provider' => 'lodgify',
            'event' => 'booking_change',
            'status' => 'success',
        ]);

        $row = \App\Models\OperationsRun::query()->where('run_type', 'webhook_received')->firstOrFail();
        $this->assertSame('[REDACTED]', $row->context['payload']['guestEmail']);
        $this->assertSame('[REDACTED]', $row->context['payload']['token']);
        $this->assertSame('abc123', $row->context['payload']['booking_id']);
    }
}

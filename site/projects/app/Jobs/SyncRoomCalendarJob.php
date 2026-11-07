<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * SyncRoomCalendarJob
 *
 * Triggered by webhook events (rate change, listing update, booking, cancellation)
 * to refresh pms_calendar rows for a single room without blocking the webhook response.
 *
 * Dispatched with ->afterResponse() so it runs after the 200 is sent to the PMS,
 * meaning webhook round-trip time is unaffected even if the PMS API is slow.
 *
 * Uses pms:sync-calendars --provider=X --room=Y which fetches the full 731-day
 * window for that one room only (fast: one property, chunked requests).
 */
class SyncRoomCalendarJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;

    public function __construct(
        public readonly string $pmsSource,
        public readonly string $pmsRoomId
    ) {}

    public function handle(): void
    {
        Log::channel('pms_webhooks')->info('SyncRoomCalendarJob: starting calendar refresh', [
            'pms'    => $this->pmsSource,
            'room'   => $this->pmsRoomId,
        ]);

        Artisan::call('pms:sync-calendars', [
            '--provider' => $this->pmsSource,
            '--room'     => $this->pmsRoomId,
        ]);

        Log::channel('pms_webhooks')->info('SyncRoomCalendarJob: complete', [
            'pms'    => $this->pmsSource,
            'room'   => $this->pmsRoomId,
            'output' => Artisan::output(),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::channel('pms_webhooks')->error('SyncRoomCalendarJob: failed after retries', [
            'pms'   => $this->pmsSource,
            'room'  => $this->pmsRoomId,
            'error' => $e->getMessage(),
        ]);
    }
}

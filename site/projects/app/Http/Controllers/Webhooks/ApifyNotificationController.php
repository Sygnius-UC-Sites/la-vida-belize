<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Scraping\ApifySettings;
use App\Services\Scraping\ScrapeOrchestrator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Apify webhook receiver. Apify POSTs JSON when an Actor run reaches a
 * terminal state (SUCCEEDED / FAILED / ABORTED / TIMED-OUT).
 *
 * Authenticity is checked two ways (both must pass when configured):
 *   - X-Webhook-Secret header matches the secret we registered with Apify
 *   - The runId in the payload matches a row in scrape_runs we own
 *
 * On success we hand off to ScrapeOrchestrator::finalise, which is idempotent.
 */
class ApifyNotificationController extends Controller
{
    public function __construct(
        private ApifySettings $settings,
        private ScrapeOrchestrator $orchestrator,
    ) {}

    public function handle(Request $request): Response
    {
        $expected = $this->settings->webhookSecret();
        if ($expected !== '') {
            $provided = (string) $request->header('X-Webhook-Secret', '');
            if (!hash_equals($expected, $provided)) {
                $this->log('warning', 'apify_webhook_secret_mismatch', ['ip' => $request->ip()]);
                return response('forbidden', 403);
            }
        }

        $payload = $request->json()->all();
        if (!is_array($payload)) {
            return response('invalid_payload', 400);
        }

        $eventType = (string) ($payload['eventType'] ?? '');
        $runId = (string) ($payload['eventData']['actorRunId'] ?? $payload['resource']['id'] ?? '');
        if ($runId === '') {
            return response('missing_run_id', 400);
        }

        $row = DB::table('scrape_runs')->where('provider_run_id', $runId)->first();
        if (!$row) {
            // Not a run we kicked off; just 200 it so Apify stops retrying.
            $this->log('info', 'apify_webhook_unknown_run', ['run_id' => $runId, 'event' => $eventType]);
            return response('ok', 200);
        }

        // Update status from the payload eagerly (orchestrator will re-fetch on finalise).
        DB::table('scrape_runs')->where('id', $row->id)->update([
            'status'     => (string) ($payload['resource']['status'] ?? $row->status),
            'updated_at' => now(),
        ]);

        if (in_array($eventType, ['ACTOR.RUN.SUCCEEDED', 'ACTOR.RUN.FAILED', 'ACTOR.RUN.ABORTED', 'ACTOR.RUN.TIMED_OUT'], true)) {
            $this->orchestrator->finalise((int) $row->id);
        }

        return response('ok', 200);
    }

    private function log(string $level, string $event, array $context = []): void
    {
        try {
            Log::channel((string) config('importer.log_channel', 'importer'))->{$level}($event, $context);
        } catch (\Throwable $e) {
            Log::{$level}("$event (fallback)", $context);
        }
    }
}

<?php

namespace App\Services\Scraping;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around the Apify v2 HTTP API. All network I/O for the scraping
 * module flows through here so tests can fake the endpoints with Http::fake().
 */
class ApifyClient
{
    public function __construct(private ApifySettings $settings) {}

    /**
     * Start an Actor run with the given input. Returns the Apify run ID.
     * https://docs.apify.com/api/v2#/reference/actors/runs-collection/run-actor
     *
     * @param array<string, mixed> $input
     * @param array<int, array{eventTypes: array<string>, requestUrl: string}> $webhooks
     * @return array{id: string, defaultDatasetId: ?string, status: string}
     */
    public function startRun(string $actorId, array $input, array $webhooks = []): array
    {
        $url = $this->base() . '/acts/' . urlencode($actorId) . '/runs';

        $body = $input;
        $resp = $this->http()
            ->withQueryParameters(['token' => $this->settings->apiToken()])
            ->withHeaders($webhooks ? ['X-Apify-Webhooks' => base64_encode(json_encode($webhooks))] : [])
            ->post($url, $body);

        $resp->throw();
        $data = (array) ($resp->json('data') ?? []);

        return [
            'id'                => (string) ($data['id'] ?? ''),
            'defaultDatasetId'  => $data['defaultDatasetId'] ?? null,
            'status'            => (string) ($data['status'] ?? 'READY'),
        ];
    }

    /** @return array{id: string, status: string, statusMessage: ?string, exitCode: ?int, defaultDatasetId: ?string, finishedAt: ?string, actId: ?string} */
    public function getRun(string $runId): array
    {
        $resp = $this->http()
            ->withQueryParameters(['token' => $this->settings->apiToken()])
            ->get($this->base() . '/actor-runs/' . urlencode($runId));

        $resp->throw();
        $data = (array) ($resp->json('data') ?? []);

        return [
            'id'               => (string) ($data['id'] ?? ''),
            'status'           => (string) ($data['status'] ?? ''),
            'statusMessage'    => $data['statusMessage'] ?? null,
            'exitCode'         => isset($data['exitCode']) ? (int) $data['exitCode'] : null,
            'defaultDatasetId' => $data['defaultDatasetId'] ?? null,
            'finishedAt'       => $data['finishedAt'] ?? null,
            'actId'            => $data['actId'] ?? null,
        ];
    }

    /**
     * Fetch the tail of the run's log file (best-effort) — useful for surfacing
     * the actual reason behind an Apify-side FAILED status in the admin UI.
     */
    public function getRunLog(string $runId, int $maxBytes = 4000): string
    {
        try {
            $resp = $this->http()
                ->withQueryParameters(['token' => $this->settings->apiToken()])
                ->get($this->base() . '/actor-runs/' . urlencode($runId) . '/log');
            if (!$resp->successful()) return '';
            $body = (string) $resp->body();
            return strlen($body) > $maxBytes ? substr($body, -$maxBytes) : $body;
        } catch (\Throwable $e) {
            return '';
        }
    }

    /** Fetch all items from a dataset as a PHP array. @return list<array<string, mixed>> */
    public function getDatasetItems(string $datasetId): array
    {
        $resp = $this->http()
            ->withQueryParameters(['token' => $this->settings->apiToken(), 'clean' => 'true'])
            ->get($this->base() . '/datasets/' . urlencode($datasetId) . '/items');

        $resp->throw();
        $items = $resp->json();
        return is_array($items) ? array_values($items) : [];
    }

    private function base(): string
    {
        return rtrim((string) config('importer.api_base', 'https://api.apify.com/v2'), '/');
    }

    private function http(): PendingRequest
    {
        return Http::timeout(30)->acceptJson();
    }
}

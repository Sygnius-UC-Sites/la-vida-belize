<?php

namespace App\Services\Scraping;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Coordinates: kick-off Apify run → record state → on completion fetch dataset
 * → run mapper → upsert into properties + property_galleries.
 *
 * Two entry-points:
 *   - kickOff(scrapeSourceId): start the Apify run (admin "Import" button).
 *   - finalise(scrapeRunId): fetch dataset + write to DB (called by webhook
 *     OR by the apify:poll-runs artisan command once a RUNNING run reports
 *     SUCCEEDED).
 *
 * Re-scrape policy is FILL-BLANKS-ONLY: if the property already exists, only
 * fields that are currently null/empty get the scraped value. Photos are
 * always added (sorted after existing ones).
 */
class ScrapeOrchestrator
{
    public function __construct(
        private ApifySettings $settings,
        private ApifyClient $apify,
        private MapperFactory $mappers,
        private ImageDownloader $images,
        private ReviewImporter $reviews,
    ) {}

    /**
     * Re-run the mapper against the raw_payload that's already cached on a
     * scrape_runs row — no Apify call. Used by the admin "Re-map from cached
     * payload" button to iterate on field mapping without spending credits.
     *
     * Image download is idempotent: if the property's gallery is already
     * populated we skip download (clicking Re-map twice does NOT duplicate
     * photos). If empty (e.g. the original Import timed out mid-download),
     * we download now under the 3-minute time limit.
     */
    public function remapFromCachedPayload(int $scrapeRunId): bool
    {
        @set_time_limit(180);

        $run = DB::table('scrape_runs')->where('id', $scrapeRunId)->first();
        if (!$run || !$run->raw_payload) return false;

        $source = DB::table('scrape_sources')->where('id', $run->scrape_source_id)->first();
        if (!$source) return false;

        $first = json_decode($run->raw_payload, true);
        if (!is_array($first)) return false;

        $mapper = $this->mappers->for((string) $source->platform);
        if (!$mapper) return false;

        try {
            $mapped = $mapper->mapToProperty($first);
            $propertyId = $this->upsertProperty((int) ($source->property_id ?? 0), $mapped);

            // Download photos only if gallery is empty — skip on repeat clicks
            // so Re-map stays idempotent.
            $galleryHas = DB::table('property_galleries')->where('property_id', $propertyId)->exists();
            if (!$galleryHas && !empty($mapped['images'])) {
                $imagePaths = $this->images->downloadMany($propertyId, $mapped['images']);
                $this->appendGallery($propertyId, $imagePaths);
            }

            $this->upsertAmenityGroups($propertyId, (array) ($mapped['amenity_groups'] ?? []));
            $this->upsertHighlights($propertyId, (array) ($mapped['highlights'] ?? []));

            // Re-map also re-imports reviews (idempotent on external_id / content_hash).
            $reviewItems = $mapper->mapReviews($first);
            if (!empty($reviewItems)) {
                $this->reviews->importMany($propertyId, (string) $source->platform, $reviewItems);
            }

            DB::table('scrape_sources')->where('id', $source->id)->update([
                'status'           => 'imported',
                'property_id'      => $propertyId,
                'last_scraped_at'  => now(),
                'last_error'       => null,
                'updated_at'       => now(),
            ]);
            return true;
        } catch (\Throwable $e) {
            $this->markFailed((int) $run->scrape_source_id, 'remap_exception', $e->getMessage());
            $this->log('error', 'remap_exception', ['run_id' => $scrapeRunId, 'err' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Kick off a SEPARATE reviews-scraper run for an already-imported source.
     * The listing Actor (tri_angle/airbnb-rooms-urls-scraper) doesn't return
     * reviews, so we invoke a dedicated reviews Actor as a second Apify run.
     */
    public function kickOffReviews(int $scrapeSourceId): ?int
    {
        $source = DB::table('scrape_sources')->where('id', $scrapeSourceId)->first();
        if (!$source) return null;

        if (!$this->settings->enabled() || $this->settings->apiToken() === '') {
            $this->markFailed($scrapeSourceId, 'apify_not_configured', 'Apify is disabled or API token missing.');
            return null;
        }

        $maxReviews = $this->settings->maxReviews();
        if ($maxReviews <= 0) {
            $this->markFailed($scrapeSourceId, 'reviews_disabled', 'Set "Max reviews per listing" > 0 in Apify settings first.');
            return null;
        }

        $platform = (string) $source->platform;
        $reviewsActorId = $this->settings->reviewsActorId($platform);
        if ($reviewsActorId === '') {
            $this->markFailed($scrapeSourceId, 'no_reviews_actor', "No reviews Actor configured for '$platform'.");
            return null;
        }

        $mapper = $this->mappers->for($platform);
        if (!$mapper) {
            $this->markFailed($scrapeSourceId, 'no_mapper', "No mapper for '$platform'.");
            return null;
        }

        $input = $mapper->buildReviewsActorInput((string) $source->source_url, $maxReviews);
        if ($input === null) {
            $this->markFailed($scrapeSourceId, 'reviews_unsupported', 'This platform mapper does not support a reviews Actor.');
            return null;
        }

        $rlKey = 'apify:run-kickoff';
        if (RateLimiter::tooManyAttempts($rlKey, $this->settings->maxRunsPerMinute())) {
            $this->markFailed($scrapeSourceId, 'rate_limited', 'Per-install Apify run rate exceeded — try again in a minute.');
            return null;
        }
        RateLimiter::hit($rlKey, 60);

        try {
            $webhooks = [];
            $secret = $this->settings->webhookSecret();
            if ($secret !== '') {
                $webhooks[] = [
                    'eventTypes' => ['ACTOR.RUN.SUCCEEDED', 'ACTOR.RUN.FAILED', 'ACTOR.RUN.ABORTED', 'ACTOR.RUN.TIMED_OUT'],
                    'requestUrl' => url('webhooks/listings'),
                    'headersTemplate' => json_encode(['X-Webhook-Secret' => $secret]),
                ];
            }
            $run = $this->apify->startRun($reviewsActorId, $input, $webhooks);

            $runId = DB::table('scrape_runs')->insertGetId([
                'scrape_source_id' => $scrapeSourceId,
                'provider_run_id'     => $run['id'] ?: null,
                'provider_actor_id'   => $reviewsActorId,
                'kind'             => 'reviews',
                'provider_dataset_id' => $run['defaultDatasetId'],
                'status'           => $run['status'] ?: 'RUNNING',
                'started_at'       => now(),
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            // Auto-finalise if Apify already reports a terminal status at kickoff
            // time (fast Actors can return SUCCEEDED immediately). Otherwise
            // localhost without a webhook leaves the run stuck in SUCCEEDED+unfinalised.
            if (in_array((string) ($run['status'] ?? ''), ['SUCCEEDED', 'FAILED', 'TIMED-OUT', 'ABORTED'], true)) {
                $this->finalise($runId);
            }

            return $runId;
        } catch (\Throwable $e) {
            $this->markFailed($scrapeSourceId, 'reviews_kickoff_failed', $e->getMessage());
            $this->log('error', 'reviews_kickoff_exception', ['source_id' => $scrapeSourceId, 'err' => $e->getMessage()]);
            return null;
        }
    }

    /** Returns the new scrape_runs row id, or null on rejection (rate-limit / disabled / no token / no mapper). */
    public function kickOff(int $scrapeSourceId): ?int
    {
        $source = DB::table('scrape_sources')->where('id', $scrapeSourceId)->first();
        if (!$source) return null;

        if (!$this->settings->enabled() || $this->settings->apiToken() === '') {
            $this->markFailed($scrapeSourceId, 'apify_not_configured', 'Apify is disabled or API token missing.');
            return null;
        }

        // Per-install rate cap on Apify run kick-offs.
        $rlKey = 'apify:run-kickoff';
        if (RateLimiter::tooManyAttempts($rlKey, $this->settings->maxRunsPerMinute())) {
            $this->markFailed($scrapeSourceId, 'rate_limited', 'Per-install Apify run rate exceeded — try again in a minute.');
            return null;
        }
        RateLimiter::hit($rlKey, 60);

        $platform = (string) $source->platform;
        $mapper = $this->mappers->for($platform);
        if (!$mapper) {
            $this->markFailed($scrapeSourceId, 'no_mapper', "No mapper registered for platform '$platform'.");
            return null;
        }

        $actorId = $this->settings->actorId($platform);
        if ($actorId === '') {
            $this->markFailed($scrapeSourceId, 'no_actor', "No Apify Actor configured for platform '$platform'.");
            return null;
        }

        try {
            $webhooks = [];
            $secret = $this->settings->webhookSecret();
            if ($secret !== '') {
                $webhooks[] = [
                    'eventTypes' => ['ACTOR.RUN.SUCCEEDED', 'ACTOR.RUN.FAILED', 'ACTOR.RUN.ABORTED', 'ACTOR.RUN.TIMED_OUT'],
                    'requestUrl' => url('webhooks/listings'),
                    // Apify will sign the body with this secret using HMAC-SHA256.
                    'headersTemplate' => json_encode(['X-Webhook-Secret' => $secret]),
                ];
            }

            $run = $this->apify->startRun(
                $actorId,
                $mapper->buildActorInput((string) $source->source_url, $this->settings->maxReviews()),
                $webhooks
            );

            DB::table('scrape_sources')->where('id', $scrapeSourceId)->update([
                'status'     => 'scraping',
                'last_error' => null,
                'updated_at' => now(),
            ]);

            $runId = DB::table('scrape_runs')->insertGetId([
                'scrape_source_id' => $scrapeSourceId,
                'provider_run_id'     => $run['id'] ?: null,
                'provider_actor_id'   => $actorId,
                'provider_dataset_id' => $run['defaultDatasetId'],
                'status'           => $run['status'] ?: 'RUNNING',
                'started_at'       => now(),
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            // Auto-finalise if Apify already returned a terminal status at kickoff time.
            if (in_array((string) ($run['status'] ?? ''), ['SUCCEEDED', 'FAILED', 'TIMED-OUT', 'ABORTED'], true)) {
                $this->finalise($runId);
            }

            return $runId;
        } catch (\Throwable $e) {
            $this->markFailed($scrapeSourceId, 'kickoff_failed', $e->getMessage());
            $this->log('error', 'kickoff_exception', ['source_id' => $scrapeSourceId, 'err' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Called when Apify reports a run finished (via webhook or poll). Idempotent:
     * safe to call twice — second call is a no-op once status is terminal.
     */
    public function finalise(int $scrapeRunId): bool
    {
        // Image downloads can take 1s+ each; a 30-image listing easily blows
        // PHP's 30s default. Lift to 3 minutes for the duration of finalise.
        @set_time_limit(180);

        $run = DB::table('scrape_runs')->where('id', $scrapeRunId)->first();
        if (!$run) return false;

        // Already-finalised short-circuit. For listing runs, source.status='imported'
        // is the marker. For reviews runs, raw_payload is set when we've imported
        // its dataset. ReviewImporter is idempotent, so re-running is harmless,
        // but we skip the network round-trip when nothing is left to do.
        $isTerminal = in_array($run->status, ['SUCCEEDED', 'FAILED', 'TIMED-OUT', 'ABORTED'], true);
        if ($isTerminal) {
            if (($run->kind ?? 'listing') === 'reviews') {
                if (!empty($run->raw_payload)) {
                    return true; // already finalised
                }
            } else {
                if (DB::table('scrape_sources')->where('id', $run->scrape_source_id)->value('status') === 'imported') {
                    return true; // already finalised
                }
            }
        }

        $source = DB::table('scrape_sources')->where('id', $run->scrape_source_id)->first();
        if (!$source) return false;

        try {
            // Refresh status from Apify (webhook payload may be stale or this is a poll call).
            $live = $this->apify->getRun((string) $run->provider_run_id);
            DB::table('scrape_runs')->where('id', $scrapeRunId)->update([
                'status'           => $live['status'] ?: $run->status,
                'provider_dataset_id' => $live['defaultDatasetId'] ?? $run->provider_dataset_id,
                'finished_at'      => $live['finishedAt'] ? \Carbon\Carbon::parse($live['finishedAt']) : now(),
                'updated_at'       => now(),
            ]);

            if (($live['status'] ?? '') !== 'SUCCEEDED') {
                $statusMsg = trim((string) ($live['statusMessage'] ?? ''));
                $exitCode  = $live['exitCode'] ?? null;
                $logTail   = $this->apify->getRunLog((string) $run->provider_run_id);

                $errMsg = sprintf(
                    "Apify run did not succeed (status: %s%s%s).",
                    $live['status'] ?? 'unknown',
                    $exitCode !== null ? ", exit=$exitCode" : '',
                    $statusMsg !== '' ? " — $statusMsg" : ''
                );
                if ($logTail !== '') {
                    // Last few lines of the Apify log — usually the proximate cause.
                    $errMsg .= "\n--- last log lines ---\n" . $logTail;
                }
                $this->markFailed((int) $run->scrape_source_id, 'apify_run_not_successful', $errMsg);
                DB::table('scrape_runs')->where('id', $scrapeRunId)->update([
                    'error_message' => $errMsg, 'updated_at' => now(),
                ]);
                return false;
            }

            $datasetId = (string) ($live['defaultDatasetId'] ?? $run->provider_dataset_id);
            if ($datasetId === '') {
                $this->markFailed((int) $run->scrape_source_id, 'no_dataset', 'Apify run succeeded but no dataset id was returned.');
                return false;
            }

            $items = $this->apify->getDatasetItems($datasetId);

            $mapper = $this->mappers->for((string) $source->platform);
            if (!$mapper) {
                $this->markFailed((int) $run->scrape_source_id, 'no_mapper', 'No mapper registered for platform.');
                return false;
            }

            // Reviews-only run: each dataset item is a single review.
            if (($run->kind ?? 'listing') === 'reviews') {
                if (empty($items) || !$source->property_id) {
                    $this->markFailed((int) $run->scrape_source_id, 'empty_dataset', 'Reviews dataset was empty.');
                    return false;
                }
                DB::table('scrape_runs')->where('id', $scrapeRunId)->update([
                    'raw_payload' => json_encode($items[0] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'updated_at'  => now(),
                ]);
                $reviewItems = [];
                foreach ($items as $item) {
                    $r = $mapper->mapReviewItem((array) $item);
                    if ($r) $reviewItems[] = $r;
                }
                $imported = $this->reviews->importMany((int) $source->property_id, (string) $source->platform, $reviewItems);
                $this->log('info', 'reviews_imported', ['source_id' => $source->id, 'count' => $imported]);
                DB::table('scrape_sources')->where('id', $source->id)->update([
                    'last_scraped_at'  => now(),
                    'last_error'       => null,
                    'updated_at'       => now(),
                ]);
                return true;
            }

            $first = $items[0] ?? null;
            if (!$first) {
                $this->markFailed((int) $run->scrape_source_id, 'empty_dataset', 'Apify dataset was empty — listing may have been removed or blocked.');
                return false;
            }

            DB::table('scrape_runs')->where('id', $scrapeRunId)->update([
                'raw_payload' => json_encode($first, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'updated_at'  => now(),
            ]);

            $mapped = $mapper->mapToProperty($first);
            $propertyId = $this->upsertProperty((int) ($source->property_id ?? 0), $mapped);

            // Download images (best-effort) and append to gallery.
            $imagePaths = $this->images->downloadMany($propertyId, $mapped['images'] ?? []);
            $this->appendGallery($propertyId, $imagePaths);

            $this->upsertAmenityGroups($propertyId, (array) ($mapped['amenity_groups'] ?? []));
            $this->upsertHighlights($propertyId, (array) ($mapped['highlights'] ?? []));

            // Reviews — idempotent on (source, external_id) or (property_id, content_hash).
            $reviewItems = $mapper->mapReviews($first);
            if (!empty($reviewItems)) {
                $this->reviews->importMany($propertyId, (string) $source->platform, $reviewItems);
            }

            DB::table('scrape_sources')->where('id', $source->id)->update([
                'status'           => 'imported',
                'property_id'      => $propertyId,
                'last_scraped_at'  => now(),
                'last_error'       => null,
                'updated_at'       => now(),
            ]);

            $this->log('info', 'scrape_imported', [
                'source_id'   => $source->id,
                'property_id' => $propertyId,
                'images'      => count($imagePaths),
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->markFailed((int) $run->scrape_source_id, 'finalise_exception', $e->getMessage());
            DB::table('scrape_runs')->where('id', $scrapeRunId)->update([
                'error_message' => Str::limit($e->getMessage(), 1000, ''),
                'status'        => 'FAILED',
                'updated_at'    => now(),
            ]);
            $this->log('error', 'finalise_exception', ['run_id' => $scrapeRunId, 'err' => $e->getMessage()]);
            return false;
        }
    }

    /** Apply the mapped payload — creating or filling-blanks on an existing row. */
    private function upsertProperty(int $existingId, array $mapped): int
    {
        $payload = (array) $mapped['property'];
        $now = now();

        // feature_image is set later by appendGallery() to the LOCAL path,
        // not the original Apify URL — those URLs may rotate/expire.
        if ($existingId > 0 && DB::table('properties')->where('id', $existingId)->exists()) {
            // FILL-BLANKS only — admin's local edits win.
            $existing = (array) DB::table('properties')->where('id', $existingId)->first();
            $update = [];
            foreach ($payload as $col => $value) {
                if (!array_key_exists($col, $existing)) continue;
                $current = $existing[$col];
                if ($current === null || $current === '' || $current === '0' || $current === 0) {
                    if ($value !== null && $value !== '') {
                        $update[$col] = $value;
                    }
                }
            }
            if (!empty($mapped['amenities_raw']) && empty($existing['scraped_amenities_raw'])) {
                $update['scraped_amenities_raw'] = json_encode($mapped['amenities_raw']);
            }
            if (!empty($update)) {
                $update['updated_at'] = $now;
                DB::table('properties')->where('id', $existingId)->update($update);
            }
            return $existingId;
        }

        // Fresh property — use a unique slug based on the name.
        $slug = $this->uniqueSlug((string) ($payload['name'] ?? 'imported-listing'));
        $payload += [
            'seo_url'                => $slug,
            'status'                 => 'false',  // admin reviews before publishing
            'type'                   => 'condo',
            'instant_booking_button' => 'Book Now', // NOT NULL on properties; matches existing seeder default
            'scraped_amenities_raw'  => !empty($mapped['amenities_raw']) ? json_encode($mapped['amenities_raw']) : null,
            'created_at'             => $now,
            'updated_at'             => $now,
        ];

        // Fillable safety: only insert columns that exist on the table.
        $allowed = $this->propertiesColumns();
        $insert = array_intersect_key($payload, array_flip($allowed));

        return (int) DB::table('properties')->insertGetId($insert);
    }

    /**
     * Idempotent: only writes the structured amenity tree if the property has
     * no groups yet. Once admin has organised groups, repeat scrapes don't
     * stomp them. Admin can wipe groups manually to re-seed.
     */
    private function upsertAmenityGroups(int $propertyId, array $groups): void
    {
        if (empty($groups)) return;
        $hasExisting = DB::table('property_amenity_groups')->where('property_id', $propertyId)->exists();
        if ($hasExisting) return;

        // Card-highlight strategy: mark the first amenity in each of the first
        // N groups (N = Helper::getCardHighlightLimit(), default 6). The
        // property-detail Blade only renders highlight=1 amenities in the inline
        // list; the offcanvas drawer shows everything. This gives a varied tile
        // (one per group) instead of all-from-one-category.
        $highlightLimit = (int) (\Helper::getCardHighlightLimit() ?? 6);
        $highlighted    = 0;

        $now = now();
        foreach ($groups as $i => $group) {
            $groupId = DB::table('property_amenity_groups')->insertGetId([
                'property_id' => (string) $propertyId,
                'name'        => mb_substr((string) ($group['name'] ?? ''), 0, 200),
                'status'      => 'true',
                'sorting'     => $i,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);

            $items = (array) ($group['items'] ?? []);
            $rows = [];
            foreach ($items as $j => $item) {
                $name = (string) ($item['name'] ?? '');
                if ($name === '') continue;
                $isHighlight = ($j === 0 && $highlighted < $highlightLimit) ? 1 : 0;
                if ($isHighlight) $highlighted++;
                $rows[] = [
                    'property_amenity_id' => (string) $groupId,  // FK column despite the confusing name
                    'name'                => mb_substr($name, 0, 250),
                    'icon'                => (string) ($item['icon'] ?? ''),
                    'status'              => 'true',
                    'sorting'             => $j,
                    'is_card_highlight'   => $isHighlight,
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ];
            }
            if (!empty($rows)) {
                DB::table('property_amenities')->insert($rows);
            }
        }
    }

    /** Idempotent: skip if property already has highlight rows. */
    private function upsertHighlights(int $propertyId, array $highlights): void
    {
        if (empty($highlights)) return;
        $hasExisting = DB::table('property_highlights')->where('property_id', $propertyId)->exists();
        if ($hasExisting) return;

        $now = now();
        $rows = [];
        foreach ($highlights as $i => $h) {
            $title = (string) ($h['title'] ?? '');
            if ($title === '') continue;
            $rows[] = [
                'property_id' => (string) $propertyId,
                'title'       => mb_substr($title, 0, 250),
                'description' => mb_substr((string) ($h['subtitle'] ?? ''), 0, 1000),
                'icon'        => (string) ($h['icon'] ?? ''),
                'status'      => 'true',
                'sorting'     => $i,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }
        if (!empty($rows)) {
            DB::table('property_highlights')->insert($rows);
        }
    }

    private function appendGallery(int $propertyId, array $relativePaths): void
    {
        if (empty($relativePaths)) return;
        $existingMax = (int) DB::table('property_galleries')->where('property_id', $propertyId)->max('sorting');
        $rows = [];
        foreach ($relativePaths as $i => $rel) {
            $rows[] = [
                'property_id' => (string) $propertyId,
                'image'       => $rel,
                'status'      => 'true',
                'sorting'     => $existingMax + $i + 1,
                'caption'     => null,
                'created_at'  => now(),
                'updated_at'  => now(),
            ];
        }
        DB::table('property_galleries')->insert($rows);

        // If the property has no feature_image yet, promote the first one.
        $current = DB::table('properties')->where('id', $propertyId)->value('feature_image');
        if (($current === null || $current === '') && isset($relativePaths[0])) {
            DB::table('properties')->where('id', $propertyId)->update([
                'feature_image' => $relativePaths[0],
                'updated_at'    => now(),
            ]);
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'listing-' . substr(md5($name . microtime(true)), 0, 6);
        $slug = $base;
        $i = 2;
        while (DB::table('properties')->where('seo_url', $slug)->exists()) {
            $slug = $base . '-' . $i++;
            if ($i > 50) {
                $slug = $base . '-' . substr(md5(microtime(true)), 0, 6);
                break;
            }
        }
        return $slug;
    }

    /** @return list<string> */
    private function propertiesColumns(): array
    {
        static $cache = null;
        if ($cache === null) {
            try {
                $cache = \Illuminate\Support\Facades\Schema::getColumnListing('properties');
            } catch (\Throwable $e) {
                $cache = [];
            }
        }
        return $cache;
    }

    private function markFailed(int $scrapeSourceId, string $code, string $message): void
    {
        DB::table('scrape_sources')->where('id', $scrapeSourceId)->update([
            'status'     => 'failed',
            'last_error' => "[$code] " . Str::limit($message, 800, ''),
            'updated_at' => now(),
        ]);
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

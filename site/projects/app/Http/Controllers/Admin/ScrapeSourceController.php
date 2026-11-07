<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Scraping\ApifyClient;
use App\Services\Scraping\ApifySettings;
use App\Services\Scraping\ScrapeOrchestrator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScrapeSourceController extends Controller
{
    public function __construct(
        private ApifySettings $settings,
        private ScrapeOrchestrator $orchestrator,
    ) {}

    public function index()
    {
        $sources = DB::table('scrape_sources as s')
            ->leftJoin('properties as p', 'p.id', '=', 's.property_id')
            ->orderByDesc('s.id')
            ->get([
                's.*',
                'p.name as property_name',
                'p.seo_url as property_seo_url',
                'p.type as property_type',
            ]);

        $latestRuns = DB::table('scrape_runs')
            ->select('scrape_source_id', DB::raw('MAX(id) as last_run_id'))
            ->groupBy('scrape_source_id')
            ->pluck('last_run_id', 'scrape_source_id')
            ->toArray();

        $runsById = DB::table('scrape_runs')
            ->whereIn('id', array_values($latestRuns))
            ->get(['id', 'scrape_source_id', 'status', 'error_message', 'started_at', 'finished_at', 'provider_run_id', 'kind'])
            ->keyBy('scrape_source_id');

        // Latest reviews-kind run per source (independent of latest overall run).
        // Used to surface "reviews scraping" state even when source.status='imported'.
        $latestReviewsRunIds = DB::table('scrape_runs')
            ->where('kind', 'reviews')
            ->select('scrape_source_id', DB::raw('MAX(id) as last_id'))
            ->groupBy('scrape_source_id')
            ->pluck('last_id', 'scrape_source_id')
            ->toArray();
        $reviewsRunsById = DB::table('scrape_runs')
            ->whereIn('id', array_values($latestReviewsRunIds))
            ->get(['id', 'scrape_source_id', 'status', 'provider_run_id', 'error_message', 'finished_at'])
            ->keyBy('scrape_source_id');

        $values = [
            'importer_enabled'              => $this->settings->enabled(),
            'importer_max_runs_per_minute'  => $this->settings->maxRunsPerMinute(),
            'importer_max_reviews'          => $this->settings->maxReviews(),
            'importer_webhook_secret_set'   => $this->settings->webhookSecret() !== '',
            'importer_token_set'            => $this->settings->apiToken() !== '',
            'importer_token_corrupt'        => $this->settings->tokenIsCorrupt(),
        ];

        // Review counts per source (joined via property_id).
        $reviewCounts = DB::table('property_reviews')
            ->select('property_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('property_id')
            ->pluck('cnt', 'property_id')
            ->toArray();

        $webhookUrl = url('webhooks/listings');

        return view('admin.scrape-sources.index', compact('sources', 'runsById', 'reviewsRunsById', 'values', 'webhookUrl', 'reviewCounts'));
    }

    /** Browse imported reviews for one property. Bulk publish / hide / delete. */
    public function reviews(int $sourceId)
    {
        $source = DB::table('scrape_sources')->find($sourceId);
        if (!$source || !$source->property_id) {
            return redirect()->route('admin.scrape-sources.index')->with('danger', 'No property linked to this source yet.');
        }
        $property = DB::table('properties')->find($source->property_id);
        $reviews = DB::table('property_reviews')
            ->where('property_id', $source->property_id)
            ->orderByDesc('review_date')
            ->orderByDesc('id')
            ->get();
        return view('admin.scrape-sources.reviews', compact('source', 'property', 'reviews'));
    }

    public function reviewsBulk(Request $request, int $sourceId)
    {
        $source = DB::table('scrape_sources')->find($sourceId);
        if (!$source || !$source->property_id) {
            return redirect()->route('admin.scrape-sources.index')->with('danger', 'No property linked.');
        }

        $action = (string) $request->input('action');
        $ids = array_map('intval', (array) $request->input('ids', []));

        if (empty($ids)) {
            return redirect()->route('admin.scrape-sources.reviews', $sourceId)->with('danger', 'Select at least one review.');
        }

        $q = DB::table('property_reviews')
            ->whereIn('id', $ids)
            ->where('property_id', $source->property_id);

        switch ($action) {
            case 'publish': $q->update(['status' => 'published', 'updated_at' => now()]); $msg = count($ids) . ' review(s) published.'; break;
            case 'hide':    $q->update(['status' => 'hidden',    'updated_at' => now()]); $msg = count($ids) . ' review(s) hidden.'; break;
            case 'reimport':$q->update(['status' => 'imported',  'updated_at' => now()]); $msg = count($ids) . ' review(s) reset to imported.'; break;
            case 'delete':  $q->delete(); $msg = count($ids) . ' review(s) deleted.'; break;
            default:        return redirect()->route('admin.scrape-sources.reviews', $sourceId)->with('danger', 'Unknown action.');
        }

        return redirect()->route('admin.scrape-sources.reviews', $sourceId)->with('success', $msg);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'urls'     => 'required|string',
            'platform' => 'required|in:airbnb,vrbo,booking,other',
            'notes'    => 'nullable|string|max:2000',
        ]);

        $urls = array_values(array_unique(array_filter(array_map('trim', preg_split('/[\r\n]+/', $validated['urls'])))));
        $count = 0;
        $skipped = 0;

        foreach ($urls as $url) {
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                $skipped++;
                continue;
            }
            // Skip dupes within the same platform.
            $exists = DB::table('scrape_sources')
                ->where('source_url', $url)
                ->where('platform', $validated['platform'])
                ->exists();
            if ($exists) {
                $skipped++;
                continue;
            }
            DB::table('scrape_sources')->insert([
                'source_url' => $url,
                'platform'   => $validated['platform'],
                'notes'      => $validated['notes'] ?? null,
                'status'     => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $count++;
        }

        $msg = "$count source(s) added";
        if ($skipped > 0) $msg .= " ($skipped skipped — duplicate or invalid URL)";
        return redirect()->route('admin.scrape-sources.index')->with('success', $msg);
    }

    public function destroy(int $id)
    {
        DB::table('scrape_sources')->where('id', $id)->delete();
        return redirect()->route('admin.scrape-sources.index')->with('success', 'Source removed.');
    }

    /** Trigger a fresh Apify run for this source. */
    public function scrape(int $id)
    {
        $runId = $this->orchestrator->kickOff($id);
        if (!$runId) {
            return redirect()->route('admin.scrape-sources.index')
                ->with('danger', 'Could not start scrape — see "Last error" on the row for details.');
        }
        return redirect()->route('admin.scrape-sources.index')
            ->with('success', "Scrape kicked off (run #$runId). It runs on Apify and may take a couple of minutes.");
    }

    /**
     * Kick off the dedicated reviews Actor (separate from the listing Actor).
     * Listing scraper doesn't return reviews; this fetches them and imports
     * via ReviewImporter when the run completes.
     */
    public function scrapeReviews(int $id)
    {
        $runId = $this->orchestrator->kickOffReviews($id);
        if (!$runId) {
            return redirect()->route('admin.scrape-sources.index')
                ->with('danger', 'Could not start reviews scrape — see "Last error" on the row.');
        }
        return redirect()->route('admin.scrape-sources.index')
            ->with('success', "Reviews scrape kicked off (run #$runId). Different Actor than the listing scrape; will populate property_reviews when done.");
    }

    /** Poll one run synchronously (manual fallback when no public webhook URL). */
    public function checkStatus(int $id, ApifyClient $client)
    {
        $row = DB::table('scrape_runs')
            ->where('scrape_source_id', $id)
            ->orderByDesc('id')->first();
        if (!$row || !$row->provider_run_id) {
            return redirect()->route('admin.scrape-sources.index')->with('danger', 'No run to check.');
        }

        try {
            $live = $client->getRun((string) $row->provider_run_id);
            $status = (string) ($live['status'] ?? '');

            DB::table('scrape_runs')->where('id', $row->id)->update([
                'status' => $status ?: $row->status,
                'provider_dataset_id' => $live['defaultDatasetId'] ?? $row->provider_dataset_id,
                'updated_at' => now(),
            ]);

            if (in_array($status, ['SUCCEEDED', 'FAILED', 'TIMED-OUT', 'ABORTED'], true)) {
                $this->orchestrator->finalise((int) $row->id);
                return redirect()->route('admin.scrape-sources.index')->with('success', "Run finalised (status: $status).");
            }
            return redirect()->route('admin.scrape-sources.index')->with('success', "Run still in progress (status: $status). Try again in a moment.");
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            // Apify returns HTTP 403 with type=insufficient-permissions when a
            // Restricted API Token can run actors but lacks `actor-run:read` /
            // `dataset:read`. Surface that explicitly — generic "403" is unhelpful.
            if (str_contains($msg, 'status code 403') && str_contains($msg, 'insufficient-permissions')) {
                $msg = 'Apify rejected the read with HTTP 403 (insufficient-permissions). Your token can START runs but can\'t READ them — it\'s a Restricted API Token missing the actor-run:read and dataset:read scopes. Use a Personal API Token from console.apify.com → Settings → Integrations → "Personal API tokens", paste it into Settings, Save, then Re-scrape.';
            }
            return redirect()->route('admin.scrape-sources.index')->with('danger', 'Status check failed: ' . $msg);
        }
    }

    /** Show the raw JSON dataset item Apify returned for the latest run on this source. */
    public function viewRaw(int $id)
    {
        $run = DB::table('scrape_runs')
            ->where('scrape_source_id', $id)
            ->whereNotNull('raw_payload')
            ->orderByDesc('id')
            ->first();
        if (!$run) {
            return redirect()->route('admin.scrape-sources.index')->with('danger', 'No cached payload found for this source. Run an import first.');
        }
        $source = DB::table('scrape_sources')->find($id);
        $payload = json_decode($run->raw_payload, true);
        $pretty = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return view('admin.scrape-sources.raw', compact('source', 'run', 'pretty'));
    }

    /** Re-run the mapper against cached payload — no new Apify run. Used to iterate on field-mapping without burning credits. */
    public function remap(int $id)
    {
        $run = DB::table('scrape_runs')
            ->where('scrape_source_id', $id)
            ->whereNotNull('raw_payload')
            ->orderByDesc('id')
            ->first();
        if (!$run) {
            return redirect()->route('admin.scrape-sources.index')->with('danger', 'No cached payload to re-map. Run an import first.');
        }
        $ok = $this->orchestrator->remapFromCachedPayload((int) $run->id);
        if (!$ok) {
            return redirect()->route('admin.scrape-sources.index')->with('danger', 'Re-map failed — see "Last error" on the row.');
        }
        return redirect()->route('admin.scrape-sources.index')->with('success', 'Re-mapped from cached payload (no Apify call). Open the property to verify fields.');
    }

    /** Quick auth check — calls Apify's /users/me to verify the token round-trips. */
    public function testConnection(\App\Services\Scraping\ApifyClient $client)
    {
        $token = $this->settings->apiToken();
        if ($token === '') {
            return redirect()->route('admin.scrape-sources.index')
                ->with('danger', 'No Apify API token resolved. Either basic_settings is missing the row, or the stored token cannot be decrypted (APP_KEY mismatch). Re-enter the token in Settings → Save.');
        }
        try {
            $resp = \Illuminate\Support\Facades\Http::timeout(10)
                ->acceptJson()
                ->withQueryParameters(['token' => $token])
                ->get(rtrim((string) config('importer.api_base'), '/') . '/users/me');

            if ($resp->status() === 401 || $resp->status() === 403) {
                return redirect()->route('admin.scrape-sources.index')
                    ->with('danger', 'Apify rejected the token (HTTP ' . $resp->status() . '). Token resolved fine on our side but Apify says it is invalid — re-check the value on console.apify.com → Settings → Integrations.');
            }
            if (!$resp->successful()) {
                return redirect()->route('admin.scrape-sources.index')
                    ->with('danger', 'Apify reachability test failed: HTTP ' . $resp->status());
            }
            $username = (string) ($resp->json('data.username') ?? '');
            return redirect()->route('admin.scrape-sources.index')
                ->with('success', 'Apify connection OK. Authenticated as: ' . ($username ?: 'unknown user'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.scrape-sources.index')
                ->with('danger', 'Apify test threw: ' . $e->getMessage());
        }
    }

    /* ============== Settings sub-page ============== */

    public function settingsUpdate(Request $request)
    {
        $validated = $request->validate([
            'importer_enabled'             => 'nullable|boolean',
            'importer_api_token'           => 'nullable|string|max:255',
            // Actor IDs intentionally not validated/saved from form — they're
            // env-only so they don't show in the admin UI or settings DB.
            'importer_max_runs_per_minute' => 'nullable|integer|min:1|max:1000',
            'importer_max_reviews'         => 'nullable|integer|min:0|max:5000',
            'importer_webhook_secret'      => 'nullable|string|max:255',
        ]);

        // Persist non-sensitive settings.
        foreach (['importer_enabled', 'importer_max_runs_per_minute', 'importer_max_reviews', 'importer_webhook_secret'] as $key) {
            if ($key === 'importer_enabled') {
                $value = $request->boolean($key) ? '1' : '0';
            } else {
                $value = (string) ($validated[$key] ?? '');
            }
            DB::table('basic_settings')->updateOrInsert(['name' => $key], ['value' => $value, 'updated_at' => now()]);
        }

        // Token only updates when admin types a new value (empty leaves existing in place).
        $newToken = (string) ($request->input('importer_api_token') ?? '');
        if ($newToken !== '') {
            $this->settings->setEncryptedToken($newToken);
        }
        $this->settings->refresh();

        return redirect()->route('admin.scrape-sources.index')->with('success', 'Apify settings updated.');
    }
}

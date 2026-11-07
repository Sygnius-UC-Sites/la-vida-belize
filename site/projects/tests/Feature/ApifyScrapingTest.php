<?php

namespace Tests\Feature;

use App\Services\Scraping\ApifySettings;
use App\Services\Scraping\Mappers\AirbnbMapper;
use App\Services\Scraping\ReviewImporter;
use App\Services\Scraping\ScrapeOrchestrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ApifyScrapingTest extends TestCase
{
    private array $createdPropertyIds = [];
    private array $createdSourceIds = [];

    /**
     * Settings keys this test class touches. Restricted to a fixed list so
     * test runs don't wipe an admin's live `apify_api_token` row stored via
     * the UI — a real production token is encrypted differently than our
     * synthetic fixtures, so over-broad `like 'apify_%'` deletes can wipe
     * legitimate data on a shared dev DB. Snapshot+restore around each test
     * is the safe pattern.
     */
    private const TOUCHED_KEYS = [
        'importer_api_token', 'importer_actor_airbnb', 'importer_actor_airbnb_reviews',
        'importer_max_runs_per_minute', 'importer_webhook_secret', 'importer_enabled',
        'importer_max_reviews',
    ];
    private array $settingsSnapshot = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Snapshot whatever the admin had before this test ran so we can restore in tearDown.
        $this->settingsSnapshot = DB::table('basic_settings')
            ->whereIn('name', self::TOUCHED_KEYS)
            ->get(['name', 'value', 'created_at', 'updated_at'])
            ->keyBy('name')->toArray();

        DB::table('basic_settings')->whereIn('name', self::TOUCHED_KEYS)->delete();
        DB::table('basic_settings')->updateOrInsert(['name' => 'importer_enabled'],
            ['value' => 'true', 'updated_at' => now()]);
        DB::table('basic_settings')->updateOrInsert(['name' => 'importer_api_token'],
            ['value' => \Illuminate\Support\Facades\Crypt::encryptString('test-token'), 'updated_at' => now()]);
        DB::table('basic_settings')->updateOrInsert(['name' => 'importer_actor_airbnb'],
            ['value' => 'tri_angle~airbnb-scraper', 'updated_at' => now()]);
        app(ApifySettings::class)->refresh();
        RateLimiter::clear('apify:run-kickoff');
    }

    protected function tearDown(): void
    {
        if ($this->createdSourceIds) {
            $runIds = DB::table('scrape_runs')->whereIn('scrape_source_id', $this->createdSourceIds)->pluck('id');
            DB::table('scrape_runs')->whereIn('id', $runIds)->delete();
            DB::table('scrape_sources')->whereIn('id', $this->createdSourceIds)->delete();
        }
        if ($this->createdPropertyIds) {
            DB::table('property_galleries')->whereIn('property_id', $this->createdPropertyIds)->delete();
            DB::table('properties')->whereIn('id', $this->createdPropertyIds)->delete();
        }

        // Restore admin's settings exactly as they were before this test ran.
        DB::table('basic_settings')->whereIn('name', self::TOUCHED_KEYS)->delete();
        foreach ($this->settingsSnapshot as $name => $row) {
            DB::table('basic_settings')->insert([
                'name'       => $name,
                'value'      => $row->value,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
            ]);
        }
        app(ApifySettings::class)->refresh();
        parent::tearDown();
    }

    private function makeSource(string $url = 'https://www.airbnb.com/rooms/12345', string $platform = 'airbnb'): int
    {
        $id = DB::table('scrape_sources')->insertGetId([
            'source_url' => $url,
            'platform'   => $platform,
            'status'     => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->createdSourceIds[] = $id;
        return $id;
    }

    /* ============== Mapper unit ============== */

    public function test_airbnb_mapper_extracts_core_fields(): void
    {
        $item = [
            'name' => 'Cosy 2BR cabin',
            'description' => 'A lovely lakeside cabin with great views.',
            'address' => 'Lake Tahoe, CA',
            'personCapacity' => 4,
            'bedrooms' => 2, 'bathrooms' => 1, 'beds' => 3,
            'price' => ['rate' => 199],
            'photos' => [
                ['large' => 'https://images.example/a.jpg'],
                ['picture' => 'https://images.example/b.jpg'],
                'https://images.example/c.jpg',
            ],
            'amenities' => [['title' => 'Wifi'], ['title' => 'Kitchen'], 'Hot tub'],
        ];

        $mapped = (new AirbnbMapper())->mapToProperty($item);

        $this->assertSame('Cosy 2BR cabin', $mapped['property']['name']);
        $this->assertSame(2, $mapped['property']['bedroom']);
        $this->assertSame(4, $mapped['property']['no_of_guest']);
        $this->assertSame(199.0, $mapped['property']['standard_rate']);
        $this->assertCount(3, $mapped['images']);
        $this->assertEqualsCanonicalizing(['Wifi', 'Kitchen', 'Hot tub'], $mapped['amenities_raw']);
    }

    /* ============== Review extraction + idempotent import ============== */

    public function test_airbnb_mapper_extracts_reviews_with_multiple_shapes(): void
    {
        $item = [
            'reviews' => [
                [
                    'id' => 'rev-1',
                    'comments' => 'Great stay, loved it!',
                    'rating' => 5,
                    'language' => 'en',
                    'createdAt' => '2025-08-15T10:00:00Z',
                    'reviewer' => ['firstName' => 'Alice', 'pictureUrl' => 'https://img.example/a.jpg', 'location' => 'Paris'],
                    'response' => 'Thanks Alice!',
                    'responseCreatedAt' => '2025-08-16T10:00:00Z',
                ],
                [
                    // No external id; comments under different key.
                    'comment' => 'Quiet and clean.',
                    'stars' => 4,
                    'submittedAt' => '3 months ago',
                    'reviewer' => ['name' => 'Bob'],
                ],
                ['comments' => '', 'rating' => null], // empty — should be skipped
            ],
        ];

        $reviews = (new AirbnbMapper())->mapReviews($item);
        $this->assertCount(2, $reviews);

        $this->assertSame('rev-1', $reviews[0]['external_id']);
        $this->assertSame('Alice', $reviews[0]['reviewer_name']);
        $this->assertSame(5, $reviews[0]['rating']);
        $this->assertSame('Great stay, loved it!', $reviews[0]['comment']);
        $this->assertSame('Thanks Alice!', $reviews[0]['response_text']);
        $this->assertSame('Paris', $reviews[0]['reviewer_location']);

        $this->assertArrayNotHasKey('external_id', $reviews[1]);
        $this->assertSame('Bob', $reviews[1]['reviewer_name']);
        $this->assertSame(4, $reviews[1]['rating']);
    }

    public function test_review_importer_is_idempotent_on_external_id(): void
    {
        $propertyId = DB::table('properties')->insertGetId([
            'name' => 'IdempReview Test', 'seo_url' => 'idemp-rev-' . uniqid(),
            'status' => 'true', 'type' => 'condo', 'instant_booking_button' => 'Book Now',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->createdPropertyIds[] = $propertyId;

        $reviews = [
            ['external_id' => 'r-1', 'reviewer_name' => 'Alice', 'comment' => 'Great', 'rating' => 5],
            ['external_id' => 'r-2', 'reviewer_name' => 'Bob',   'comment' => 'Good',  'rating' => 4],
        ];

        $first = app(ReviewImporter::class)->importMany($propertyId, 'airbnb', $reviews);
        $this->assertSame(2, $first);

        // Re-import same payload — no new rows.
        $second = app(ReviewImporter::class)->importMany($propertyId, 'airbnb', $reviews);
        $this->assertSame(0, $second);

        $count = DB::table('property_reviews')->where('property_id', $propertyId)->count();
        $this->assertSame(2, $count);

        DB::table('property_reviews')->where('property_id', $propertyId)->delete();
    }

    public function test_review_importer_falls_back_to_content_hash_when_no_external_id(): void
    {
        $propertyId = DB::table('properties')->insertGetId([
            'name' => 'HashDedup Test', 'seo_url' => 'hash-rev-' . uniqid(),
            'status' => 'true', 'type' => 'condo', 'instant_booking_button' => 'Book Now',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->createdPropertyIds[] = $propertyId;

        $reviews = [
            ['reviewer_name' => 'Charlie', 'comment' => 'Lovely place', 'rating' => 5],
        ];

        $first = app(ReviewImporter::class)->importMany($propertyId, 'airbnb', $reviews);
        $this->assertSame(1, $first);

        // Same content → dedup via content_hash.
        $second = app(ReviewImporter::class)->importMany($propertyId, 'airbnb', $reviews);
        $this->assertSame(0, $second);

        // Different content → new row.
        $third = app(ReviewImporter::class)->importMany($propertyId, 'airbnb', [
            ['reviewer_name' => 'Charlie', 'comment' => 'Different note', 'rating' => 5],
        ]);
        $this->assertSame(1, $third);

        $this->assertSame(2, DB::table('property_reviews')->where('property_id', $propertyId)->count());
        DB::table('property_reviews')->where('property_id', $propertyId)->delete();
    }

    public function test_finalise_imports_reviews_when_present_in_payload(): void
    {
        Http::fake([
            'api.apify.com/v2/acts/*'              => Http::response(['data' => ['id' => 'r-rev', 'defaultDatasetId' => 'd-rev', 'status' => 'RUNNING']], 201),
            'api.apify.com/v2/actor-runs/r-rev*'   => Http::response(['data' => ['id' => 'r-rev', 'status' => 'SUCCEEDED', 'defaultDatasetId' => 'd-rev', 'finishedAt' => now()->toIso8601String()]], 200),
            'api.apify.com/v2/datasets/d-rev/items*' => Http::response([[
                'name' => 'Review Test ' . uniqid(),
                'description' => 'x',
                'bedrooms' => 2,
                'reviews' => [
                    ['id' => 'ext-1', 'comments' => 'Wonderful', 'rating' => 5, 'reviewer' => ['firstName' => 'Alice']],
                    ['id' => 'ext-2', 'comments' => 'Average', 'rating' => 3, 'reviewer' => ['firstName' => 'Bob']],
                ],
            ]], 200),
        ]);

        $sourceId = $this->makeSource('https://www.airbnb.com/rooms/rev-test');
        $runId = app(ScrapeOrchestrator::class)->kickOff($sourceId);
        $this->assertNotNull($runId);

        $this->assertTrue(app(ScrapeOrchestrator::class)->finalise($runId));

        $source = DB::table('scrape_sources')->find($sourceId);
        $this->assertNotNull($source->property_id);
        $this->createdPropertyIds[] = (int) $source->property_id;

        $reviews = DB::table('property_reviews')->where('property_id', $source->property_id)->get();
        $this->assertCount(2, $reviews);
        $this->assertEqualsCanonicalizing(['ext-1', 'ext-2'], $reviews->pluck('external_review_id')->toArray());

        DB::table('property_reviews')->where('property_id', $source->property_id)->delete();
    }

    public function test_airbnb_mapper_extracts_grouped_amenities_highlights_and_house_rules(): void
    {
        $item = [
            'title' => 'Big Villa',
            'description' => 'Test.',
            'locationSubtitle' => 'Some Town, CR',
            'personCapacity' => 8,
            'subDescription' => ['items' => ['8 guests', '4 bedrooms', '5 beds', '3 baths']],
            'coordinates' => ['latitude' => 9.5, 'longitude' => -84.4],
            'cancellationPolicies' => [['policyName' => 'Firm', 'policyId' => 62]],
            'highlights' => [
                ['title' => 'Self check-in', 'subtitle' => 'Keypad', 'icon' => 'SYSTEM_CHECK_IN', 'type' => 'X'],
                ['title' => 'Pool', 'subtitle' => '', 'icon' => 'SYSTEM_POOL', 'type' => 'Y'],
            ],
            'houseRules' => [
                'general' => [
                    ['title' => 'Checking in and out', 'values' => [
                        ['title' => 'Check-in after 3:00 PM', 'icon' => 'X', 'additionalInfo' => null],
                        ['title' => 'Checkout before 10:00 AM', 'icon' => 'X', 'additionalInfo' => null],
                    ]],
                    ['title' => 'During your stay', 'values' => [
                        ['title' => 'Pets allowed', 'icon' => 'X', 'additionalInfo' => null],
                        ['title' => 'Smoking is allowed', 'icon' => 'X', 'additionalInfo' => null],
                    ]],
                ],
                'additional' => null,
            ],
            'amenities' => [
                ['title' => 'Bathroom', 'values' => [
                    ['title' => 'Bathtub', 'icon' => 'SYSTEM_BATHTUB', 'available' => true],
                    ['title' => 'Hair dryer', 'icon' => 'SYSTEM_HAIRDRYER', 'available' => true],
                ]],
                ['title' => 'Kitchen', 'values' => [
                    ['title' => 'Refrigerator', 'icon' => 'SYSTEM_REFRIGERATOR', 'available' => true],
                ]],
                ['title' => 'Not included', 'values' => [
                    ['title' => 'Heating', 'icon' => 'X', 'available' => false],
                ]],
            ],
        ];

        $mapped = (new AirbnbMapper())->mapToProperty($item);

        // Property scalars
        $this->assertSame(4, $mapped['property']['bedroom']);
        $this->assertSame(3, $mapped['property']['bathroom']);
        $this->assertSame(8, $mapped['property']['no_of_guest']);
        $this->assertSame('3:00 PM', $mapped['property']['checkin']);
        $this->assertSame('10:00 AM', $mapped['property']['checkout']);
        $this->assertSame(1, $mapped['property']['accepts_pets']);
        $this->assertSame(1, $mapped['property']['smoking_allowed']);
        $this->assertSame('firm', $mapped['property']['cancellation_policy_type']);
        // map = bare URL (Blade wraps it in <iframe src="...">).
        $this->assertStringContainsString('maps.google.com/maps?q=9.5,-84.4', $mapped['property']['map']);
        $this->assertStringNotContainsString('<iframe', $mapped['property']['map']);
        // booking_policy is plain text now (the front-end Blades render with {{ }}).
        $this->assertStringContainsString('CHECKING IN AND OUT', $mapped['property']['booking_policy']);
        $this->assertStringNotContainsString('<h4>', $mapped['property']['booking_policy']);

        // Grouped amenities — preserved structure, "Not included" dropped
        $groupNames = array_column($mapped['amenity_groups'], 'name');
        $this->assertContains('Bathroom', $groupNames);
        $this->assertContains('Kitchen', $groupNames);
        $this->assertNotContains('Not included', $groupNames);

        $bathroomItems = array_column($mapped['amenity_groups'][0]['items'], 'name');
        $this->assertEqualsCanonicalizing(['Bathtub', 'Hair dryer'], $bathroomItems);
        $this->assertSame('SYSTEM_BATHTUB', $mapped['amenity_groups'][0]['items'][0]['icon']);

        // Highlights
        $this->assertCount(2, $mapped['highlights']);
        $this->assertSame('Self check-in', $mapped['highlights'][0]['title']);
        $this->assertSame('Keypad', $mapped['highlights'][0]['subtitle']);
        $this->assertSame('SYSTEM_CHECK_IN', $mapped['highlights'][0]['icon']);
    }

    public function test_airbnb_mapper_handles_missing_fields_gracefully(): void
    {
        $mapped = (new AirbnbMapper())->mapToProperty([]);
        // Empty input — defensive code path. No exception, sensible defaults.
        $this->assertNotEmpty($mapped['property']['name']);
        $this->assertSame(0, $mapped['property']['bedroom']);
        // standard_rate gets array_filter'd out when null (orchestrator skips
        // missing keys, equivalent to leaving the column unchanged on insert).
        $this->assertNull($mapped['property']['standard_rate'] ?? null);
        $this->assertSame([], $mapped['images']);
    }

    /* ============== Orchestrator: kick-off ============== */

    public function test_kickoff_starts_apify_run_and_creates_run_row(): void
    {
        Http::fake([
            'api.apify.com/v2/acts/*' => Http::response(['data' => [
                'id' => 'apify-run-1', 'defaultDatasetId' => 'dataset-1', 'status' => 'RUNNING',
            ]], 201),
        ]);

        $sourceId = $this->makeSource();
        $runId = app(ScrapeOrchestrator::class)->kickOff($sourceId);

        $this->assertNotNull($runId);
        $run = DB::table('scrape_runs')->find($runId);
        $this->assertSame('apify-run-1', $run->provider_run_id);
        $this->assertSame('dataset-1', $run->provider_dataset_id);
        $this->assertSame('RUNNING', $run->status);

        $source = DB::table('scrape_sources')->find($sourceId);
        $this->assertSame('scraping', $source->status);
    }

    public function test_kickoff_rejects_when_no_token_set(): void
    {
        DB::table('basic_settings')->where('name', 'importer_api_token')->delete();
        app(ApifySettings::class)->refresh();
        config(['apify.api_token' => '']);

        $sourceId = $this->makeSource();
        $runId = app(ScrapeOrchestrator::class)->kickOff($sourceId);

        $this->assertNull($runId);
        $source = DB::table('scrape_sources')->find($sourceId);
        $this->assertSame('failed', $source->status);
        $this->assertStringContainsString('apify_not_configured', (string) $source->last_error);
    }

    public function test_kickoff_respects_per_install_rate_limit(): void
    {
        DB::table('basic_settings')->updateOrInsert(['name' => 'importer_max_runs_per_minute'],
            ['value' => '2', 'updated_at' => now()]);
        app(ApifySettings::class)->refresh();

        Http::fake([
            'api.apify.com/v2/acts/*' => Http::sequence()
                ->push(['data' => ['id' => 'r-rl-1', 'defaultDatasetId' => 'd-rl-1', 'status' => 'RUNNING']], 201)
                ->push(['data' => ['id' => 'r-rl-2', 'defaultDatasetId' => 'd-rl-2', 'status' => 'RUNNING']], 201)
                ->push(['data' => ['id' => 'r-rl-3', 'defaultDatasetId' => 'd-rl-3', 'status' => 'RUNNING']], 201),
        ]);

        $a = $this->makeSource('https://www.airbnb.com/rooms/1');
        $b = $this->makeSource('https://www.airbnb.com/rooms/2');
        $c = $this->makeSource('https://www.airbnb.com/rooms/3');

        $this->assertNotNull(app(ScrapeOrchestrator::class)->kickOff($a));
        $this->assertNotNull(app(ScrapeOrchestrator::class)->kickOff($b));
        $this->assertNull(app(ScrapeOrchestrator::class)->kickOff($c));

        $cRow = DB::table('scrape_sources')->find($c);
        $this->assertSame('failed', $cRow->status);
        $this->assertStringContainsString('rate_limited', (string) $cRow->last_error);
    }

    /* ============== Orchestrator: finalise ============== */

    public function test_finalise_creates_property_and_gallery_on_success(): void
    {
        // 1. kick off a run via faked Apify
        Http::fake([
            'api.apify.com/v2/acts/*'                          => Http::response(['data' => ['id' => 'r-1', 'defaultDatasetId' => 'd-1', 'status' => 'RUNNING']], 201),
            'api.apify.com/v2/actor-runs/r-1*'                 => Http::response(['data' => ['id' => 'r-1', 'status' => 'SUCCEEDED', 'defaultDatasetId' => 'd-1', 'finishedAt' => now()->toIso8601String()]], 200),
            'api.apify.com/v2/datasets/d-1/items*'             => Http::response([[
                'name' => 'Apify Test Cabin ' . uniqid(),
                'description' => 'Test description',
                'address' => 'Tahoe',
                'personCapacity' => 4,
                'bedrooms' => 2, 'bathrooms' => 1,
                'price' => ['rate' => 175],
                'photos' => [['large' => 'https://images.example/test1.jpg']],
                'amenities' => [['title' => 'Wifi']],
            ]], 200),
            'images.example/*' => Http::response('FAKE_JPEG_BYTES', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $sourceId = $this->makeSource();
        $runId = app(ScrapeOrchestrator::class)->kickOff($sourceId);
        $this->assertNotNull($runId);

        $ok = app(ScrapeOrchestrator::class)->finalise($runId);
        $sourceAfter = DB::table('scrape_sources')->find($sourceId);
        $runAfter = DB::table('scrape_runs')->find($runId);
        $this->assertTrue($ok, 'finalise returned false. last_error=' . ($sourceAfter->last_error ?? 'none')
            . ' run.error=' . ($runAfter->error_message ?? 'none')
            . ' run.status=' . ($runAfter->status ?? 'none'));

        $source = DB::table('scrape_sources')->find($sourceId);
        $this->assertSame('imported', $source->status);
        $this->assertNotNull($source->property_id);

        $this->createdPropertyIds[] = (int) $source->property_id;

        $prop = DB::table('properties')->find($source->property_id);
        $this->assertStringStartsWith('Apify Test Cabin', $prop->name);
        $this->assertSame(2, (int) $prop->bedroom);
        $this->assertSame(4, (int) $prop->no_of_guest);
        $this->assertSame('false', $prop->status); // imported in draft
        $this->assertSame(['Wifi'], json_decode($prop->scraped_amenities_raw, true)); // raw amenities stashed for admin review

        $gallery = DB::table('property_galleries')->where('property_id', $source->property_id)->get();
        $this->assertCount(1, $gallery);
        $this->assertStringStartsWith('uploads/listings/' . $source->property_id . '/', $gallery[0]->image);
    }

    public function test_finalise_fill_blanks_only_does_not_overwrite_admin_edits(): void
    {
        Http::fake([
            'api.apify.com/v2/acts/*'              => Http::response(['data' => ['id' => 'r-2', 'defaultDatasetId' => 'd-2', 'status' => 'RUNNING']], 201),
            'api.apify.com/v2/actor-runs/r-2*'     => Http::response(['data' => ['id' => 'r-2', 'status' => 'SUCCEEDED', 'defaultDatasetId' => 'd-2', 'finishedAt' => now()->toIso8601String()]], 200),
            'api.apify.com/v2/datasets/d-2/items*' => Http::response([[
                'name'        => 'NEW SCRAPED NAME',
                'description' => 'NEW SCRAPED DESC',
                'bedrooms'    => 99,
                'price'       => ['rate' => 555],
            ]], 200),
        ]);

        // Pre-create a property with admin edits, link it to the source.
        $existingId = DB::table('properties')->insertGetId([
            'name' => 'Admin Edited Name',
            'seo_url' => 'admin-edited-' . uniqid(),
            'description' => '', // BLANK — should be filled
            'bedroom' => 0,      // BLANK (zero-equivalent) — should be filled
            'standard_rate' => 250.0, // ALREADY SET — must NOT be overwritten
            'status' => 'true',
            'type' => 'condo',
            'instant_booking_button' => 'Book Now',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->createdPropertyIds[] = $existingId;

        $sourceId = DB::table('scrape_sources')->insertGetId([
            'source_url' => 'https://www.airbnb.com/rooms/22222',
            'platform'   => 'airbnb',
            'property_id' => $existingId,
            'status'     => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->createdSourceIds[] = $sourceId;

        $runId = app(ScrapeOrchestrator::class)->kickOff($sourceId);
        $this->assertNotNull($runId);
        app(ScrapeOrchestrator::class)->finalise($runId);

        $after = DB::table('properties')->find($existingId);
        // Admin's name preserved
        $this->assertSame('Admin Edited Name', $after->name);
        // Admin's standard_rate preserved
        $this->assertEquals(250.0, (float) $after->standard_rate);
        // Blanks filled
        $this->assertSame('NEW SCRAPED DESC', $after->description);
        $this->assertSame(99, (int) $after->bedroom);
    }

    public function test_finalise_marks_failed_when_apify_run_did_not_succeed(): void
    {
        Http::fake([
            'api.apify.com/v2/acts/*'          => Http::response(['data' => ['id' => 'r-fail', 'defaultDatasetId' => 'd-fail', 'status' => 'RUNNING']], 201),
            'api.apify.com/v2/actor-runs/r-fail*' => Http::response(['data' => ['id' => 'r-fail', 'status' => 'FAILED', 'defaultDatasetId' => null, 'finishedAt' => now()->toIso8601String()]], 200),
        ]);

        $sourceId = $this->makeSource();
        $runId = app(ScrapeOrchestrator::class)->kickOff($sourceId);
        $this->assertFalse(app(ScrapeOrchestrator::class)->finalise($runId));

        $source = DB::table('scrape_sources')->find($sourceId);
        $this->assertSame('failed', $source->status);
        $this->assertStringContainsString('apify_run_not_successful', (string) $source->last_error);
    }

    public function test_finalise_marks_failed_when_dataset_empty(): void
    {
        Http::fake([
            'api.apify.com/v2/acts/*'              => Http::response(['data' => ['id' => 'r-empty', 'defaultDatasetId' => 'd-empty', 'status' => 'RUNNING']], 201),
            'api.apify.com/v2/actor-runs/r-empty*' => Http::response(['data' => ['id' => 'r-empty', 'status' => 'SUCCEEDED', 'defaultDatasetId' => 'd-empty', 'finishedAt' => now()->toIso8601String()]], 200),
            'api.apify.com/v2/datasets/d-empty/items*' => Http::response([], 200),
        ]);

        $sourceId = $this->makeSource('https://www.airbnb.com/rooms/empty');
        $runId = app(ScrapeOrchestrator::class)->kickOff($sourceId);
        $this->assertFalse(app(ScrapeOrchestrator::class)->finalise($runId));

        $source = DB::table('scrape_sources')->find($sourceId);
        $this->assertSame('failed', $source->status);
        $this->assertStringContainsString('empty_dataset', (string) $source->last_error);
    }

    /* ============== Webhook ============== */

    public function test_apify_webhook_finalises_run_when_secret_matches(): void
    {
        DB::table('basic_settings')->updateOrInsert(['name' => 'importer_webhook_secret'],
            ['value' => 'shh', 'updated_at' => now()]);
        app(ApifySettings::class)->refresh();

        // Create a kicked-off run row directly so we don't have to fake the kickoff path.
        $sourceId = $this->makeSource('https://www.airbnb.com/rooms/wh');
        $runRow = DB::table('scrape_runs')->insertGetId([
            'scrape_source_id' => $sourceId,
            'provider_run_id'     => 'r-wh',
            'provider_actor_id'   => 'tri_angle~airbnb-scraper',
            'provider_dataset_id' => null,
            'status'           => 'RUNNING',
            'started_at'       => now(),
            'created_at'       => now(), 'updated_at' => now(),
        ]);

        Http::fake([
            'api.apify.com/v2/actor-runs/r-wh*'    => Http::response(['data' => ['id' => 'r-wh', 'status' => 'SUCCEEDED', 'defaultDatasetId' => 'd-wh', 'finishedAt' => now()->toIso8601String()]], 200),
            'api.apify.com/v2/datasets/d-wh/items*' => Http::response([[
                'name' => 'Webhook Imported ' . uniqid(),
                'description' => 'wh',
                'bedrooms' => 1,
            ]], 200),
        ]);

        $payload = [
            'eventType' => 'ACTOR.RUN.SUCCEEDED',
            'eventData' => ['actorRunId' => 'r-wh'],
            'resource'  => ['id' => 'r-wh', 'status' => 'SUCCEEDED'],
        ];

        $resp = $this->postJson('/webhooks/listings', $payload, ['X-Webhook-Secret' => 'shh']);
        $resp->assertOk();

        $source = DB::table('scrape_sources')->find($sourceId);
        $this->assertSame('imported', $source->status);
        $this->createdPropertyIds[] = (int) $source->property_id;
    }

    public function test_apify_webhook_rejects_missing_secret(): void
    {
        DB::table('basic_settings')->updateOrInsert(['name' => 'importer_webhook_secret'],
            ['value' => 'shh', 'updated_at' => now()]);
        app(ApifySettings::class)->refresh();

        $resp = $this->postJson('/webhooks/listings', ['eventType' => 'ACTOR.RUN.SUCCEEDED', 'eventData' => ['actorRunId' => 'r-x']]);
        $resp->assertStatus(403);
    }
}

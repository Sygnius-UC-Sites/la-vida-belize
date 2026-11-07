<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * /stays — homepage hero / search-results listing.
 *
 * Locks the date-availability filter added 2026-05-07: a property with a
 * conflicting confirmed booking or imported iCal event for the requested
 * window must NOT appear in search results. Pre-fix the controller ignored
 * dates entirely (siddharth reported searching blocked dates on Casita Del
 * Eden and still seeing it in the results).
 */
class StaysSearchTest extends TestCase
{
    private int $availPropertyId;
    private int $bookedPropertyId;
    private int $bookingId;
    private string $availSlug;
    private string $bookedSlug;

    protected function setUp(): void
    {
        parent::setUp();

        $this->availSlug = '_test-avail-' . uniqid();
        $this->bookedSlug = '_test-booked-' . uniqid();
        $this->availPropertyId = DB::table('properties')->insertGetId([
            'name'                   => '_test_avail_property_',
            'seo_url'                => $this->availSlug,
            'instant_booking_button' => '',
            'status'                 => 'true',
            'standard_rate'          => 200,
            'min_stay'               => 1,
            'no_of_guest'            => 4,
            'sleeps'                 => 4,
            'type'                   => 'condo',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);
        $this->bookedPropertyId = DB::table('properties')->insertGetId([
            'name'                   => '_test_booked_property_',
            'seo_url'                => $this->bookedSlug,
            'instant_booking_button' => '',
            'status'                 => 'true',
            'standard_rate'          => 200,
            'min_stay'               => 1,
            'no_of_guest'            => 4,
            'sleeps'                 => 4,
            'type'                   => 'condo',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        // Confirmed booking that overlaps the search window we'll use below.
        $this->bookingId = DB::table('booking_requests')->insertGetId([
            'property_id'    => $this->bookedPropertyId,
            'name'           => 'Test Guest',
            'email'          => 'test@example.com',
            'checkin'        => '2030-09-10',
            'checkout'       => '2030-09-15',
            'booking_status' => 'booked',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('booking_requests')->where('id', $this->bookingId)->delete();
        DB::table('properties')
            ->whereIn('id', [$this->availPropertyId, $this->bookedPropertyId])
            ->delete();
        parent::tearDown();
    }

    public function test_search_with_no_dates_returns_both_properties(): void
    {
        $html = $this->get('/stays')->assertOk()->getContent();
        $this->assertResultContains($html, $this->availSlug);
        $this->assertResultContains($html, $this->bookedSlug);
    }

    public function test_search_with_unavailable_dates_excludes_conflicting_property(): void
    {
        $html = $this->get('/stays?check_in=2030-09-12&check_out=2030-09-14')
            ->assertOk()
            ->getContent();
        $this->assertResultContains($html, $this->availSlug);
        $this->assertResultDoesNotContain($html, $this->bookedSlug);
    }

    public function test_search_with_dates_outside_existing_booking_includes_both(): void
    {
        // Window that ends BEFORE the existing booking starts → no conflict.
        $html = $this->get('/stays?check_in=2030-09-01&check_out=2030-09-05')
            ->assertOk()
            ->getContent();
        $this->assertResultContains($html, $this->availSlug);
        $this->assertResultContains($html, $this->bookedSlug);
    }

    public function test_pet_filter_excludes_properties_that_dont_accept_pets(): void
    {
        // availPropertyId currently accepts no pets (default 0). Update one
        // record to be pet-friendly with cap 2; the other stays no-pets.
        DB::table('properties')->where('id', $this->availPropertyId)->update([
            'accepts_pets' => 1,
            'max_pet'      => '2',
        ]);
        DB::table('properties')->where('id', $this->bookedPropertyId)->update([
            'accepts_pets' => 0,
            'max_pet'      => '0',
        ]);

        // pets=1 → pet-friendly only.
        $html = $this->get('/stays?pets=1')->assertOk()->getContent();
        $this->assertResultContains($html, $this->availSlug);
        $this->assertResultDoesNotContain($html, $this->bookedSlug);

        // pets=3 exceeds avail's max_pet=2 → both excluded.
        $html = $this->get('/stays?pets=3')->assertOk()->getContent();
        $this->assertResultDoesNotContain($html, $this->availSlug);
        $this->assertResultDoesNotContain($html, $this->bookedSlug);

        // pets=0 → filter inactive, both included.
        $html = $this->get('/stays?pets=0')->assertOk()->getContent();
        $this->assertResultContains($html, $this->availSlug);
        $this->assertResultContains($html, $this->bookedSlug);
    }

    public function test_search_excludes_property_with_overlapping_imported_ical_event(): void
    {
        $icalEventId = DB::table('ical_events')->insertGetId([
            'ppp_id'         => $this->availPropertyId,
            'ical_link'      => 'https://example.com/feed.ics',
            'start_date'     => '2030-10-05',
            'end_date'       => '2030-10-12',
            'text'           => 'Imported booking',
            'event_pid'      => $this->availPropertyId,
            'cat_id'         => 1,
            'uid'            => 'test-stays-' . uniqid(),
            'event_type'     => 'reserved',
            'booking_status' => 1,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
        try {
            $html = $this->get('/stays?check_in=2030-10-07&check_out=2030-10-10')
                ->assertOk()
                ->getContent();
            $this->assertResultDoesNotContain($html, $this->availSlug);
            $this->assertResultContains($html, $this->bookedSlug);
        } finally {
            DB::table('ical_events')->where('id', $icalEventId)->delete();
        }
    }

    private function assertResultContains(string $html, string $slug): void
    {
        $this->assertStringContainsString('data-slug="' . $slug . '"', $html);
    }

    private function assertResultDoesNotContain(string $html, string $slug): void
    {
        $this->assertStringNotContainsString('data-slug="' . $slug . '"', $html);
    }
}

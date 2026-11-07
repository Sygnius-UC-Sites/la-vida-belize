<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IcalExportTest extends TestCase
{
    private int $propertyId;
    private int $bookingId;
    private int $blockId;
    private int $icalEventId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->propertyId = DB::table('properties')->insertGetId([
            'name'                   => '_test_ical_property_',
            'seo_url'                => '_test-ical-' . uniqid(),
            'instant_booking_button' => '',
            'status'                 => 'true',
            'standard_rate'          => 200,
            'min_stay'               => 2,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $this->bookingId = DB::table('booking_requests')->insertGetId([
            'property_id'    => $this->propertyId,
            'name'           => 'Test Guest',
            'email'          => 'guest@example.com',
            'checkin'        => '2030-01-10',
            'checkout'       => '2030-01-15',
            'booking_status' => 'booked',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $this->blockId = DB::table('property_rates')->insertGetId([
            'property_id'           => (string) $this->propertyId,
            'single_date'           => '2030-02-20',
            'single_date_timestamp' => strtotime('2030-02-20'),
            'price'                 => 0,
            'is_available'          => '0',
            'notes'                 => 'Owner staying',
            'rate_group_id'         => null,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        // An imported Airbnb event — must NOT appear in our export.
        $this->icalEventId = DB::table('ical_events')->insertGetId([
            'ppp_id'         => $this->propertyId,
            'ical_link'      => 'https://airbnb.example/feed.ics',
            'start_date'     => '2030-03-01',
            'end_date'       => '2030-03-05',
            'text'           => 'Reserved (Airbnb import)',
            'event_pid'      => $this->propertyId,
            'cat_id'         => 1,
            'uid'            => 'airbnb-uid-test-001',
            'event_type'     => 'reserved',
            'booking_status' => 1,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('property_rates')->where('id', $this->blockId)->delete();
        DB::table('ical_events')->where('id', $this->icalEventId)->delete();
        DB::table('booking_requests')->where('id', $this->bookingId)->delete();
        DB::table('properties')->where('id', $this->propertyId)->delete();

        parent::tearDown();
    }

    public function test_export_emits_only_confirmed_bookings_and_manual_blocks(): void
    {
        $response = $this->get('/ical/' . $this->propertyId);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/calendar; charset=utf-8');

        $body = $response->getContent();

        $this->assertStringContainsString('BEGIN:VCALENDAR', $body);
        $this->assertStringContainsString('END:VCALENDAR', $body);

        // Confirmed booking
        $this->assertStringContainsString('UID:booking-' . $this->bookingId . '@', $body);
        $this->assertStringContainsString('DTSTART;VALUE=DATE:20300110', $body);
        $this->assertStringContainsString('DTEND;VALUE=DATE:20300115', $body);

        // Manual block — DTEND is exclusive (one-day block runs D → D+1)
        $this->assertStringContainsString('UID:block-' . $this->blockId . '@', $body);
        $this->assertStringContainsString('DTSTART;VALUE=DATE:20300220', $body);
        $this->assertStringContainsString('DTEND;VALUE=DATE:20300221', $body);
        $this->assertStringContainsString('SUMMARY:Blocked — Owner staying', $body);

        // Imported feed must NOT leak into our export — those channels own
        // those dates on their side, re-exporting creates sync loops.
        $this->assertStringNotContainsString('airbnb-uid-test-001', $body);
        $this->assertStringNotContainsString('20300301', $body);

        // Exactly two VEVENTs.
        $this->assertSame(2, substr_count($body, 'BEGIN:VEVENT'));
        $this->assertSame(2, substr_count($body, 'END:VEVENT'));
    }

    public function test_export_excludes_non_booked_statuses_and_available_overrides(): void
    {
        // Each non-booked status — none should appear in the export.
        $extraIds = [];
        foreach (['cancelled', 'rejected', 'declined', 'pending-approval', 'booking-accepted', 'completed', 'rental-aggrement'] as $i => $status) {
            $extraIds[] = DB::table('booking_requests')->insertGetId([
                'property_id'    => $this->propertyId,
                'name'           => 'Excluded ' . $status,
                'email'          => 'excluded' . $i . '@example.com',
                'checkin'        => '2030-04-' . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT),
                'checkout'       => '2030-04-' . str_pad((string)($i + 2), 2, '0', STR_PAD_LEFT),
                'booking_status' => $status,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }
        $availableId = DB::table('property_rates')->insertGetId([
            'property_id'           => (string) $this->propertyId,
            'single_date'           => '2030-04-25',
            'single_date_timestamp' => strtotime('2030-04-25'),
            'price'                 => 250,
            'is_available'          => '1',
            'rate_group_id'         => null,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        try {
            $body = $this->get('/ical/' . $this->propertyId)->getContent();
            foreach ($extraIds as $id) {
                $this->assertStringNotContainsString('booking-' . $id, $body);
            }
            $this->assertStringNotContainsString('block-' . $availableId, $body);
            $this->assertStringNotContainsString('20300425', $body);
        } finally {
            DB::table('booking_requests')->whereIn('id', $extraIds)->delete();
            DB::table('property_rates')->where('id', $availableId)->delete();
        }
    }

    public function test_export_returns_404_for_unknown_property(): void
    {
        $this->get('/ical/99999999')->assertNotFound();
    }

    public function test_write_ics_file_emits_same_body_as_dynamic_route(): void
    {
        $controller = new \App\Http\Controllers\ICalController();
        $path = public_path('uploads/ical/' . sprintf('%06d', $this->propertyId) . '.ics');

        @unlink($path);
        $this->assertTrue($controller->writeIcsFile($this->propertyId));
        $this->assertFileExists($path);

        try {
            $disk    = file_get_contents($path);
            $route   = $this->get('/ical/' . $this->propertyId)->getContent();

            // DTSTAMP timestamps differ by execution time; strip them before comparing.
            $strip = fn(string $s) => preg_replace('/^DTSTAMP:[^\r\n]*\r?\n/m', '', $s);
            $this->assertSame($strip($route), $strip($disk));
        } finally {
            @unlink($path);
        }
    }

    public function test_write_ics_file_returns_false_for_unknown_property(): void
    {
        $controller = new \App\Http\Controllers\ICalController();
        $this->assertFalse($controller->writeIcsFile(99999999));
    }
}

<?php

namespace Tests\Feature\Stay;

use App\Mail\CheckinInfoSent;
use App\Models\BookingRequest;
use App\Models\Property;
use App\Services\Stay\StayCommunicationsResolver;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CheckinEmailContentTest extends TestCase
{
    private int $propertyId;
    private int $bookingId;
    private array $savedSettings = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'checkin_default_arrival_window', 'checkin_default_door_code', 'checkin_default_wifi_name',
            'checkin_default_wifi_password', 'checkin_default_parking_instructions',
            'checkin_default_host_name', 'checkin_default_host_phone', 'checkin_default_emergency_phone',
            'checkin_default_notes_html',
        ] as $k) {
            $this->savedSettings[$k] = DB::table('basic_settings')->where('name', $k)->value('value');
        }

        $this->propertyId = DB::table('properties')->insertGetId([
            'name' => 'CIE-Test ' . uniqid(),
            'seo_url' => 'cie-test-' . uniqid(),
            'instant_booking_button' => 'Book Now',
            'standard_rate' => 200,
            'no_of_guest' => 4,
            'type' => 'condo',
            'status' => 'true',
        ]);

        $this->bookingId = DB::table('booking_requests')->insertGetId([
            'property_id' => $this->propertyId,
            'checkin' => '2026-06-01',
            'checkout' => '2026-06-04',
            'name' => 'Checkin Guest',
            'email' => 'cie@example.com',
            'total_night' => 3,
            'rental_aggrement_status' => 'false',
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('booking_requests')->where('id', $this->bookingId)->delete();
        DB::table('properties')->where('id', $this->propertyId)->delete();
        foreach ($this->savedSettings as $k => $v) {
            if ($v !== null) {
                DB::table('basic_settings')->updateOrInsert(['name' => $k], ['value' => $v, 'updated_at' => now()]);
            }
        }
        parent::tearDown();
    }

    private function renderCheckin(): string
    {
        $booking = BookingRequest::find($this->bookingId);
        $property = Property::find($this->propertyId);
        $resolver = new StayCommunicationsResolver($property, $booking);
        $payload = $resolver->resolveCheckinPayload();
        $m = new CheckinInfoSent($booking, $property, $payload);
        return $m->render();
    }

    public function test_checkin_email_renders_structured_fields_from_property_overrides(): void
    {
        DB::table('properties')->where('id', $this->propertyId)->update([
            'checkin_door_code'  => 'PROP1234',
            'checkin_wifi_name'  => 'PROP-NET',
            'checkin_host_phone' => '+506-PROP-1234',
        ]);

        $html = $this->renderCheckin();
        $this->assertStringContainsString('PROP1234', $html);
        $this->assertStringContainsString('PROP-NET', $html);
        $this->assertStringContainsString('+506-PROP-1234', $html);
        $this->assertStringContainsString('Door code:', $html);
        $this->assertStringContainsString('Wi-Fi:', $html);
    }

    public function test_checkin_email_renders_global_fields_when_property_null(): void
    {
        DB::table('basic_settings')->updateOrInsert(['name' => 'checkin_default_door_code'], ['value' => 'GLOBAL9999', 'updated_at' => now()]);
        DB::table('basic_settings')->updateOrInsert(['name' => 'checkin_default_wifi_name'], ['value' => 'GLOBAL-NET', 'updated_at' => now()]);

        $html = $this->renderCheckin();
        $this->assertStringContainsString('GLOBAL9999', $html);
        $this->assertStringContainsString('GLOBAL-NET', $html);
    }

    public function test_checkin_email_omits_blank_fields(): void
    {
        // Empty door_code in both tiers
        DB::table('basic_settings')->updateOrInsert(['name' => 'checkin_default_door_code'], ['value' => '', 'updated_at' => now()]);
        DB::table('basic_settings')->updateOrInsert(['name' => 'checkin_default_wifi_name'], ['value' => '', 'updated_at' => now()]);

        $html = $this->renderCheckin();
        $this->assertStringNotContainsString('Door code:', $html);
        $this->assertStringNotContainsString('Wi-Fi:', $html);
    }
}

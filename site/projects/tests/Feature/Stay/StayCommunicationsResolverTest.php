<?php

namespace Tests\Feature\Stay;

use App\Models\BookingRequest;
use App\Models\Property;
use App\Services\Stay\StayCommunicationsResolver;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StayCommunicationsResolverTest extends TestCase
{
    private int $propertyId;
    private int $bookingId;
    private array $savedSettings = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Snapshot the global settings we'll mutate so we can restore them
        foreach ([
            'welcome_default_description',
            'checkin_default_arrival_window',
            'checkin_default_door_code',
            'checkin_default_wifi_name',
            'checkin_default_host_phone',
            'checkin_default_emergency_phone',
        ] as $k) {
            $this->savedSettings[$k] = DB::table('basic_settings')->where('name', $k)->value('value');
        }

        $this->propertyId = DB::table('properties')->insertGetId([
            'name'    => 'RES-Test ' . uniqid(),
            'seo_url' => 'res-test-' . uniqid(),
            'instant_booking_button' => 'Book Now',
            'standard_rate' => 150,
            'no_of_guest'   => 4,
            'type'    => 'condo',
            'status'  => 'true',
        ]);

        $this->bookingId = DB::table('booking_requests')->insertGetId([
            'property_id' => $this->propertyId,
            'checkin'     => '2026-06-01',
            'checkout'    => '2026-06-04',
            'name'        => 'Resolver Guest',
            'email'       => 'res@example.com',
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

    private function resolver(): StayCommunicationsResolver
    {
        return new StayCommunicationsResolver(
            Property::find($this->propertyId),
            BookingRequest::find($this->bookingId),
        );
    }

    public function test_resolves_property_value_when_set(): void
    {
        DB::table('properties')->where('id', $this->propertyId)->update([
            'welcome_package_description' => 'PROP DESC',
        ]);
        DB::table('basic_settings')->updateOrInsert(['name' => 'welcome_default_description'], ['value' => 'GLOBAL DESC', 'updated_at' => now()]);

        $this->assertSame('PROP DESC', $this->resolver()->resolve('welcome_package_description', 'welcome_default_description', false));
    }

    public function test_falls_back_to_global_when_property_null(): void
    {
        DB::table('basic_settings')->updateOrInsert(['name' => 'welcome_default_description'], ['value' => 'GLOBAL DESC', 'updated_at' => now()]);

        $this->assertSame('GLOBAL DESC', $this->resolver()->resolve('welcome_package_description', 'welcome_default_description', false));
    }

    public function test_returns_empty_string_when_neither_set(): void
    {
        DB::table('basic_settings')->where('name', 'welcome_default_description')->delete();
        $this->assertSame('', $this->resolver()->resolve('welcome_package_description', 'welcome_default_description', false));
    }

    public function test_expands_merge_tokens(): void
    {
        $html = 'Hi {{guest_name}} at {{property_name}} (check-in {{check_in}})';
        $expanded = $this->resolver()->expandMergeFields($html);
        $this->assertStringContainsString('Resolver Guest', $expanded);
        $this->assertStringContainsString('RES-Test', $expanded);
        $this->assertStringContainsString('Monday · Jun 1, 2026', $expanded);
    }

    public function test_html_escapes_guest_data_in_merge_tokens(): void
    {
        DB::table('booking_requests')->where('id', $this->bookingId)->update(['name' => '<script>x</script>']);
        $expanded = $this->resolver()->expandMergeFields('Hi {{guest_name}}');
        $this->assertStringNotContainsString('<script>x</script>', $expanded);
        $this->assertStringContainsString('&lt;script&gt;x&lt;/script&gt;', $expanded);
    }

    public function test_resolveCheckinPayload_returns_full_assoc(): void
    {
        DB::table('properties')->where('id', $this->propertyId)->update([
            'checkin_door_code' => '1234',
        ]);
        DB::table('basic_settings')->updateOrInsert(['name' => 'checkin_default_wifi_name'], ['value' => 'GlobalWifi', 'updated_at' => now()]);

        $payload = $this->resolver()->resolveCheckinPayload();
        $this->assertSame('1234', $payload['door_code']);
        $this->assertSame('GlobalWifi', $payload['wifi_name']);
        $this->assertArrayHasKey('arrival_window', $payload);
        $this->assertArrayHasKey('emergency_phone', $payload);
    }

    public function test_resolveSendDayOffset_property_beats_global_beats_fallback(): void
    {
        // (a) property set
        DB::table('properties')->where('id', $this->propertyId)->update(['welcome_email_send_day' => 2]);
        $this->assertSame(2, $this->resolver()->resolveSendDayOffset('welcome_email_send_day', 'welcome_package_send_day', 99));

        // (b) property null, global set
        DB::table('properties')->where('id', $this->propertyId)->update(['welcome_email_send_day' => null]);
        DB::table('basic_settings')->updateOrInsert(['name' => 'welcome_package_send_day'], ['value' => '5', 'updated_at' => now()]);
        $this->assertSame(5, $this->resolver()->resolveSendDayOffset('welcome_email_send_day', 'welcome_package_send_day', 99));

        // (c) both empty -> fallback
        DB::table('basic_settings')->where('name', 'welcome_package_send_day')->delete();
        $this->assertSame(99, $this->resolver()->resolveSendDayOffset('welcome_email_send_day', 'welcome_package_send_day', 99));
    }
}

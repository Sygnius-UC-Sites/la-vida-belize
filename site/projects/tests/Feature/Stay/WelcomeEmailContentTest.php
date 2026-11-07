<?php

namespace Tests\Feature\Stay;

use App\Mail\WelcomePackageSent;
use App\Models\BookingRequest;
use App\Models\Property;
use App\Services\Stay\StayCommunicationsResolver;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WelcomeEmailContentTest extends TestCase
{
    private int $propertyId;
    private int $bookingId;
    private array $savedSettings = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['welcome_default_description', 'welcome_default_attachment_path'] as $k) {
            $this->savedSettings[$k] = DB::table('basic_settings')->where('name', $k)->value('value');
        }

        $this->propertyId = DB::table('properties')->insertGetId([
            'name' => 'WEC-Test ' . uniqid(),
            'seo_url' => 'wec-test-' . uniqid(),
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
            'name' => 'Welcome Guest',
            'email' => 'wel@example.com',
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

    private function renderWelcome(?BookingRequest $b = null): string
    {
        $booking = $b ?: BookingRequest::find($this->bookingId);
        $property = Property::find($this->propertyId);
        $resolver = new StayCommunicationsResolver($property, $booking);
        $payload = $resolver->resolveWelcomePayload();
        $m = new WelcomePackageSent($booking, $property, $payload['description_html'], $payload['files']);
        return $m->render();
    }

    public function test_welcome_email_renders_property_description_when_set(): void
    {
        DB::table('properties')->where('id', $this->propertyId)->update([
            'welcome_package_description' => 'PROP DESC OVERRIDE',
        ]);
        DB::table('basic_settings')->updateOrInsert(['name' => 'welcome_default_description'], ['value' => 'GLOBAL DESC', 'updated_at' => now()]);

        $html = $this->renderWelcome();
        $this->assertStringContainsString('PROP DESC OVERRIDE', $html);
        $this->assertStringNotContainsString('GLOBAL DESC', $html);
    }

    public function test_welcome_email_renders_global_description_when_property_null(): void
    {
        DB::table('basic_settings')->updateOrInsert(['name' => 'welcome_default_description'], ['value' => 'GLOBAL ONLY DESC', 'updated_at' => now()]);

        $html = $this->renderWelcome();
        $this->assertStringContainsString('GLOBAL ONLY DESC', $html);
    }

    public function test_welcome_email_expands_guest_name_token(): void
    {
        DB::table('basic_settings')->updateOrInsert(['name' => 'welcome_default_description'], ['value' => 'Hi {{guest_name}}!', 'updated_at' => now()]);
        DB::table('booking_requests')->where('id', $this->bookingId)->update(['name' => 'Bob']);

        $html = $this->renderWelcome();
        $this->assertStringContainsString('Hi Bob!', $html);
    }
}

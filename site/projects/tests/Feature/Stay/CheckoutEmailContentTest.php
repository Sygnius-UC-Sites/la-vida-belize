<?php

namespace Tests\Feature\Stay;

use App\Mail\CheckoutInfoSent;
use App\Models\BookingRequest;
use App\Models\Property;
use App\Services\Stay\StayCommunicationsResolver;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CheckoutEmailContentTest extends TestCase
{
    private int $propertyId;
    private int $bookingId;
    private array $savedSettings = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'checkout_default_departure_time', 'checkout_default_key_return',
            'checkout_default_cleaning_checklist', 'checkout_default_notes_html',
        ] as $k) {
            $this->savedSettings[$k] = DB::table('basic_settings')->where('name', $k)->value('value');
        }

        $this->propertyId = DB::table('properties')->insertGetId([
            'name' => 'COE-Test ' . uniqid(),
            'seo_url' => 'coe-test-' . uniqid(),
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
            'name' => 'Checkout Guest',
            'email' => 'coe@example.com',
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

    private function renderCheckout(): string
    {
        $booking = BookingRequest::find($this->bookingId);
        $property = Property::find($this->propertyId);
        $resolver = new StayCommunicationsResolver($property, $booking);
        $payload = $resolver->resolveCheckoutPayload();
        $m = new CheckoutInfoSent($booking, $property, $payload);
        return $m->render();
    }

    public function test_checkout_email_renders_structured_fields_from_property_overrides(): void
    {
        DB::table('properties')->where('id', $this->propertyId)->update([
            'checkout_departure_time'      => '10:30 AM PROP',
            'checkout_cleaning_checklist'  => '<ul><li>PROP CHECKLIST ITEM</li></ul>',
        ]);

        $html = $this->renderCheckout();
        $this->assertStringContainsString('10:30 AM PROP', $html);
        $this->assertStringContainsString('PROP CHECKLIST ITEM', $html);
        $this->assertStringContainsString('Departure time:', $html);
    }

    public function test_checkout_email_renders_global_fields_when_property_null(): void
    {
        DB::table('basic_settings')->updateOrInsert(['name' => 'checkout_default_departure_time'], ['value' => 'BY 9 GLOBAL', 'updated_at' => now()]);
        DB::table('basic_settings')->updateOrInsert(['name' => 'checkout_default_cleaning_checklist'], ['value' => '<ul><li>GLOBAL CHECKLIST</li></ul>', 'updated_at' => now()]);

        $html = $this->renderCheckout();
        $this->assertStringContainsString('BY 9 GLOBAL', $html);
        $this->assertStringContainsString('GLOBAL CHECKLIST', $html);
    }

    public function test_checkout_email_omits_blank_fields(): void
    {
        DB::table('basic_settings')->updateOrInsert(['name' => 'checkout_default_departure_time'], ['value' => '', 'updated_at' => now()]);
        DB::table('basic_settings')->updateOrInsert(['name' => 'checkout_default_cleaning_checklist'], ['value' => '', 'updated_at' => now()]);

        $html = $this->renderCheckout();
        $this->assertStringNotContainsString('Departure time:', $html);
        $this->assertStringNotContainsString('Before you leave:', $html);
    }
}

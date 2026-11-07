<?php

namespace Tests\Feature\Visitor;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CheckoutPageTest extends TestCase
{
    use WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'enabled_payment_gateways' => 'stripe',
            'stripe_publish_key' => 'pk_test_checkout_page',
            'stripe_secret_key' => 'sk_test_checkout_page',
        ] as $name => $value) {
            DB::table('basic_settings')->updateOrInsert(
                ['name' => $name],
                ['value' => $value, 'updated_at' => now()]
            );
        }
    }

    private function propertySlug(): string
    {
        return (string) DB::table('properties')->where('id', 1)->value('seo_url');
    }

    public function test_checkout_page_renders_with_breakdown_for_valid_property(): void
    {
        $checkIn = \Carbon\Carbon::today()->addDays(30)->toDateString();
        $checkOut = \Carbon\Carbon::today()->addDays(33)->toDateString();
        $url = "/booking/checkout/" . $this->propertySlug() . "?check_in={$checkIn}&check_out={$checkOut}&adults=2";

        $response = $this->get($url);
        $response->assertStatus(200);
        $response->assertSee('Total');
    }

    public function test_checkout_page_404s_for_invalid_property_slug(): void
    {
        $response = $this->get('/booking/checkout/nonexistent-property-slug?check_in=2026-09-01&check_out=2026-09-04&adults=2');
        $response->assertStatus(404);
    }

    public function test_checkout_page_still_renders_when_instant_booking_disabled(): void
    {
        DB::table('properties')->where('id', 1)->update(['instant_booking_enabled' => 0]);

        $checkIn = \Carbon\Carbon::today()->addDays(30)->toDateString();
        $checkOut = \Carbon\Carbon::today()->addDays(33)->toDateString();
        $response = $this->get("/booking/checkout/" . $this->propertySlug() . "?check_in={$checkIn}&check_out={$checkOut}&adults=2");
        $response->assertOk();
        $response->assertSee('Total');

        DB::table('properties')->where('id', 1)->update(['instant_booking_enabled' => 0]);  // already 0; reset for safety
    }

    public function test_checkout_page_still_renders_when_instant_booking_enabled(): void
    {
        DB::table('properties')->where('id', 1)->update(['instant_booking_enabled' => 1]);

        $checkIn = \Carbon\Carbon::today()->addDays(30)->toDateString();
        $checkOut = \Carbon\Carbon::today()->addDays(33)->toDateString();
        $response = $this->get("/booking/checkout/" . $this->propertySlug() . "?check_in={$checkIn}&check_out={$checkOut}&adults=2");
        $response->assertOk();
        $response->assertSee('Total');

        DB::table('properties')->where('id', 1)->update(['instant_booking_enabled' => 0]);
    }
}

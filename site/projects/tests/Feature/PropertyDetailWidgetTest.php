<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Support\PropertyUrl;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Locks Phase 2 (2026-05-07) of the booking-flow redesign:
 *  - Property-detail booking widget renders dates, an Airbnb-style
 *    Adults/Children/(Pets) stepper popover, and a Next button.
 *  - Optional addons + coupon are NOT rendered here (those moved to checkout).
 *  - Pet stepper appears only when accepts_pets is on AND max_pet > 0.
 *  - The data-blocked attribute is populated from existing bookings + iCal events.
 */
class PropertyDetailWidgetTest extends TestCase
{
    private int $petsAllowedId;
    private int $petsBlockedId;
    private int $bookingId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->petsAllowedId = DB::table('properties')->insertGetId([
            'name'                   => '_test_pet_allowed_property_',
            'seo_url'                => '_test-pet-allowed-' . uniqid(),
            'instant_booking_button' => '',
            'status'                 => 'true',
            'standard_rate'          => 200,
            'min_stay'               => 1,
            'no_of_guest'            => 4,
            'accepts_pets'           => 1,
            'max_pet'                => '2',
            'type'                   => 'condo',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $this->petsBlockedId = DB::table('properties')->insertGetId([
            'name'                   => '_test_pet_blocked_property_',
            'seo_url'                => '_test-pet-blocked-' . uniqid(),
            'instant_booking_button' => '',
            'status'                 => 'true',
            'standard_rate'          => 200,
            'min_stay'               => 1,
            'no_of_guest'            => 4,
            'accepts_pets'           => 0,
            'max_pet'                => '0',
            'type'                   => 'condo',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        // Seed a confirmed booking on the pet-friendly property so the
        // data-blocked attribute should pick it up.
        $this->bookingId = DB::table('booking_requests')->insertGetId([
            'property_id'    => $this->petsAllowedId,
            'name'           => 'Blocked Test',
            'email'          => 'blocked@example.com',
            'checkin'        => '2030-08-10',
            'checkout'       => '2030-08-15',
            'booking_status' => 'booked',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('booking_requests')->where('id', $this->bookingId)->delete();
        DB::table('properties')->whereIn('id', [$this->petsAllowedId, $this->petsBlockedId])->delete();
        parent::tearDown();
    }

    private function widgetHtml(int $propertyId): string
    {
        $property = Property::findOrFail($propertyId);
        $response = $this->get(PropertyUrl::path($property));
        $response->assertOk();
        return $response->getContent();
    }

    public function test_widget_renders_steppers_for_adults_and_children_always(): void
    {
        $html = $this->widgetHtml($this->petsAllowedId);

        $this->assertStringContainsString('id="widget-guests-trigger"', $html);
        $this->assertStringContainsString('id="widget-guests-popover"', $html);
        $this->assertStringContainsString('id="widget-adults-value"', $html);
        $this->assertStringContainsString('id="widget-children-value"', $html);
        $this->assertStringContainsString('data-target="adults"', $html);
        $this->assertStringContainsString('data-target="children"', $html);
        $this->assertStringContainsString('id="widget-adults"', $html);
        $this->assertStringContainsString('id="widget-children"', $html);
    }

    public function test_pet_stepper_renders_when_property_allows_pets(): void
    {
        $html = $this->widgetHtml($this->petsAllowedId);
        $this->assertStringContainsString('data-target="pets"', $html);
        $this->assertStringContainsString('id="widget-pets-value"', $html);
        $this->assertStringContainsString('id="widget-pets"', $html);
    }

    public function test_pet_stepper_omitted_when_property_disallows_pets(): void
    {
        $html = $this->widgetHtml($this->petsBlockedId);
        $this->assertStringNotContainsString('data-target="pets"', $html);
        $this->assertStringNotContainsString('id="widget-pets-value"', $html);
        $this->assertStringNotContainsString('id="widget-pets"', $html);
    }

    public function test_widget_supports_verified_optional_services_without_coupon_ui(): void
    {
        $html = $this->widgetHtml($this->petsAllowedId);
        $this->assertStringNotContainsString('widget-coupon-code', $html);
        $this->assertStringNotContainsString('coupon-details', $html);
        $this->assertStringContainsString('Optional services', $html);
        $this->assertStringContainsString('optional_services', $html);
    }

    public function test_blocked_dates_attribute_includes_active_booking_range(): void
    {
        $html = $this->widgetHtml($this->petsAllowedId);

        // The booking spans 2030-08-10 → 2030-08-15. iCalDataCheckInCheckOutCheckinCheckout
        // blocks the interior days (start+1 .. end-1). Assert one of those interior days appears
        // in the data-blocked JSON attribute.
        $this->assertMatchesRegularExpression(
            '/data-blocked=[\'"]\[[^\]]*"2030-08-12"/',
            $html,
            'data-blocked must include 2030-08-12 (an interior day of the active booking)'
        );
    }

    public function test_picker_internal_clear_replaces_external_button(): void
    {
        $html = $this->widgetHtml($this->petsAllowedId);
        // Phase: 2026-05-07 — Clear-dates pill moved INSIDE the picker
        // (Airbnb-style). The external #widget-clear-dates button is gone;
        // the picker injects its own .jc-picker-clear via jc-datepicker.js.
        $this->assertStringNotContainsString('id="widget-clear-dates"', $html);
    }

    public function test_picker_uses_plugin_names_expected_by_bundled_easepick(): void
    {
        $script = file_get_contents(dirname(base_path()) . '/front/js/jc-datepicker.js');

        $this->assertIsString($script);
        $this->assertStringContainsString("plugins.push('RangePlugin')", $script);
        $this->assertStringContainsString("plugins.push('LockPlugin')", $script);
        $this->assertStringNotContainsString('plugins.push(easepick.RangePlugin)', $script);
        $this->assertStringNotContainsString('plugins.push(easepick.LockPlugin)', $script);
    }

    public function test_check_in_and_check_out_inputs_have_add_date_placeholder(): void
    {
        $html = $this->widgetHtml($this->petsAllowedId);
        $this->assertMatchesRegularExpression(
            '/<input[^>]*id="widget-check-in"[^>]*placeholder="Add date"/',
            $html,
            'check-in input must have placeholder "Add date" (Airbnb-style empty state)'
        );
        $this->assertMatchesRegularExpression(
            '/<input[^>]*id="widget-check-out"[^>]*placeholder="Add date"/',
            $html,
            'check-out input must have placeholder "Add date"'
        );
    }

    public function test_max_guests_uses_sleeps_not_no_of_guest_threshold(): void
    {
        // Property where sleeps (capacity) and no_of_guest (fee threshold) differ —
        // mirrors Casita Del Eden's real config (sleeps=5, no_of_guest=4).
        DB::table('properties')->where('id', $this->petsAllowedId)->update([
            'sleeps'      => 5,
            'no_of_guest' => '4',
        ]);
        $html = $this->widgetHtml($this->petsAllowedId);

        // The widget renders data-max-guests on the .guests-selector wrapper.
        $this->assertMatchesRegularExpression(
            '/id="widget-guests-selector"[^>]*data-max-guests="5"/',
            $html,
            'widget cap must be sleeps (5), NOT no_of_guest×2 (8) — bumping past sleeps causes API errors'
        );
    }
}

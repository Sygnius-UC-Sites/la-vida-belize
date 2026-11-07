<?php

namespace Tests\Feature\Booking;

use App\Services\Booking\QuoteCalculator;
use App\Services\Booking\QuoteInput;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QuoteCalculatorPricingTest extends TestCase
{
    private int $propertyId;
    private QuoteCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->propertyId = DB::table('properties')->insertGetId([
            'name' => 'Test Pricing Property ' . uniqid(),
            'seo_url' => 'test-pricing-' . uniqid(),
            'instant_booking_button' => 'Book Now',
            'standard_rate' => 200,
            'cleaning_fee' => 50,
            'tax' => 8,
            'no_of_guest' => 4,
            'min_stay' => null,
            'max_stay' => null,
            'type' => 'condo',
            'status' => 'true',
        ]);

        $this->calc = app(QuoteCalculator::class);
    }

    protected function tearDown(): void
    {
        DB::table('properties_rates_group')->where('property_id', $this->propertyId)->delete();
        DB::table('property_rates')->where('property_id', $this->propertyId)->delete();
        DB::table('properties')->where('id', $this->propertyId)->delete();
        parent::tearDown();
    }

    private function quote(string $start, string $end, int $adults = 2, ?string $coupon = null): \App\Services\Booking\Quote
    {
        return $this->calc->calculate(QuoteInput::fromArray([
            'property_id' => $this->propertyId,
            'check_in' => $start,
            'check_out' => $end,
            'adults' => $adults,
            'coupon_code' => $coupon,
        ]));
    }

    public function test_property_with_only_standard_rate_quotes_correctly(): void
    {
        $start = now()->addDays(30)->format('Y-m-d');
        $end = now()->addDays(33)->format('Y-m-d');  // 3 nights

        $quote = $this->quote($start, $end);

        $this->assertTrue($quote->valid, 'Quote should be valid; errors: ' . implode('; ', $quote->errors));
        $this->assertSame(3, $quote->nights);
        $this->assertSame(600.0, $quote->subtotal_nights_only);  // 3 × 200
        $this->assertNull($quote->los_discount);
        $this->assertNull($quote->coupon_discount);
        // Total = 600 nights + 50 cleaning + 8% tax on (600+50) = 650 + 52 = 702
        $this->assertEqualsWithDelta(702.0, $quote->total, 0.01);
    }

    public function test_property_with_seasonal_rule_overrides_standard_rate(): void
    {
        $start = now()->addDays(60)->format('Y-m-d');
        $end = now()->addDays(63)->format('Y-m-d');  // 3 nights

        $endRange = now()->addDays(90)->format('Y-m-d');
        DB::table('properties_rates_group')->insert([
            'property_id' => $this->propertyId,
            'name_of_price' => 'Test Season',
            'type_of_price' => 'default',
            'start_date' => $start,
            'start_date_timestamp' => strtotime($start),
            'end_date' => $endRange,
            'end_date_timestamp' => strtotime($endRange),
            'price' => 300,
            'min_stay' => 1,
        ]);

        $quote = $this->quote($start, $end);

        $this->assertTrue($quote->valid, 'Quote should be valid; errors: ' . implode('; ', $quote->errors));
        $this->assertSame(900.0, $quote->subtotal_nights_only);  // 3 × 300, NOT 3 × 200
    }

    public function test_property_with_per_weekday_seasonal_rule(): void
    {
        // Pick a Friday + 3 nights to span Fri/Sat/Sun
        $friday = now()->next(\Carbon\Carbon::FRIDAY)->addWeeks(2);
        $start = $friday->format('Y-m-d');
        $end = $friday->copy()->addDays(3)->format('Y-m-d');

        $endRange = $friday->copy()->addDays(60)->format('Y-m-d');
        DB::table('properties_rates_group')->insert([
            'property_id' => $this->propertyId,
            'name_of_price' => 'Test Weekly',
            'type_of_price' => 'weekly',
            'start_date' => $start,
            'start_date_timestamp' => strtotime($start),
            'end_date' => $endRange,
            'end_date_timestamp' => strtotime($endRange),
            'monday_price' => 200,
            'tuesday_price' => 200,
            'wednesday_price' => 200,
            'thrusday_price' => 200,  // schema typo preserved
            'friday_price' => 250,
            'saturday_price' => 250,
            'sunday_price' => 200,
            'min_stay' => 1,
        ]);

        $quote = $this->quote($start, $end);

        $this->assertTrue($quote->valid, 'Quote should be valid; errors: ' . implode('; ', $quote->errors));
        // Fri 250 + Sat 250 + Sun 200 = 700
        $this->assertSame(700.0, $quote->subtotal_nights_only);
    }

    public function test_single_date_override_wins_over_seasonal(): void
    {
        $start = now()->addDays(45)->format('Y-m-d');
        $christmasEve = now()->addDays(46)->format('Y-m-d');
        $end = now()->addDays(47)->format('Y-m-d');  // 2 nights: start, christmasEve

        // Seasonal range covering both dates at $200
        DB::table('properties_rates_group')->insert([
            'property_id' => $this->propertyId,
            'name_of_price' => 'Holiday Season',
            'type_of_price' => 'default',
            'start_date' => $start,
            'start_date_timestamp' => strtotime($start),
            'end_date' => $end,
            'end_date_timestamp' => strtotime($end),
            'price' => 200,
            'min_stay' => 1,
        ]);

        // Single-date override on the second night
        DB::table('properties_rates_group')->insert([
            'property_id' => $this->propertyId,
            'name_of_price' => 'Christmas Eve Premium',
            'type_of_price' => 'default',
            'start_date' => $christmasEve,
            'start_date_timestamp' => strtotime($christmasEve),
            'end_date' => $christmasEve,
            'end_date_timestamp' => strtotime($christmasEve),
            'price' => 500,
            'min_stay' => 1,
        ]);

        $quote = $this->quote($start, $end);

        $this->assertTrue($quote->valid, 'Quote should be valid; errors: ' . implode('; ', $quote->errors));
        $this->assertSame(700.0, $quote->subtotal_nights_only);  // 200 + 500
    }

    public function test_weekly_los_discount_applied_at_7_plus_nights(): void
    {
        $start = now()->addDays(30)->format('Y-m-d');
        $end = now()->addDays(37)->format('Y-m-d');  // 7 nights

        $endRange = now()->addDays(60)->format('Y-m-d');
        DB::table('properties_rates_group')->insert([
            'property_id' => $this->propertyId,
            'name_of_price' => 'Weekly Discount Season',
            'type_of_price' => 'default',
            'start_date' => $start,
            'start_date_timestamp' => strtotime($start),
            'end_date' => $endRange,
            'end_date_timestamp' => strtotime($endRange),
            'price' => 200,
            'discount_weekly' => 10,
            'min_stay' => 1,
        ]);

        $quote = $this->quote($start, $end);

        $this->assertTrue($quote->valid, 'Quote should be valid; errors: ' . implode('; ', $quote->errors));
        $this->assertSame(7, $quote->nights);
        $this->assertSame(1400.0, $quote->subtotal_nights_only);  // 7 × 200
        $this->assertNotNull($quote->los_discount);
        $this->assertSame('Weekly stay discount', $quote->los_discount['label']);
        $this->assertSame(10, $quote->los_discount['percent']);
        $this->assertSame(140.0, $quote->los_discount['amount']);  // 10% of 1400
    }

    public function test_monthly_los_discount_supersedes_weekly_at_28_plus_nights(): void
    {
        $start = now()->addDays(30)->format('Y-m-d');
        $end = now()->addDays(60)->format('Y-m-d');  // 30 nights

        $endRange = now()->addDays(90)->format('Y-m-d');
        DB::table('properties_rates_group')->insert([
            'property_id' => $this->propertyId,
            'name_of_price' => 'Monthly Discount Season',
            'type_of_price' => 'default',
            'start_date' => $start,
            'start_date_timestamp' => strtotime($start),
            'end_date' => $endRange,
            'end_date_timestamp' => strtotime($endRange),
            'price' => 200,
            'discount_weekly' => 10,
            'discount_monthly' => 20,
            'min_stay' => 1,
        ]);

        $quote = $this->quote($start, $end);

        $this->assertTrue($quote->valid, 'Quote should be valid; errors: ' . implode('; ', $quote->errors));
        $this->assertSame(30, $quote->nights);
        $this->assertNotNull($quote->los_discount);
        $this->assertSame('Monthly stay discount', $quote->los_discount['label']);
        $this->assertSame(20, $quote->los_discount['percent']);
        $this->assertSame(1200.0, $quote->los_discount['amount']);  // 20% of 6000
    }

    public function test_property_checkin_day_rule_rejects_non_matching_weekday(): void
    {
        // Set property to require Saturday check-in (dayOfWeek = '6')
        \DB::table('properties')->where('id', $this->propertyId)->update(['checkin_day' => '6']);

        // Pick a Monday for check-in (deliberately not Saturday)
        $monday = now()->next(\Carbon\Carbon::MONDAY)->addWeeks(2);
        $start = $monday->format('Y-m-d');
        $end = $monday->copy()->addDays(3)->format('Y-m-d');

        $quote = $this->quote($start, $end);

        $this->assertFalse($quote->valid, 'Quote should be invalid when check-in day rule violated');
        $this->assertNotEmpty($quote->errors);
        $this->assertStringContainsString('Saturday', $quote->errors[0]);

        // BC adapter should map this to 'checkin-checkout-day' status
        $property = \App\Models\Property::find($this->propertyId);
        $result = \Helper::getGrossAmountData($property, $start, $end);
        $this->assertSame('checkin-checkout-day', $result['status']);
    }

    public function test_property_checkin_day_rule_accepts_matching_weekday(): void
    {
        // Set property to require Saturday check-in
        \DB::table('properties')->where('id', $this->propertyId)->update(['checkin_day' => '6']);

        // Pick a Saturday for check-in
        $saturday = now()->next(\Carbon\Carbon::SATURDAY)->addWeeks(2);
        $start = $saturday->format('Y-m-d');
        $end = $saturday->copy()->addDays(3)->format('Y-m-d');

        $quote = $this->quote($start, $end);

        $this->assertTrue($quote->valid, 'Quote should be valid; errors: ' . implode('; ', $quote->errors));
    }

    public function test_legacy_adapter_returns_expected_shape(): void
    {
        $start = now()->addDays(30)->format('Y-m-d');
        $end = now()->addDays(33)->format('Y-m-d');  // 3 nights

        $property = \App\Models\Property::find($this->propertyId);
        $result = \Helper::getGrossAmountData($property, $start, $end);

        // Legacy contract shape (preserved for BC):
        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('gross_amount', $result);
        $this->assertArrayHasKey('total_night', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('extra_amount', $result);

        $this->assertTrue($result['status'] === true, 'Happy path status should be boolean true');
        $this->assertSame(600.0, (float) $result['gross_amount']);  // 3 × 200
        $this->assertSame(3, $result['total_night']);
        $this->assertSame('', $result['message']);
        $this->assertSame(0, $result['extra_amount']);
    }

    public function test_weekend_rate_applies_when_no_explicit_rule_covers_the_night(): void
    {
        DB::table('properties')->where('id', $this->propertyId)->update([
            'weekend_rate' => 300,
            'weekend_days' => '5,6',
        ]);

        // Pick a Thursday so the 3 nights span Thu (200) / Fri (300) / Sat (300).
        $thursday = now()->next(\Carbon\Carbon::THURSDAY)->addWeeks(2);
        $start = $thursday->format('Y-m-d');
        $end = $thursday->copy()->addDays(3)->format('Y-m-d');

        $quote = $this->quote($start, $end);
        $this->assertTrue($quote->valid, 'Quote should be valid; errors: ' . implode('; ', $quote->errors));
        $this->assertSame(800.0, $quote->subtotal_nights_only);  // 200 + 300 + 300
    }

    public function test_weekend_rate_yields_to_explicit_range_rule_in_booking(): void
    {
        DB::table('properties')->where('id', $this->propertyId)->update([
            'weekend_rate' => 300,
            'weekend_days' => '5,6',
        ]);

        // Pick a Friday + 1 night.
        $friday = now()->next(\Carbon\Carbon::FRIDAY)->addWeeks(2);
        $start = $friday->format('Y-m-d');
        $end = $friday->copy()->addDay()->format('Y-m-d');

        DB::table('properties_rates_group')->insert([
            'property_id' => $this->propertyId,
            'name_of_price' => 'Summer Friday',
            'type_of_price' => 'default',
            'start_date' => $start,
            'start_date_timestamp' => strtotime($start),
            'end_date' => $end,
            'end_date_timestamp' => strtotime($end),
            'price' => 500,
            'min_stay' => 1,
        ]);

        $quote = $this->quote($start, $end);
        $this->assertTrue($quote->valid, 'Quote should be valid; errors: ' . implode('; ', $quote->errors));
        $this->assertSame(500.0, $quote->subtotal_nights_only, 'range rule wins over weekend preset');
    }
}

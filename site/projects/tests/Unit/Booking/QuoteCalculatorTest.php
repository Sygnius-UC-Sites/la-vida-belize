<?php

namespace Tests\Unit\Booking;

use App\Models\Property;
use App\Services\Booking\QuoteCalculator;
use App\Services\Booking\QuoteInput;
use Tests\TestCase;

class QuoteCalculatorTest extends TestCase
{
    public function test_returns_invalid_quote_when_property_not_found(): void
    {
        $calc = new QuoteCalculator();
        $input = new QuoteInput(
            property_id: 999999,
            check_in: \Carbon\Carbon::today()->addDays(30)->toDateString(),
            check_out: \Carbon\Carbon::today()->addDays(34)->toDateString(),
            adults: 2,
        );

        $quote = $calc->calculate($input);

        $this->assertFalse($quote->valid);
        $this->assertContains('Property not found', $quote->errors);
    }

    public function test_returns_quote_object_when_property_exists(): void
    {
        // Use property id=1 which we know exists from the seeded data.
        $calc = new QuoteCalculator();
        $input = new QuoteInput(
            property_id: 1,
            check_in: \Carbon\Carbon::today()->addDays(30)->toDateString(),
            check_out: \Carbon\Carbon::today()->addDays(34)->toDateString(),
            adults: 2,
        );

        $quote = $calc->calculate($input);

        $this->assertNotNull($quote);
    }

    public function test_rejects_check_out_before_check_in(): void
    {
        $calc = new QuoteCalculator();
        $input = new QuoteInput(
            property_id: 1,
            check_in: \Carbon\Carbon::today()->addDays(35)->toDateString(),
            check_out: \Carbon\Carbon::today()->addDays(30)->toDateString(),
            adults: 2,
        );
        $quote = $calc->calculate($input);
        $this->assertFalse($quote->valid);
        $this->assertContains('Check-out must be after check-in', $quote->errors);
    }

    public function test_rejects_check_in_in_the_past(): void
    {
        $calc = new QuoteCalculator();
        $input = new QuoteInput(
            property_id: 1,
            check_in: '2020-01-01',
            check_out: '2020-01-05',
            adults: 2,
        );
        $quote = $calc->calculate($input);
        $this->assertFalse($quote->valid);
        $this->assertContains('Check-in cannot be in the past', $quote->errors);
    }

    public function test_computes_nights_count_correctly(): void
    {
        $calc = new QuoteCalculator();
        $input = new QuoteInput(
            property_id: 1,
            check_in: \Carbon\Carbon::today()->addDays(30)->toDateString(),
            check_out: \Carbon\Carbon::today()->addDays(34)->toDateString(),
            adults: 2,
        );
        $quote = $calc->calculate($input);
        $this->assertSame(4, $quote->nights);
    }

    public function test_rejects_same_day_checkin_and_checkout(): void
    {
        $sameDay = \Carbon\Carbon::today()->addDays(30)->toDateString();
        $calc = new QuoteCalculator();
        $input = new QuoteInput(
            property_id: 1,
            check_in: $sameDay,
            check_out: $sameDay,
            adults: 2,
        );
        $quote = $calc->calculate($input);
        $this->assertFalse($quote->valid);
        $this->assertContains('Check-out must be after check-in', $quote->errors);
    }

    public function test_accepts_check_in_today(): void
    {
        $today = \Carbon\Carbon::today()->toDateString();
        $tomorrow = \Carbon\Carbon::today()->addDay()->toDateString();
        $calc = new QuoteCalculator();
        $input = new QuoteInput(
            property_id: 1,
            check_in: $today,
            check_out: $tomorrow,
            adults: 2,
        );
        $quote = $calc->calculate($input);
        // Should NOT have a 'Check-in cannot be in the past' error
        $this->assertNotContains('Check-in cannot be in the past', $quote->errors);
        $this->assertSame(1, $quote->nights);
    }

    public function test_rejects_dates_blocked_by_existing_booking(): void
    {
        // Insert a confirmed booking for property 1 covering today+40..today+45
        $blockStart = \Carbon\Carbon::today()->addDays(40)->toDateString();
        $blockEnd = \Carbon\Carbon::today()->addDays(45)->toDateString();
        \DB::table('booking_requests')->insert([
            'property_id' => 1,
            'name' => 'Test Guest',
            'email' => 'test@example.com',
            'checkin' => $blockStart,
            'checkout' => $blockEnd,
            'total_guests' => 2,
            'gross_amount' => 0,
            'total_night' => 5,
            'sub_amount' => 0,
            'total_amount' => 0,
            'after_total_fees' => '[]',
            'before_total_fees' => '[]',
            'request_id' => 'blocked-dates-test',
            'booking_status' => 'booked',
            'payment_status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $calc = new QuoteCalculator();
        $input = new QuoteInput(
            property_id: 1,
            check_in: \Carbon\Carbon::today()->addDays(42)->toDateString(),
            check_out: \Carbon\Carbon::today()->addDays(44)->toDateString(),
            adults: 2,
        );
        $quote = $calc->calculate($input);

        $this->assertFalse($quote->valid);
        $this->assertContains('Selected dates include unavailable nights', $quote->errors);

        // Cleanup
        \DB::table('booking_requests')->where('email', 'test@example.com')->delete();
    }

    public function test_rejects_when_nights_below_property_min_stay(): void
    {
        \DB::table('properties')->where('id', 1)->update(['min_stay' => 5]);

        $calc = new QuoteCalculator();
        $input = new QuoteInput(
            property_id: 1,
            check_in: \Carbon\Carbon::today()->addDays(60)->toDateString(),
            check_out: \Carbon\Carbon::today()->addDays(63)->toDateString(), // 3 nights < 5
            adults: 2,
        );
        $quote = $calc->calculate($input);

        $this->assertFalse($quote->valid);
        $this->assertStringContainsString('Minimum stay', implode(' ', $quote->errors));

        // Cleanup
        \DB::table('properties')->where('id', 1)->update(['min_stay' => null]);
    }

    public function test_rejects_when_nights_above_property_max_stay(): void
    {
        \DB::table('properties')->where('id', 1)->update(['max_stay' => 7]);

        $calc = new QuoteCalculator();
        $input = new QuoteInput(
            property_id: 1,
            check_in: \Carbon\Carbon::today()->addDays(60)->toDateString(),
            check_out: \Carbon\Carbon::today()->addDays(74)->toDateString(), // 14 nights > 7
            adults: 2,
        );
        $quote = $calc->calculate($input);

        $this->assertFalse($quote->valid);
        $this->assertStringContainsString('Maximum stay', implode(' ', $quote->errors));

        // Cleanup
        \DB::table('properties')->where('id', 1)->update(['max_stay' => null]);
    }

    public function test_uses_property_base_price_when_no_rate_rows_exist(): void
    {
        \DB::table('properties')->where('id', 1)->update(['standard_rate' => '150']);
        \DB::table('property_rates')->where('property_id', 1)->delete();
        \DB::table('properties_rates_group')->where('property_id', 1)->delete();

        $checkIn = \Carbon\Carbon::today()->addDays(80)->toDateString();
        $checkOut = \Carbon\Carbon::today()->addDays(83)->toDateString(); // 3 nights

        $calc = new QuoteCalculator();
        $input = new QuoteInput(
            property_id: 1,
            check_in: $checkIn,
            check_out: $checkOut,
            adults: 2,
        );
        $quote = $calc->calculate($input);

        // Look for nights line in breakdown
        $nightsLine = collect($quote->breakdown)->firstWhere('category', 'nights');
        $this->assertNotNull($nightsLine);
        $this->assertSame(450.0, $nightsLine['amount']); // 3 × 150
    }

    public function test_per_day_rate_overrides_property_base_price(): void
    {
        \DB::table('properties')->where('id', 1)->update(['standard_rate' => '150']);
        \DB::table('property_rates')->where('property_id', 1)->delete();
        \DB::table('properties_rates_group')->where('property_id', 1)->delete();

        $checkIn = \Carbon\Carbon::today()->addDays(85);
        $middleNight = $checkIn->copy()->addDay()->toDateString();

        // Per-day rate of 200 for the middle night only
        \DB::table('property_rates')->insert([
            'property_id' => '1',
            'single_date' => $middleNight,
            'single_date_timestamp' => strtotime($middleNight),
            'price' => 200,
            'is_available' => '1',
            'platform_type' => 'airbnb',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $calc = new QuoteCalculator();
        $input = new QuoteInput(
            property_id: 1,
            check_in: $checkIn->toDateString(),
            check_out: $checkIn->copy()->addDays(3)->toDateString(), // 3 nights: 150 + 200 + 150 = 500
            adults: 2,
        );
        $quote = $calc->calculate($input);

        $nightsLine = collect($quote->breakdown)->firstWhere('category', 'nights');
        $this->assertSame(500.0, $nightsLine['amount']);

        // Cleanup
        \DB::table('property_rates')->where('property_id', 1)->delete();
    }

    public function test_date_range_rule_per_weekday_overrides_per_day(): void
    {
        \DB::table('properties')->where('id', 1)->update(['standard_rate' => '150']);
        \DB::table('property_rates')->where('property_id', 1)->delete();
        \DB::table('properties_rates_group')->where('property_id', 1)->delete();

        // Find the next Saturday at least 90 days out
        $sat = \Carbon\Carbon::today()->addDays(90)->next(\Carbon\Carbon::SATURDAY);
        $sun = $sat->copy()->addDay();
        $rangeStart = $sat->copy()->subDays(7)->toDateString();
        $rangeEnd = $sat->copy()->addDays(14)->toDateString();

        // Per-weekday rule: 300 on Saturdays in this 3-week range, default $200 other days
        \DB::table('properties_rates_group')->insert([
            'property_id' => '1',
            'name_of_price' => 'Test Weekend',
            'type_of_price' => 'weekend',
            'start_date' => $rangeStart,
            'start_date_timestamp' => strtotime($rangeStart),
            'end_date' => $rangeEnd,
            'end_date_timestamp' => strtotime($rangeEnd),
            'saturday_price' => 300,
            'price' => 200,
            'is_available' => '1',
            'platform_type' => 'airbnb',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $calc = new QuoteCalculator();
        $input = new QuoteInput(
            property_id: 1,
            check_in: $sat->toDateString(),     // Sat
            check_out: $sun->copy()->addDay()->toDateString(),   // 2 nights: Sat 300 + Sun 200 = 500
            adults: 2,
        );
        $quote = $calc->calculate($input);

        $nightsLine = collect($quote->breakdown)->firstWhere('category', 'nights');
        $this->assertSame(500.0, $nightsLine['amount']);

        // Cleanup
        \DB::table('properties_rates_group')->where('property_id', 1)->delete();
    }

    public function test_single_day_override_in_rate_group_takes_top_priority(): void
    {
        // Layer 1: properties_rates_group row where start_date == end_date == night
        \DB::table('properties')->where('id', 1)->update(['standard_rate' => '150']);
        \DB::table('property_rates')->where('property_id', 1)->delete();
        \DB::table('properties_rates_group')->where('property_id', 1)->delete();

        $checkIn = \Carbon\Carbon::today()->addDays(95);
        $overrideDate = $checkIn->copy()->addDay()->toDateString();

        // First: a date-range rule covering all 3 nights at $200
        \DB::table('properties_rates_group')->insert([
            'property_id' => '1',
            'name_of_price' => 'Range Rule',
            'type_of_price' => 'range',
            'start_date' => $checkIn->toDateString(),
            'start_date_timestamp' => strtotime($checkIn->toDateString()),
            'end_date' => $checkIn->copy()->addDays(2)->toDateString(),
            'end_date_timestamp' => strtotime($checkIn->copy()->addDays(2)->toDateString()),
            'price' => 200,
            'is_available' => '1',
            'platform_type' => 'airbnb',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Then: a single-day override for the middle night at $500 (Layer 1)
        \DB::table('properties_rates_group')->insert([
            'property_id' => '1',
            'name_of_price' => 'Single Day Override',
            'type_of_price' => 'override',
            'start_date' => $overrideDate,
            'start_date_timestamp' => strtotime($overrideDate),
            'end_date' => $overrideDate,
            'end_date_timestamp' => strtotime($overrideDate),
            'price' => 500,
            'is_available' => '1',
            'platform_type' => 'airbnb',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $calc = new QuoteCalculator();
        $input = new QuoteInput(
            property_id: 1,
            check_in: $checkIn->toDateString(),
            check_out: $checkIn->copy()->addDays(3)->toDateString(), // 3 nights: 200 + 500 + 200 = 900
            adults: 2,
        );
        $quote = $calc->calculate($input);

        $nightsLine = collect($quote->breakdown)->firstWhere('category', 'nights');
        $this->assertSame(900.0, $nightsLine['amount']);

        // Cleanup
        \DB::table('properties_rates_group')->where('property_id', 1)->delete();
    }

    public function test_date_range_single_price_used_when_no_weekday_or_per_day(): void
    {
        // Layer 3: date-range rule's single 'price' column (no per-weekday, no per-day row)
        \DB::table('properties')->where('id', 1)->update(['standard_rate' => '150']);
        \DB::table('property_rates')->where('property_id', 1)->delete();
        \DB::table('properties_rates_group')->where('property_id', 1)->delete();

        $checkIn = \Carbon\Carbon::today()->addDays(100);

        // Date-range rule with only the single 'price' column populated (no weekday cols)
        \DB::table('properties_rates_group')->insert([
            'property_id' => '1',
            'name_of_price' => 'Plain Range',
            'type_of_price' => 'range',
            'start_date' => $checkIn->toDateString(),
            'start_date_timestamp' => strtotime($checkIn->toDateString()),
            'end_date' => $checkIn->copy()->addDays(5)->toDateString(),
            'end_date_timestamp' => strtotime($checkIn->copy()->addDays(5)->toDateString()),
            'price' => 175,
            // No saturday_price, monday_price, etc.
            'is_available' => '1',
            'platform_type' => 'airbnb',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $calc = new QuoteCalculator();
        $input = new QuoteInput(
            property_id: 1,
            check_in: $checkIn->toDateString(),
            check_out: $checkIn->copy()->addDays(3)->toDateString(), // 3 nights × 175 = 525
            adults: 2,
        );
        $quote = $calc->calculate($input);

        $nightsLine = collect($quote->breakdown)->firstWhere('category', 'nights');
        $this->assertSame(525.0, $nightsLine['amount']);

        // Cleanup
        \DB::table('properties_rates_group')->where('property_id', 1)->delete();
    }

    public function test_adds_cleaning_fee_line_item(): void
    {
        \DB::table('properties')->where('id', 1)->update(['cleaning_fee' => 75, 'standard_rate' => '100']);

        $calc = new QuoteCalculator();
        $input = new QuoteInput(
            property_id: 1,
            check_in: \Carbon\Carbon::today()->addDays(110)->toDateString(),
            check_out: \Carbon\Carbon::today()->addDays(113)->toDateString(),
            adults: 2,
        );
        $quote = $calc->calculate($input);

        $cleaning = collect($quote->breakdown)->firstWhere('category', 'cleaning_fee');
        $this->assertNotNull($cleaning);
        $this->assertSame(75.0, $cleaning['amount']);
    }

    public function test_adds_extra_guest_fee_when_over_threshold(): void
    {
        \DB::table('properties')->where('id', 1)->update([
            'standard_rate' => '100',
            'no_of_guest' => '4',
            'sleeps' => 6,
            'guest_fee' => '25',
        ]);

        $calc = new QuoteCalculator();
        $input = new QuoteInput(
            property_id: 1,
            check_in: \Carbon\Carbon::today()->addDays(115)->toDateString(),
            check_out: \Carbon\Carbon::today()->addDays(118)->toDateString(), // 3 nights
            adults: 5,    // 1 over threshold
            children: 1,  // 2 over threshold total
        );
        $quote = $calc->calculate($input);

        $extra = collect($quote->breakdown)->firstWhere('category', 'extra_guest_fee');
        $this->assertNotNull($extra);
        // 2 extra guests × $25/night × 3 nights = 150
        $this->assertSame(150.0, $extra['amount']);
    }

    public function test_adds_no_extra_guest_fee_when_at_or_under_threshold(): void
    {
        \DB::table('properties')->where('id', 1)->update([
            'standard_rate' => '100',
            'no_of_guest' => '4',
            'guest_fee' => '25',
        ]);

        $calc = new QuoteCalculator();
        $input = new QuoteInput(
            property_id: 1,
            check_in: \Carbon\Carbon::today()->addDays(120)->toDateString(),
            check_out: \Carbon\Carbon::today()->addDays(123)->toDateString(),
            adults: 4,
            children: 0,
        );
        $quote = $calc->calculate($input);

        $extra = collect($quote->breakdown)->firstWhere('category', 'extra_guest_fee');
        $this->assertNull($extra);
    }

    public function test_applies_weekly_los_discount_to_nights_only_by_default(): void
    {
        \DB::table('properties')->where('id', 1)->update([
            'standard_rate' => '100',
            'cleaning_fee' => 50,
            // Reset overrides to use global defaults
            'discount_applies_to_nights' => null,
            'discount_applies_to_cleaning_fee' => null,
        ]);
        \DB::table('property_rates')->where('property_id', 1)->delete();
        \DB::table('properties_rates_group')->where('property_id', 1)->delete();

        $checkIn = \Carbon\Carbon::today()->addDays(125);

        // Add a 7-day rate row with discount_weekly = 10%
        $rows = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $checkIn->copy()->addDays($i)->toDateString();
            $rows[] = [
                'property_id' => '1',
                'single_date' => $d,
                'single_date_timestamp' => strtotime($d),
                'price' => 100,
                'is_available' => '1',
                'platform_type' => 'airbnb',
                'currency' => 'USD',
                'discount_weekly' => '10',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        \DB::table('property_rates')->insert($rows);

        $calc = new QuoteCalculator();
        $input = new QuoteInput(
            property_id: 1,
            check_in: $checkIn->toDateString(),
            check_out: $checkIn->copy()->addDays(7)->toDateString(), // 7 nights — qualifies for weekly
            adults: 2,
        );
        $quote = $calc->calculate($input);

        $discount = collect($quote->breakdown)->firstWhere('category', 'los_discount');
        $this->assertNotNull($discount, 'Expected an los_discount line in breakdown');
        // 10% of 700 (nights only, since cleaning is not in default discount base) = -70
        $this->assertSame(-70.0, $discount['amount']);

        // Cleanup
        \DB::table('property_rates')->where('property_id', 1)->delete();
    }

    public function test_subtotal_sums_breakdown_correctly(): void
    {
        \DB::table('properties')->where('id', 1)->update([
            'standard_rate' => '100',
            'cleaning_fee' => 50,
            'no_of_guest' => '4',
            'guest_fee' => '0',
            'tax' => 0,
        ]);

        $calc = new QuoteCalculator();
        $input = new QuoteInput(
            property_id: 1,
            check_in: \Carbon\Carbon::today()->addDays(140)->toDateString(),
            check_out: \Carbon\Carbon::today()->addDays(143)->toDateString(), // 3 nights × 100 + 50 cleaning = 350
            adults: 2,
        );
        $quote = $calc->calculate($input);

        $this->assertSame(350.0, $quote->subtotal);
    }

    public function test_applies_amount_coupon_correctly(): void
    {
        \DB::table('properties')->where('id', 1)->update([
            'standard_rate' => '100', 'cleaning_fee' => 50, 'tax' => 0,
        ]);
        \DB::table('coupons')->insert([
            'name' => 'Test Coupon',
            'code' => 'SAVE25',
            'amount' => '25',
            'type' => 'amount',
            'property_id' => null,
            'start_date' => '2026-01-01',
            'end_date' => '2027-12-31',
            'max_uses_per_customer' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $calc = new QuoteCalculator();
        $input = new QuoteInput(
            property_id: 1,
            check_in: \Carbon\Carbon::today()->addDays(145)->toDateString(),
            check_out: \Carbon\Carbon::today()->addDays(148)->toDateString(),
            adults: 2,
            coupon_code: 'SAVE25',
        );
        $quote = $calc->calculate($input);

        $this->assertNotNull($quote->discount);
        $this->assertSame(-25.0, $quote->discount->amount);

        \DB::table('coupons')->where('code', 'SAVE25')->delete();
    }

    public function test_rejects_invalid_coupon_code_with_error(): void
    {
        \DB::table('properties')->where('id', 1)->update(['standard_rate' => '100']);

        $calc = new QuoteCalculator();
        $input = new QuoteInput(
            property_id: 1,
            check_in: \Carbon\Carbon::today()->addDays(150)->toDateString(),
            check_out: \Carbon\Carbon::today()->addDays(152)->toDateString(),
            adults: 2,
            coupon_code: 'NONEXISTENT',
        );
        $quote = $calc->calculate($input);

        $this->assertContains('Coupon code not found', $quote->errors);
    }

    public function test_applies_tax_only_to_taxable_categories(): void
    {
        // Reset overrides to use global defaults
        // Phase 3: tax_applies_to_hottub_addon / firewood_addon columns dropped — typed addons removed.
        \DB::table('properties')->where('id', 1)->update([
            'standard_rate' => '100',
            'cleaning_fee' => 50,
            'tax' => 10,    // 10% tax
            'tax_applies_to_nights' => null,
            'tax_applies_to_cleaning_fee' => null,
        ]);
        // Globals: nights=true, cleaning=true, hottub=false (per Task 6 seed)

        $calc = new QuoteCalculator();
        $input = new QuoteInput(
            property_id: 1,
            check_in: \Carbon\Carbon::today()->addDays(155)->toDateString(),
            check_out: \Carbon\Carbon::today()->addDays(158)->toDateString(), // 3 nights × 100 = 300; cleaning 50; subtotal 350
            adults: 2,
        );
        $quote = $calc->calculate($input);

        // Tax applies to nights (300) + cleaning (50) = 350; 10% = 35
        $this->assertSame(35.0, $quote->tax);
    }

    public function test_adds_damage_deposit_to_total_as_refundable_line(): void
    {
        \DB::table('properties')->where('id', 1)->update([
            'standard_rate' => '100', 'cleaning_fee' => 0, 'tax' => 0,
            'security_deposit_amount' => 200,
            'security_deposit_charge_at_booking' => 1,
        ]);

        $calc = new QuoteCalculator();
        $input = new QuoteInput(
            property_id: 1,
            check_in: \Carbon\Carbon::today()->addDays(160)->toDateString(),
            check_out: \Carbon\Carbon::today()->addDays(162)->toDateString(),
            adults: 2,
        );
        $quote = $calc->calculate($input);

        $deposit = collect($quote->breakdown)->firstWhere('category', 'damage_deposit');
        $this->assertNotNull($deposit);
        $this->assertSame(200.0, $deposit['amount']);
        $this->assertTrue($deposit['refundable']);
        // Total = nights (200) + deposit (200)
        $this->assertSame(400.0, $quote->total);
    }

    public function test_full_payment_when_lead_time_below_2_installment_threshold(): void
    {
        \DB::table('properties')->where('id', 1)->update([
            'standard_rate' => '100', 'cleaning_fee' => 0, 'tax' => 0, 'security_deposit_amount' => 0,
            // Reset overrides to use globals (full / 46 / 115)
            'installment_plan' => null,
        ]);
        \DB::table('basic_settings')->where('name', 'installment_plan')->update(['value' => 'full']);

        $calc = new QuoteCalculator();
        $input = new QuoteInput(
            property_id: 1,
            check_in: \Carbon\Carbon::today()->addDays(10)->toDateString(),
            check_out: \Carbon\Carbon::today()->addDays(13)->toDateString(),
            adults: 2,
        );
        $quote = $calc->calculate($input);

        $this->assertCount(1, $quote->installments);
        $this->assertSame(1, $quote->installments[0]['sequence']);
        $this->assertSame($quote->total, $quote->installments[0]['amount']);
    }

    public function test_two_installment_schedule_when_lead_time_meets_threshold(): void
    {
        \DB::table('properties')->where('id', 1)->update([
            'standard_rate' => '100', 'cleaning_fee' => 0, 'tax' => 0, 'security_deposit_amount' => 0,
            'installment_plan' => null,
        ]);
        // Set globals: 2-installment plan, 65/35 split, 2nd due in 30 days, threshold 46
        \DB::table('basic_settings')->where('name', 'installment_plan')->update(['value' => 'two']);

        $calc = new QuoteCalculator();
        $input = new QuoteInput(
            property_id: 1,
            check_in: \Carbon\Carbon::today()->addDays(60)->toDateString(),
            check_out: \Carbon\Carbon::today()->addDays(63)->toDateString(),
            adults: 2,
        );
        $quote = $calc->calculate($input);

        $this->assertCount(2, $quote->installments);
        // total = 300
        $this->assertSame(300.0, $quote->total);
        // First = 65% = 195, second = 35% = 105
        $this->assertEqualsWithDelta(195.0, $quote->installments[0]['amount'], 0.01);
        $this->assertEqualsWithDelta(105.0, $quote->installments[1]['amount'], 0.01);

        // Reset globals
        \DB::table('basic_settings')->where('name', 'installment_plan')->update(['value' => 'full']);
    }

    public function test_integration_runs_against_real_seeded_property(): void
    {
        // No DB mutation — use whatever's in property id=1 right now
        $calc = new QuoteCalculator();
        $input = new QuoteInput(
            property_id: 1,
            check_in: \Carbon\Carbon::today()->addDays(180)->toDateString(),
            check_out: \Carbon\Carbon::today()->addDays(184)->toDateString(),
            adults: 2,
        );
        $quote = $calc->calculate($input);

        $this->assertTrue($quote->valid, 'Quote should be valid; errors: ' . implode(', ', $quote->errors));
        $this->assertSame(4, $quote->nights);
        $this->assertGreaterThan(0, $quote->total);
        $this->assertNotEmpty($quote->breakdown);
        $this->assertNotEmpty($quote->installments);
    }
}

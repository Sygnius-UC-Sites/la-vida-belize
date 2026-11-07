<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithoutMiddleware;

class BookingQuoteTest extends TestCase
{
    use WithoutMiddleware;  // skip CSRF for the test

    public function test_valid_request_returns_quote_json(): void
    {
        $response = $this->postJson('/api/booking/quote', [
            'property_id' => 1,
            'check_in' => \Carbon\Carbon::today()->addDays(30)->toDateString(),
            'check_out' => \Carbon\Carbon::today()->addDays(33)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'pets' => 0,
            'addons' => ['hottub' => false, 'firewood' => 0, 'rv_hookup' => 0, 'extra_visitors' => 0],
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'valid',
            'quote' => ['nights', 'breakdown', 'subtotal', 'tax', 'damage_deposit', 'total', 'installments', 'errors', 'valid'],
            'errors',
        ]);
        $response->assertJsonPath('valid', true);
        $response->assertJsonPath('quote.nights', 3);
    }

    public function test_invalid_dates_return_errors_in_response(): void
    {
        $response = $this->postJson('/api/booking/quote', [
            'property_id' => 1,
            'check_in' => '2026-09-05',
            'check_out' => '2026-09-01', // before check_in
            'adults' => 2,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('valid', false);
        $response->assertJsonFragment(['Check-out must be after check-in']);
    }

    public function test_missing_required_field_returns_422(): void
    {
        $response = $this->postJson('/api/booking/quote', [
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-05',
            // Missing property_id and adults
        ]);

        $response->assertStatus(422);
    }

    public function test_quote_endpoint_applies_global_coupon(): void
    {
        $couponId = \Illuminate\Support\Facades\DB::table('coupons')->insertGetId([
            'name' => '_phase9_coupon_test_',
            'code' => 'PHASE9TESTAPI',
            'amount' => '10',
            'type' => 'percent',
            'property_id' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        try {
            $response = $this->postJson('/api/booking/quote', [
                'property_id' => 1,
                'check_in' => \Carbon\Carbon::today()->addDays(60)->toDateString(),
                'check_out' => \Carbon\Carbon::today()->addDays(63)->toDateString(),
                'adults' => 2,
                'coupon_code' => 'PHASE9TESTAPI',
            ]);

            $response->assertStatus(200);
            $response->assertJsonPath('valid', true);
            $couponDiscount = $response->json('quote.coupon_discount');
            $this->assertNotNull($couponDiscount, 'coupon_discount should be present in the quote response');
            $this->assertSame('PHASE9TESTAPI', $couponDiscount['code']);
            $this->assertGreaterThan(0, (float) $couponDiscount['amount']);
        } finally {
            \Illuminate\Support\Facades\DB::table('coupons')->where('id', $couponId)->delete();
        }
    }

    public function test_quote_endpoint_returns_error_on_unknown_coupon(): void
    {
        // QuoteCalculator pushes the coupon error to errors[] but does NOT
        // invalidate the quote — the rest of the calculation is still valid,
        // just without the discount. So we assert the error is present in
        // either the top-level errors[] or quote.errors[].
        $response = $this->postJson('/api/booking/quote', [
            'property_id' => 1,
            'check_in' => \Carbon\Carbon::today()->addDays(60)->toDateString(),
            'check_out' => \Carbon\Carbon::today()->addDays(63)->toDateString(),
            'adults' => 2,
            'coupon_code' => 'NEVER-EXISTS-PHASE9',
        ]);

        $response->assertStatus(200);
        $errors = array_merge(
            (array) ($response->json('errors') ?? []),
            (array) ($response->json('quote.errors') ?? []),
        );
        $foundCouponError = collect($errors)->contains(fn ($m) => stripos((string) $m, 'coupon') !== false);
        $this->assertTrue($foundCouponError, 'Expected coupon-related error message in: ' . json_encode($errors));
        // The discount should NOT be applied — coupon_discount stays null
        $this->assertNull($response->json('quote.coupon_discount'));
    }
}

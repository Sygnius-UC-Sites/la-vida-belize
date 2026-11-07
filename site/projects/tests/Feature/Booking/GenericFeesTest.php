<?php

namespace Tests\Feature\Booking;

use App\Services\Booking\QuoteCalculator;
use App\Services\Booking\QuoteInput;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GenericFeesTest extends TestCase
{
    private int $propertyId;
    private QuoteCalculator $calc;
    private array $feeIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->propertyId = DB::table('properties')->insertGetId([
            'name' => 'Generic-Fees-Test ' . uniqid(),
            'seo_url' => 'gft-' . uniqid(),
            'instant_booking_button' => 'Book Now',
            'standard_rate' => 200,
            'no_of_guest' => 4,
            'type' => 'condo',
            'status' => 'true',
        ]);

        $this->calc = app(QuoteCalculator::class);
    }

    protected function tearDown(): void
    {
        if ($this->feeIds) {
            DB::table('property_fees')->whereIn('id', $this->feeIds)->delete();
        }
        DB::table('properties')->where('id', $this->propertyId)->delete();
        parent::tearDown();
    }

    private function insertFee(array $overrides): int
    {
        $id = DB::table('property_fees')->insertGetId(array_merge([
            'property_id' => $this->propertyId,
            'name'        => 'Test Fee',
            'rate'        => 20,
            'billing_type' => 'one_time',
            'taxable'     => 1,
            'applies_discount' => 0,
            'sort_order'  => 0,
            'status'      => 'active',
            'created_at'  => now(),
            'updated_at'  => now(),
        ], $overrides));
        $this->feeIds[] = $id;
        return $id;
    }

    private function quote(array $feeIds = [], int $pets = 0, int $nights = 3): \App\Services\Booking\Quote
    {
        $start = now()->addDays(30)->format('Y-m-d');
        $end   = now()->addDays(30 + $nights)->format('Y-m-d');
        return $this->calc->calculate(QuoteInput::fromArray([
            'property_id' => $this->propertyId,
            'check_in'    => $start,
            'check_out'   => $end,
            'adults'      => 2,
            'pets'        => $pets,
            'addons'      => ['fees' => $feeIds],
        ]));
    }

    public function test_per_day_addon_fee_multiplies_by_nights(): void
    {
        $id = $this->insertFee(['name' => 'Beach gear', 'rate' => 20, 'billing_type' => 'per_day']);
        $q = $this->quote([$id], 0, 3);

        $line = collect($q->breakdown)->firstWhere('category', 'addon_fee');
        $this->assertNotNull($line);
        $this->assertSame(60.0, (float)$line['amount']); // 20 × 3 nights
        $this->assertStringContainsString('Beach gear', $line['label']);
        $this->assertStringContainsString('3 nights', $line['label']);
    }

    public function test_one_time_addon_fee_charges_once(): void
    {
        $id = $this->insertFee(['name' => 'Late checkout', 'rate' => 50, 'billing_type' => 'one_time']);
        $q = $this->quote([$id], 0, 3);

        $line = collect($q->breakdown)->firstWhere('category', 'addon_fee');
        $this->assertNotNull($line);
        $this->assertSame(50.0, (float)$line['amount']);
        $this->assertSame('Late checkout', $line['label']);
    }

    public function test_multiple_addon_fees_iterate_in_sort_order(): void
    {
        $a = $this->insertFee(['name' => 'A', 'rate' => 5,  'sort_order' => 30]);
        $b = $this->insertFee(['name' => 'B', 'rate' => 10, 'sort_order' => 10]);
        $c = $this->insertFee(['name' => 'C', 'rate' => 15, 'sort_order' => 20]);

        $q = $this->quote([$a, $b, $c], 0, 3);
        $names = collect($q->breakdown)->where('category', 'addon_fee')->pluck('label')->all();
        $this->assertSame(['B', 'C', 'A'], $names);
    }

    public function test_inactive_fees_not_applied(): void
    {
        $id = $this->insertFee(['name' => 'Hidden', 'rate' => 99, 'status' => 'inactive']);
        $q = $this->quote([$id], 0, 3);
        $addon = collect($q->breakdown)->firstWhere('category', 'addon_fee');
        $this->assertNull($addon);
    }

    public function test_addon_fee_taxable_flag_respected(): void
    {
        $id = $this->insertFee(['name' => 'Non-taxable', 'rate' => 30, 'taxable' => 0]);
        $q = $this->quote([$id], 0, 3);
        $line = collect($q->breakdown)->firstWhere('category', 'addon_fee');
        $this->assertNotNull($line);
        $this->assertFalse((bool)$line['taxable']);
    }

    public function test_pet_fee_per_day_multiplies_by_nights(): void
    {
        DB::table('properties')->where('id', $this->propertyId)->update([
            'pet_fee' => 10,
            'pet_fee_interval' => 'per_day',
        ]);

        $q = $this->quote([], 2, 3); // 2 pets, 3 nights
        $line = collect($q->breakdown)->firstWhere('category', 'pet_fee');
        $this->assertNotNull($line);
        $this->assertSame(60.0, (float)$line['amount']); // 2 × 10 × 3
    }

    public function test_pet_fee_one_time_charges_once(): void
    {
        DB::table('properties')->where('id', $this->propertyId)->update([
            'pet_fee' => 10,
            'pet_fee_interval' => 'one_time',
        ]);

        $q = $this->quote([], 2, 3);
        $line = collect($q->breakdown)->firstWhere('category', 'pet_fee');
        $this->assertNotNull($line);
        $this->assertSame(20.0, (float)$line['amount']); // 2 × 10
    }

    public function test_ajax_quote_endpoint_applies_addons_fees(): void
    {
        $id = $this->insertFee(['name' => 'AJAX Test Fee', 'rate' => 30, 'billing_type' => 'per_day']);

        $start = now()->addDays(30)->format('Y-m-d');
        $end   = now()->addDays(33)->format('Y-m-d');

        $resp = $this->postJson('/api/booking/quote', [
            'property_id' => $this->propertyId,
            'check_in'    => $start,
            'check_out'   => $end,
            'adults'      => 2,
            'addons'      => ['fees' => [$id]],
        ]);
        $resp->assertOk();
        $resp->assertJsonPath('valid', true);

        $breakdown = collect($resp->json('quote.breakdown'));
        $line = $breakdown->firstWhere('category', 'addon_fee');
        $this->assertNotNull($line);
        $this->assertSame(90.0, (float)$line['amount']); // 30 × 3 nights
    }
}

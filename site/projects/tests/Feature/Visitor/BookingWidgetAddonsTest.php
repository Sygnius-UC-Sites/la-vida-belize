<?php

namespace Tests\Feature\Visitor;

use App\Models\Property;
use App\Support\PropertyUrl;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookingWidgetAddonsTest extends TestCase
{
    private int $propertyId;
    private string $seoUrl;
    private array $feeIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seoUrl = 'bw-addons-' . uniqid();
        $this->propertyId = DB::table('properties')->insertGetId([
            'name' => 'BW-Addons-Test ' . uniqid(),
            'seo_url' => $this->seoUrl,
            'instant_booking_button' => 'Book Now',
            'standard_rate' => 200,
            'no_of_guest' => 4,
            'type' => 'condo',
            'status' => 'true',
        ]);
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
            'name'        => 'Beach gear',
            'rate'        => 25,
            'billing_type' => 'per_day',
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

    private function propertyPath(): string
    {
        return PropertyUrl::path(Property::findOrFail($this->propertyId));
    }

    public function test_property_detail_keeps_optional_fees_for_the_checkout_step(): void
    {
        $this->insertFee(['name' => 'Beach gear rental', 'rate' => 25, 'billing_type' => 'per_day']);
        $this->insertFee(['name' => 'Late checkout', 'rate' => 50, 'billing_type' => 'one_time']);

        $resp = $this->get($this->propertyPath());
        $resp->assertOk();
        $resp->assertSee('id="booking-widget"', false);
        $resp->assertDontSee('data-fee-id=', false);
        $resp->assertDontSee('Beach gear rental', false);
        $resp->assertDontSee('Late checkout', false);
        $resp->assertDontSee('Add-ons (optional)', false);
    }

    public function test_property_detail_page_omits_fees_section_when_no_fees(): void
    {
        // No fees inserted
        $resp = $this->get($this->propertyPath());
        $resp->assertOk();
        // No data-fee-id attribute should appear (the JS class string `widget-fee-checkbox` IS in the script body, but no rendered input carries data-fee-id)
        $resp->assertDontSee('data-fee-id=', false);
        $resp->assertDontSee('Add-ons (optional)', false);
    }

    public function test_inactive_fees_not_rendered(): void
    {
        $this->insertFee(['name' => 'Hidden fee', 'rate' => 99, 'status' => 'inactive']);

        $resp = $this->get($this->propertyPath());
        $resp->assertOk();
        $resp->assertDontSee('Hidden fee', false);
        $resp->assertDontSee('data-fee-id=', false);
    }
}

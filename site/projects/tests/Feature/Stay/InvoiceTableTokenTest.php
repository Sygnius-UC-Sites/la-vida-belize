<?php

namespace Tests\Feature\Stay;

use App\Models\BookingRequest;
use App\Models\Property;
use App\Services\Stay\StayCommunicationsResolver;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 13J — {{invoice_table}} merge token + structured fee editor save flow.
 */
class InvoiceTableTokenTest extends TestCase
{
    private int $propertyId;
    private int $bookingId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->propertyId = DB::table('properties')->insertGetId([
            'name'    => 'P13J Test ' . uniqid(),
            'seo_url' => 'p13j-test-' . uniqid(),
            'instant_booking_button' => 'Book Now',
            'standard_rate' => 200,
            'no_of_guest'   => 4,
            'type'    => 'condo',
            'status'  => 'true',
        ]);

        $this->bookingId = DB::table('booking_requests')->insertGetId([
            'property_id'    => $this->propertyId,
            'checkin'        => '2026-10-01',
            'checkout'       => '2026-10-04',
            'name'           => 'Phase 13J Guest',
            'email'          => 'p13j@example.com',
            'total_night'    => 3,
            'gross_amount'   => 600,
            'total_amount'   => 720,
            'tax'            => 60,
            'discount'       => 0,
            'before_total_fees' => json_encode([['name' => 'Cleaning fee', 'amount' => 50]]),
            'after_total_fees'  => json_encode([['name' => 'Card processing', 'amount' => 10]]),
            'custom_before_total_fees' => json_encode([['product_name' => 'Beach gear', 'product_amount' => 60]]),
            'rental_aggrement_status' => 'false',
        ]);

        DB::table('basic_settings')->updateOrInsert(['name' => 'payment_currency'], ['value' => '$', 'updated_at' => now(), 'created_at' => now()]);
    }

    protected function tearDown(): void
    {
        DB::table('booking_requests')->where('id', $this->bookingId)->delete();
        DB::table('properties')->where('id', $this->propertyId)->delete();
        parent::tearDown();
    }

    public function test_phase13j_invoice_table_token_renders_all_fee_lines(): void
    {
        $resolver = new StayCommunicationsResolver(
            Property::find($this->propertyId),
            BookingRequest::find($this->bookingId),
        );

        $html = $resolver->expandMergeFields('Hello {{guest_name}}: {{invoice_table}}');

        // Spot-check that each fee line shows up
        $this->assertStringContainsString('Rental rate', $html);
        $this->assertStringContainsString('3 night', $html);
        $this->assertStringContainsString('Beach gear', $html);
        $this->assertStringContainsString('Cleaning fee', $html);
        $this->assertStringContainsString('Card processing', $html);
        $this->assertStringContainsString('Lodging tax', $html);
        $this->assertStringContainsString('60.00', $html); // tax
        $this->assertStringContainsString('Total', $html);
        $this->assertStringContainsString('720.00', $html); // total_amount
    }

    public function test_phase13j_invoice_table_renders_empty_when_no_fees(): void
    {
        // Wipe all fee data
        DB::table('booking_requests')->where('id', $this->bookingId)->update([
            'gross_amount' => 0,
            'before_total_fees' => null,
            'after_total_fees' => null,
            'custom_before_total_fees' => null,
            'tax' => 0,
            'discount' => 0,
        ]);

        $resolver = new StayCommunicationsResolver(
            Property::find($this->propertyId),
            BookingRequest::find($this->bookingId),
        );
        $html = $resolver->expandMergeFields('Body: {{invoice_table}} END');
        // No table HTML, just the surrounding template text
        $this->assertStringContainsString('Body:  END', $html);
        $this->assertStringNotContainsString('<table', $html);
    }

    public function test_phase13j_invoice_table_handles_discount_as_subtraction(): void
    {
        DB::table('booking_requests')->where('id', $this->bookingId)->update([
            'discount' => 30,
            'discount_coupon' => 'SUMMER10',
        ]);

        $resolver = new StayCommunicationsResolver(
            Property::find($this->propertyId),
            BookingRequest::find($this->bookingId),
        );
        $html = $resolver->expandMergeFields('{{invoice_table}}');

        $this->assertStringContainsString('Discount', $html);
        $this->assertStringContainsString('SUMMER10', $html);
        $this->assertStringContainsString('30.00', $html);
        // Discount line uses the green subtraction style
        $this->assertStringContainsString('&minus;', $html);
    }
}

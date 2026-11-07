<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MandatoryPropertyFeeTest extends TestCase
{
    private int $propertyId;
    private int $mandatoryFeeId;
    private int $optionalFeeId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->propertyId = DB::table('properties')->insertGetId([
            'name'                   => '_test_mandatory_fee_property_',
            'seo_url'                => '_test-mandatory-fee-' . uniqid(),
            'instant_booking_button' => '',
            'status'                 => 'true',
            'standard_rate'          => 200,
            'min_stay'               => 1,
            'cleaning_fee'           => 0,
            'tax'                    => 0,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $this->mandatoryFeeId = DB::table('property_fees')->insertGetId([
            'property_id'      => $this->propertyId,
            'name'             => 'Damage Insurance',
            'rate'             => 50.00,
            'billing_type'     => 'one_time',
            'taxable'          => 0,
            'applies_discount' => 0,
            'is_mandatory'     => 1,
            'sort_order'       => 0,
            'status'           => 'active',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $this->optionalFeeId = DB::table('property_fees')->insertGetId([
            'property_id'      => $this->propertyId,
            'name'             => 'Beach gear rental',
            'rate'             => 25.00,
            'billing_type'     => 'one_time',
            'taxable'          => 0,
            'applies_discount' => 0,
            'is_mandatory'     => 0,
            'sort_order'       => 1,
            'status'           => 'active',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('property_fees')->where('property_id', $this->propertyId)->delete();
        DB::table('properties')->where('id', $this->propertyId)->delete();
        parent::tearDown();
    }

    public function test_mandatory_fees_are_included_even_when_no_addons_selected(): void
    {
        $futureCheckIn  = now()->addDays(30)->toDateString();
        $futureCheckOut = now()->addDays(33)->toDateString();

        $response = $this->postJson('/api/booking/quote', [
            'property_id' => $this->propertyId,
            'check_in'    => $futureCheckIn,
            'check_out'   => $futureCheckOut,
            'adults'      => 2,
            'children'    => 0,
            'pets'        => 0,
            'addons'      => ['fees' => []],
        ]);

        $response->assertOk();
        $body = $response->json();
        $this->assertTrue($body['valid'], 'quote should be valid');

        $labels = array_column($body['quote']['breakdown'], 'label');
        $this->assertContains('Damage Insurance', $labels, 'mandatory fee must auto-include');
        $this->assertNotContains('Beach gear rental', $labels, 'optional fee must NOT appear when not selected');
    }

    public function test_optional_fee_appears_when_explicitly_selected(): void
    {
        $futureCheckIn  = now()->addDays(30)->toDateString();
        $futureCheckOut = now()->addDays(33)->toDateString();

        $body = $this->postJson('/api/booking/quote', [
            'property_id' => $this->propertyId,
            'check_in'    => $futureCheckIn,
            'check_out'   => $futureCheckOut,
            'adults'      => 2,
            'children'    => 0,
            'pets'        => 0,
            'addons'      => ['fees' => [$this->optionalFeeId]],
        ])->json();

        $labels = array_column($body['quote']['breakdown'], 'label');
        $this->assertContains('Damage Insurance', $labels);
        $this->assertContains('Beach gear rental', $labels);
    }

    public function test_mandatory_fee_not_double_charged_when_listed_in_addons(): void
    {
        // If the checkout payload happens to include the mandatory fee in addons.fees too,
        // it must not appear twice in the breakdown.
        $futureCheckIn  = now()->addDays(30)->toDateString();
        $futureCheckOut = now()->addDays(33)->toDateString();

        $body = $this->postJson('/api/booking/quote', [
            'property_id' => $this->propertyId,
            'check_in'    => $futureCheckIn,
            'check_out'   => $futureCheckOut,
            'adults'      => 2,
            'children'    => 0,
            'pets'        => 0,
            'addons'      => ['fees' => [$this->mandatoryFeeId]],
        ])->json();

        $occurrences = array_filter(
            array_column($body['quote']['breakdown'], 'label'),
            fn ($l) => $l === 'Damage Insurance',
        );
        $this->assertCount(1, $occurrences, 'mandatory fee must appear exactly once even if also listed in addons');
    }

    public function test_security_deposit_only_charged_when_toggle_is_on(): void
    {
        // Set a deposit amount but leave charge_at_booking=0 (default).
        DB::table('properties')->where('id', $this->propertyId)->update([
            'security_deposit_amount' => 300,
            'security_deposit_charge_at_booking' => 0,
        ]);

        $futureCheckIn  = now()->addDays(30)->toDateString();
        $futureCheckOut = now()->addDays(33)->toDateString();
        $payload = [
            'property_id' => $this->propertyId,
            'check_in'    => $futureCheckIn,
            'check_out'   => $futureCheckOut,
            'adults'      => 2,
            'children'    => 0,
            'pets'        => 0,
            'addons'      => ['fees' => []],
        ];

        $bodyOff = $this->postJson('/api/booking/quote', $payload)->json();
        $labelsOff = array_column($bodyOff['quote']['breakdown'], 'label');
        $this->assertNotContains('Refundable security deposit', $labelsOff,
            'deposit must NOT appear when charge_at_booking is off');
        $this->assertSame(0.0, (float)$bodyOff['quote']['damage_deposit']);

        // Flip the toggle on.
        DB::table('properties')->where('id', $this->propertyId)->update([
            'security_deposit_charge_at_booking' => 1,
        ]);

        $bodyOn = $this->postJson('/api/booking/quote', $payload)->json();
        $labelsOn = array_column($bodyOn['quote']['breakdown'], 'label');
        $this->assertContains('Refundable security deposit', $labelsOn,
            'deposit must appear when charge_at_booking is on');
        $this->assertSame(300.0, (float)$bodyOn['quote']['damage_deposit']);
    }
}

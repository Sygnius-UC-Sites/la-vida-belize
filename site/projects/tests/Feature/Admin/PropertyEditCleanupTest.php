<?php

namespace Tests\Feature\Admin;

use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PropertyEditCleanupTest extends TestCase
{
    private int $propertyId;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->propertyId = DB::table('properties')->insertGetId([
            'name'                   => '_test_edit_cleanup_property_',
            'seo_url'                => '_test-edit-cleanup-' . uniqid(),
            'instant_booking_button' => '',
            'status'                 => 'true',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $this->userId = DB::table('users')->insertGetId([
            'name'       => '_test_edit_cleanup_user_',
            'email'      => '_test_edit_cleanup_' . uniqid() . '@example.com',
            'password'   => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('property_fees')->where('property_id', $this->propertyId)->delete();
        DB::table('properties')->where('id', $this->propertyId)->delete();
        DB::table('users')->where('id', $this->userId)->delete();
        parent::tearDown();
    }

    /**
     * Minimum payload accepted by PropertyController::update()'s validator
     * (seo_url required + unique-except-self, name required).
     */
    private function basePayload(string $seoUrl): array
    {
        return [
            'name'    => '_test_edit_cleanup_property_',
            'seo_url' => $seoUrl,
        ];
    }

    public function test_property_save_preserves_existing_property_fees(): void
    {
        // Two fees, added via the (working) PropertyFeeController flow — directly inserted here.
        DB::table('property_fees')->insert([
            [
                'property_id'      => $this->propertyId,
                'name'             => 'Beach Gear',
                'rate'             => 20.00,
                'billing_type'     => 'per_day',
                'taxable'          => 1,
                'applies_discount' => 0,
                'sort_order'       => 0,
                'status'           => 'active',
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
            [
                'property_id'      => $this->propertyId,
                'name'             => 'Late Checkout',
                'rate'             => 50.00,
                'billing_type'     => 'one_time',
                'taxable'          => 0,
                'applies_discount' => 1,
                'sort_order'       => 1,
                'status'           => 'active',
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
        ]);

        $seoUrl = DB::table('properties')->where('id', $this->propertyId)->value('seo_url');
        $payload = $this->basePayload($seoUrl);

        $response = $this->actingAs(User::findOrFail($this->userId))
            ->put('/admin/properties/' . $this->propertyId, $payload);
        $response->assertRedirect();

        $rows = DB::table('property_fees')->where('property_id', $this->propertyId)->orderBy('sort_order')->get();
        $this->assertCount(2, $rows, 'Property save should NOT wipe property_fees rows');
        $this->assertSame('Beach Gear', $rows[0]->name);
        $this->assertSame('per_day', $rows[0]->billing_type);
        $this->assertSame('Late Checkout', $rows[1]->name);
        $this->assertSame('one_time', $rows[1]->billing_type);
    }

    public function test_pet_fee_round_trip(): void
    {
        $seoUrl = DB::table('properties')->where('id', $this->propertyId)->value('seo_url');
        $payload = $this->basePayload($seoUrl) + [
            'pet_fee'          => '15.50',
            'pet_fee_interval' => 'per_day',
            'pet_fee_type'     => 'taxable',
            'max_pet'          => '3',
        ];

        $response = $this->actingAs(User::findOrFail($this->userId))
            ->put('/admin/properties/' . $this->propertyId, $payload);
        $response->assertRedirect();

        $p = Property::findOrFail($this->propertyId);
        $this->assertEquals(15.50, (float)$p->pet_fee);
        $this->assertSame('per_day', $p->pet_fee_interval);
        $this->assertSame('taxable', $p->pet_fee_type);
        $this->assertSame(3, (int)$p->max_pet);
    }

    public function test_extra_guest_fee_round_trip(): void
    {
        $seoUrl = DB::table('properties')->where('id', $this->propertyId)->value('seo_url');
        $payload = $this->basePayload($seoUrl) + [
            'guest_fee'   => '25',
            'no_of_guest' => '4',
        ];

        $response = $this->actingAs(User::findOrFail($this->userId))
            ->put('/admin/properties/' . $this->propertyId, $payload);
        $response->assertRedirect();

        $p = Property::findOrFail($this->propertyId);
        $this->assertEquals(25, (float)$p->guest_fee);
        $this->assertSame(4, (int)$p->no_of_guest);
    }

    public function test_tax_and_security_deposit_round_trip(): void
    {
        $seoUrl = DB::table('properties')->where('id', $this->propertyId)->value('seo_url');
        $payload = $this->basePayload($seoUrl) + [
            'tax'                     => '13.5',
            'security_deposit_amount' => '200',
            'security_deposit_text'   => 'Released 7 days post-checkout',
        ];

        $response = $this->actingAs(User::findOrFail($this->userId))
            ->put('/admin/properties/' . $this->propertyId, $payload);
        $response->assertRedirect();

        $p = Property::findOrFail($this->propertyId);
        $this->assertEquals(13.5, (float)$p->tax);
        $this->assertEquals(200, (float)$p->security_deposit_amount);
        $this->assertSame('Released 7 days post-checkout', $p->security_deposit_text);
    }

    public function test_get_quote_description_round_trip(): void
    {
        $seoUrl = DB::table('properties')->where('id', $this->propertyId)->value('seo_url');
        $payload = $this->basePayload($seoUrl) + [
            'get_quote_description' => '<p>Hello world</p>',
        ];

        $response = $this->actingAs(User::findOrFail($this->userId))
            ->put('/admin/properties/' . $this->propertyId, $payload);
        $response->assertRedirect();

        $p = Property::findOrFail($this->propertyId);
        $this->assertSame('<p>Hello world</p>', $p->get_quote_description);
    }
}

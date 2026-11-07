<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PropertyFeeAdminTest extends TestCase
{
    private int $userId;
    private int $propertyId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userId = DB::table('users')->insertGetId([
            'name'       => '_pfa_test_user_',
            'email'      => '_pfa_' . uniqid() . '@example.com',
            'password'   => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->propertyId = DB::table('properties')->insertGetId([
            'name' => 'PFA-Test ' . uniqid(),
            'seo_url' => 'pfa-' . uniqid(),
            'instant_booking_button' => 'Book Now',
            'standard_rate' => 200,
            'no_of_guest' => 4,
            'type' => 'condo',
            'status' => 'true',
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('property_fees')->where('property_id', $this->propertyId)->delete();
        DB::table('properties')->where('id', $this->propertyId)->delete();
        DB::table('users')->where('id', $this->userId)->delete();
        parent::tearDown();
    }

    public function test_admin_can_create_property_fee(): void
    {
        $user = User::findOrFail($this->userId);
        $resp = $this->actingAs($user)->post("/admin/properties/{$this->propertyId}/fees", [
            'name'         => 'Beach gear',
            'rate'         => 25.00,
            'billing_type' => 'per_day',
            'taxable'      => '1',
            'sort_order'   => 5,
            'status'       => 'active',
        ]);
        $resp->assertRedirect();

        $row = DB::table('property_fees')->where('property_id', $this->propertyId)->first();
        $this->assertNotNull($row);
        $this->assertSame('Beach gear', $row->name);
        $this->assertEqualsWithDelta(25.00, (float) $row->rate, 0.001);
        $this->assertSame('per_day', $row->billing_type);
        $this->assertSame(1, (int)$row->taxable);
    }

    public function test_admin_can_update_property_fee(): void
    {
        $user = User::findOrFail($this->userId);
        $feeId = DB::table('property_fees')->insertGetId([
            'property_id' => $this->propertyId,
            'name' => 'Old name', 'rate' => 10, 'billing_type' => 'one_time',
            'taxable' => 1, 'applies_discount' => 0, 'sort_order' => 0, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $resp = $this->actingAs($user)->put("/admin/properties/{$this->propertyId}/fees/{$feeId}", [
            'name'         => 'Updated name',
            'rate'         => 99.99,
            'billing_type' => 'per_day',
            'status'       => 'inactive',
        ]);
        $resp->assertRedirect();

        $row = DB::table('property_fees')->where('id', $feeId)->first();
        $this->assertSame('Updated name', $row->name);
        $this->assertEqualsWithDelta(99.99, (float) $row->rate, 0.001);
        $this->assertSame('per_day', $row->billing_type);
        $this->assertSame('inactive', $row->status);
    }

    public function test_admin_can_delete_property_fee(): void
    {
        $user = User::findOrFail($this->userId);
        $feeId = DB::table('property_fees')->insertGetId([
            'property_id' => $this->propertyId,
            'name' => 'Doomed', 'rate' => 5, 'billing_type' => 'one_time',
            'taxable' => 1, 'applies_discount' => 0, 'sort_order' => 0, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $resp = $this->actingAs($user)->delete("/admin/properties/{$this->propertyId}/fees/{$feeId}");
        $resp->assertRedirect();

        $this->assertNull(DB::table('property_fees')->where('id', $feeId)->first());
    }

    public function test_property_fee_validation_rejects_bad_billing_type(): void
    {
        $user = User::findOrFail($this->userId);

        $resp = $this->actingAs($user)
            ->from("/admin/properties/{$this->propertyId}/edit")
            ->post("/admin/properties/{$this->propertyId}/fees", [
                'name'         => 'Bad billing',
                'rate'         => 10,
                'billing_type' => 'monthly', // not allowed
                'status'       => 'active',
            ]);
        $resp->assertRedirect();
        $resp->assertSessionHasErrors('billing_type');

        // No row inserted
        $this->assertSame(0, DB::table('property_fees')->where('property_id', $this->propertyId)->count());
    }
}

<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CouponExtensionTest extends TestCase
{
    use WithoutMiddleware;

    public function test_create_coupon_persists_new_guard_fields(): void
    {
        // Cleanup any leftover test data first
        DB::table('coupons')->where('code', 'TEST-EXT-1')->delete();

        $payload = [
            'name' => 'Test Extended',
            'code' => 'TEST-EXT-1',
            'type' => 'per',  // existing project uses 'per' for percent
            'amount' => 10,
            'min_stay' => 2,
            'max_uses' => 25,
            'max_uses_per_customer' => 1,
            'min_order_amount' => 250.50,
            'description' => 'Test coupon for extension fields',
        ];
        $this->post('/admin/coupons', $payload);

        $row = DB::table('coupons')->where('code', 'TEST-EXT-1')->first();
        $this->assertNotNull($row, 'Coupon row should have been created');
        $this->assertSame(25, (int)$row->max_uses);
        $this->assertSame(1, (int)$row->max_uses_per_customer);
        $this->assertEqualsWithDelta(250.50, (float) $row->min_order_amount, 0.001);

        DB::table('coupons')->where('code', 'TEST-EXT-1')->delete();
    }

    public function test_blank_guard_fields_persist_as_null(): void
    {
        DB::table('coupons')->where('code', 'TEST-BLANK-1')->delete();

        $payload = [
            'name' => 'Test Blank',
            'code' => 'TEST-BLANK-1',
            'type' => 'per',
            'amount' => 5,
        ];
        $this->post('/admin/coupons', $payload);

        $row = DB::table('coupons')->where('code', 'TEST-BLANK-1')->first();
        $this->assertNotNull($row);
        $this->assertNull($row->max_uses);
        // Note: max_uses_per_customer has DB default 1 (from Plan A migration),
        // so even when not posted it ends up at 1 (via the column default, not the controller)
        $this->assertNull($row->min_order_amount);

        DB::table('coupons')->where('code', 'TEST-BLANK-1')->delete();
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Models\Pms\PmsRoom;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UnifiedBookingAdminSurfaceTest extends TestCase
{
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userId = DB::table('users')->insertGetId([
            'name' => 'Unified Booking Admin',
            'email' => 'unified-booking-admin-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function actor(): User
    {
        return User::findOrFail($this->userId);
    }

    public function test_pms_website_booking_uses_unified_booking_admin_surface(): void
    {
        $room = PmsRoom::create([
            'pms_property_id' => 'unified-admin-property',
            'pms_room_id' => 'unified-admin-room',
            'pms_source' => 'hostfully',
            'name' => 'Unified Admin PMS Room',
            'seo_url' => 'unified-admin-pms-room',
            'status' => 'true',
            'currency' => 'USD',
        ]);

        $bookingId = DB::table('booking_requests')->insertGetId([
            'property_id' => $room->id,
            'pms_source' => 'hostfully',
            'pms_room_id' => 'unified-admin-room',
            'pms_booking_id' => 'HF-BOOKING-123',
            'name' => 'Unified PMS Guest',
            'email' => 'unified-pms-guest@example.test',
            'mobile' => '+1 555 0140',
            'checkin' => '2031-04-10',
            'checkout' => '2031-04-13',
            'total_guests' => 2,
            'adults' => 2,
            'child' => 0,
            'total_night' => 3,
            'total_amount' => 900,
            'currency' => 'USD',
            'booking_status' => 'payment-captured-pms-failed',
            'payment_status' => 'paid',
            'before_total_fees' => '[]',
            'after_total_fees' => '[]',
            'stripe_deposit_charge_id' => 'pi_deposit_unified_test',
            'security_deposit_amount' => 300,
            'security_deposit_currency' => 'USD',
            'security_deposit_status' => 'authorized',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $paymentId = DB::table('payments')->insertGetId([
            'booking_id' => $bookingId,
            'tran_id' => 'ch_unified_admin_test',
            'type' => 'stripe',
            'amount' => 450,
            'status' => 'complete',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $response = $this->actingAs($this->actor())->get('/admin/booking-enquiries');

            $response->assertOk();
            $response->assertSee('Unified PMS Guest');
            $response->assertSee('Unified Admin PMS Room');
            $response->assertSee('Hostfully website booking');
            $response->assertSee('PMS reservation HF-BOOKING-123');
            $response->assertSee('PMS retry needed');
            $response->assertSee('Retry PMS reservation');
            $response->assertSee('Refundable Damage Deposit Hold');
            $response->assertSee('Capture deposit');
            $response->assertSee('Release hold');
            $response->assertSee('Stripe Payment');
            $response->assertSee('Issue refund');
            $response->assertSee(route('admin.pms-bookings.destroy', [$bookingId]), false);
            $response->assertDontSee('PMS actions');
        } finally {
            DB::table('payments')->where('id', $paymentId)->delete();
            DB::table('booking_requests')->where('id', $bookingId)->delete();
            DB::table('pms_rooms')->where('id', $room->id)->delete();
        }
    }
}

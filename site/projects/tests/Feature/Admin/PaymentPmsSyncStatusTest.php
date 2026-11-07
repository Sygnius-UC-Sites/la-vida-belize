<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentPmsSyncStatusTest extends TestCase
{
    public function test_payments_index_shows_pms_sync_status_and_error(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => '_payment_sync_user_',
            'email' => '_payment_sync_' . uniqid() . '@test.local',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $bookingId = DB::table('booking_requests')->insertGetId([
            'property_id' => 1,
            'name' => 'Payment Sync Guest',
            'email' => 'payment-sync@example.com',
            'checkin' => now()->addMonth()->toDateString(),
            'checkout' => now()->addMonth()->addDays(3)->toDateString(),
            'booking_status' => 'booked',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $paymentId = DB::table('payments')->insertGetId([
            'booking_id' => $bookingId,
            'tran_id' => 'pi_sync_test_' . uniqid(),
            'type' => 'stripe',
            'amount' => 123.45,
            'status' => 'complete',
            'pms_sync_status' => 'not_supported',
            'pms_sync_error' => 'Manual reconciliation required',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs(User::findOrFail($userId))->get('/admin/payments');

        $response->assertOk();
        $response->assertSee('not_supported');
        $response->assertSee('Manual reconciliation required');

        DB::table('payments')->where('id', $paymentId)->delete();
        DB::table('booking_requests')->where('id', $bookingId)->delete();
        DB::table('users')->where('id', $userId)->delete();
    }
}

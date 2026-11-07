<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BookingCancellationInstallmentsTest extends TestCase
{
    public function test_regular_admin_cancellation_cancels_pending_scheduled_installments(): void
    {
        Mail::fake();

        $userId = DB::table('users')->insertGetId([
            'name' => '_cancel_installment_user_',
            'email' => '_cancel_installment_' . uniqid() . '@test.local',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $bookingId = DB::table('booking_requests')->insertGetId([
            'property_id' => 1,
            'name' => 'Cancel Installment',
            'email' => 'cancel-installment@example.com',
            'checkin' => Carbon::today()->addDays(60)->toDateString(),
            'checkout' => Carbon::today()->addDays(63)->toDateString(),
            'booking_status' => 'booked',
            'payment_status' => 'partially',
            'total_payment' => 2,
            'how_many_payment_done' => 1,
            'before_total_fees' => '[]',
            'after_total_fees' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $scheduledId = DB::table('scheduled_payments')->insertGetId([
            'booking_request_id' => $bookingId,
            'pms_source' => 'crm',
            'pms_booking_id' => 'direct-' . $bookingId,
            'stripe_customer_id' => 'cus_cancel_test',
            'amount' => 400,
            'currency' => 'USD',
            'due_date' => Carbon::today()->addDays(30)->toDateString(),
            'status' => 'pending',
            'attempts' => 0,
            'pms_notified' => false,
            'reminder_sent' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::findOrFail($userId))
            ->withoutMiddleware()
            ->delete('/admin/booking-enquiries/' . $bookingId)
            ->assertRedirect();

        $this->assertSame('cancelled', DB::table('booking_requests')->where('id', $bookingId)->value('booking_status'));
        $this->assertSame('cancelled', DB::table('scheduled_payments')->where('id', $scheduledId)->value('status'));

        DB::table('scheduled_payments')->where('booking_request_id', $bookingId)->delete();
        DB::table('booking_requests')->where('id', $bookingId)->delete();
        DB::table('users')->where('id', $userId)->delete();
    }
}

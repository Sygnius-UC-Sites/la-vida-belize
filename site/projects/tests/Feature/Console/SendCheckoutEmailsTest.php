<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendCheckoutEmailsTest extends TestCase
{
    public function test_flips_checkout_email_for_booking_checking_out_today(): void
    {
        Mail::fake();
        $today = \Carbon\Carbon::today()->toDateString();
        $bookingId = DB::table('booking_requests')->insertGetId([
            'property_id' => 1,
            'name' => 'Checkout Test', 'email' => 'checkout-test@example.com',
            'checkin' => \Carbon\Carbon::today()->subDays(3)->toDateString(),
            'checkout' => $today,
            'booking_status' => 'booked',
            'payment_status' => 'paid',
            'checkout_email' => 'false',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Artisan::call('bookings:send-checkout-emails');

        $flag = DB::table('booking_requests')->where('id', $bookingId)->value('checkout_email');
        $this->assertSame('true', $flag);

        DB::table('booking_requests')->where('id', $bookingId)->delete();
    }
}

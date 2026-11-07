<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendCheckinEmailsTest extends TestCase
{
    public function test_flips_checkin_email_for_booking_checking_in_today(): void
    {
        Mail::fake();
        $today = \Carbon\Carbon::today()->toDateString();
        $bookingId = DB::table('booking_requests')->insertGetId([
            'property_id' => 1,
            'name' => 'Checkin Test', 'email' => 'checkin-test@example.com',
            'checkin' => $today,
            'checkout' => \Carbon\Carbon::today()->addDays(3)->toDateString(),
            'booking_status' => 'booked',
            'payment_status' => 'paid',
            'checkin_email' => 'false',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Artisan::call('bookings:send-checkin-emails');

        $flag = DB::table('booking_requests')->where('id', $bookingId)->value('checkin_email');
        $this->assertSame('true', $flag);

        DB::table('booking_requests')->where('id', $bookingId)->delete();
    }
}

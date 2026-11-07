<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendWelcomeEmailsTest extends TestCase
{
    public function test_sends_welcome_email_to_booking_with_matching_checkin(): void
    {
        Mail::fake(); // Phase 13I — actual send is intercepted; we only care that the flag flips.
        DB::table('basic_settings')->updateOrInsert(['name' => 'welcome_package_send_day'], ['value' => '7', 'updated_at' => now(), 'created_at' => now()]);

        $checkin = \Carbon\Carbon::today()->addDays(7)->toDateString();
        $bookingId = DB::table('booking_requests')->insertGetId([
            'property_id' => 1,
            'name' => 'Welcome Test', 'email' => 'welcome-test@example.com',
            'checkin' => $checkin,
            'checkout' => \Carbon\Carbon::today()->addDays(10)->toDateString(),
            'booking_status' => 'booked',
            'payment_status' => 'paid',
            'welcome_email' => 'false',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Artisan::call('bookings:send-welcome-emails');

        $flag = DB::table('booking_requests')->where('id', $bookingId)->value('welcome_email');
        $this->assertSame('true', $flag);

        DB::table('booking_requests')->where('id', $bookingId)->delete();
    }

    public function test_skips_booking_with_welcome_email_already_true(): void
    {
        DB::table('basic_settings')->updateOrInsert(['name' => 'welcome_package_send_day'], ['value' => '7', 'updated_at' => now(), 'created_at' => now()]);

        $checkin = \Carbon\Carbon::today()->addDays(7)->toDateString();
        $bookingId = DB::table('booking_requests')->insertGetId([
            'property_id' => 1,
            'name' => 'Already Sent Test', 'email' => 'already-sent@example.com',
            'checkin' => $checkin,
            'checkout' => \Carbon\Carbon::today()->addDays(10)->toDateString(),
            'booking_status' => 'booked',
            'payment_status' => 'paid',
            'welcome_email' => 'true',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Artisan::call('bookings:send-welcome-emails');

        $flag = DB::table('booking_requests')->where('id', $bookingId)->value('welcome_email');
        $this->assertSame('true', $flag);

        DB::table('booking_requests')->where('id', $bookingId)->delete();
    }

    public function test_skips_booking_with_wrong_status(): void
    {
        DB::table('basic_settings')->updateOrInsert(['name' => 'welcome_package_send_day'], ['value' => '7', 'updated_at' => now(), 'created_at' => now()]);

        $checkin = \Carbon\Carbon::today()->addDays(7)->toDateString();
        $bookingId = DB::table('booking_requests')->insertGetId([
            'property_id' => 1,
            'name' => 'Wrong Status Test', 'email' => 'wrong-status@example.com',
            'checkin' => $checkin,
            'checkout' => \Carbon\Carbon::today()->addDays(10)->toDateString(),
            'booking_status' => 'cancelled',
            'payment_status' => 'paid',
            'welcome_email' => 'false',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Artisan::call('bookings:send-welcome-emails');

        $flag = DB::table('booking_requests')->where('id', $bookingId)->value('welcome_email');
        $this->assertSame('false', $flag);

        DB::table('booking_requests')->where('id', $bookingId)->delete();
    }
}

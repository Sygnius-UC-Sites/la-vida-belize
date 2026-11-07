<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SendInstallment2RemindersTest extends TestCase
{
    public function test_flips_reminder_email_for_2_installment_partial_booking(): void
    {
        DB::table('basic_settings')->updateOrInsert(['name' => 'second_how_many_days'], ['value' => '30', 'updated_at' => now(), 'created_at' => now()]);

        $checkin = \Carbon\Carbon::today()->addDays(30)->toDateString();
        $bookingId = DB::table('booking_requests')->insertGetId([
            'property_id' => 1,
            'name' => 'Inst2 Test', 'email' => 'inst2-test@example.com',
            'checkin' => $checkin,
            'checkout' => \Carbon\Carbon::today()->addDays(33)->toDateString(),
            'booking_status' => 'booked',
            'payment_status' => 'partially',
            'total_payment' => 2,
            'how_many_payment_done' => 1,
            'reminder_email' => 'false',
            'before_total_fees' => '[]',
            'after_total_fees' => '[]',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Artisan::call('bookings:send-installment-2-reminders');

        $flag = DB::table('booking_requests')->where('id', $bookingId)->value('reminder_email');
        $this->assertSame('true', $flag, Artisan::output());

        DB::table('booking_requests')->where('id', $bookingId)->delete();
    }

    public function test_skips_booking_with_payment_status_paid(): void
    {
        DB::table('basic_settings')->updateOrInsert(['name' => 'second_how_many_days'], ['value' => '30', 'updated_at' => now(), 'created_at' => now()]);

        $checkin = \Carbon\Carbon::today()->addDays(30)->toDateString();
        $bookingId = DB::table('booking_requests')->insertGetId([
            'property_id' => 1,
            'name' => 'Paid Test', 'email' => 'paid-inst2@example.com',
            'checkin' => $checkin,
            'checkout' => \Carbon\Carbon::today()->addDays(33)->toDateString(),
            'booking_status' => 'booked',
            'payment_status' => 'paid',
            'total_payment' => 2,
            'how_many_payment_done' => 2,
            'reminder_email' => 'false',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Artisan::call('bookings:send-installment-2-reminders');

        $flag = DB::table('booking_requests')->where('id', $bookingId)->value('reminder_email');
        $this->assertSame('false', $flag);

        DB::table('booking_requests')->where('id', $bookingId)->delete();
    }
}

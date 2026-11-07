<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SendInstallment3RemindersTest extends TestCase
{
    public function test_flips_third_reminder_email_for_3_installment_2_paid_booking(): void
    {
        DB::table('basic_settings')->updateOrInsert(['name' => 'third_how_many_days'], ['value' => '15', 'updated_at' => now(), 'created_at' => now()]);

        $checkin = \Carbon\Carbon::today()->addDays(15)->toDateString();
        $bookingId = DB::table('booking_requests')->insertGetId([
            'property_id' => 1,
            'name' => 'Inst3 Test', 'email' => 'inst3-test@example.com',
            'checkin' => $checkin,
            'checkout' => \Carbon\Carbon::today()->addDays(18)->toDateString(),
            'booking_status' => 'booked',
            'payment_status' => 'partially',
            'total_payment' => 3,
            'how_many_payment_done' => 2,
            'third_reminder_email' => 'false',
            'before_total_fees' => '[]',
            'after_total_fees' => '[]',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Artisan::call('bookings:send-installment-3-reminders');

        $flag = DB::table('booking_requests')->where('id', $bookingId)->value('third_reminder_email');
        $this->assertSame('true', $flag, Artisan::output());

        DB::table('booking_requests')->where('id', $bookingId)->delete();
    }

    public function test_skips_3_installment_with_only_1_payment_done(): void
    {
        DB::table('basic_settings')->updateOrInsert(['name' => 'third_how_many_days'], ['value' => '15', 'updated_at' => now(), 'created_at' => now()]);

        $checkin = \Carbon\Carbon::today()->addDays(15)->toDateString();
        $bookingId = DB::table('booking_requests')->insertGetId([
            'property_id' => 1,
            'name' => 'Inst3 Skip Test', 'email' => 'inst3-skip@example.com',
            'checkin' => $checkin,
            'checkout' => \Carbon\Carbon::today()->addDays(18)->toDateString(),
            'booking_status' => 'booked',
            'payment_status' => 'partially',
            'total_payment' => 3,
            'how_many_payment_done' => 1,
            'third_reminder_email' => 'false',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Artisan::call('bookings:send-installment-3-reminders');

        $flag = DB::table('booking_requests')->where('id', $bookingId)->value('third_reminder_email');
        $this->assertSame('false', $flag);

        DB::table('booking_requests')->where('id', $bookingId)->delete();
    }
}

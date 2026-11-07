<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarkBookingsCompletedTest extends TestCase
{
    public function test_flips_status_to_completed_after_checkout_passes(): void
    {
        $bookingId = DB::table('booking_requests')->insertGetId([
            'property_id' => 1,
            'name' => 'Complete Test', 'email' => 'complete-test@example.com',
            'checkin' => \Carbon\Carbon::today()->subDays(7)->toDateString(),
            'checkout' => \Carbon\Carbon::today()->subDays(1)->toDateString(),
            'booking_status' => 'booked',
            'payment_status' => 'paid',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Artisan::call('bookings:mark-completed');

        $status = DB::table('booking_requests')->where('id', $bookingId)->value('booking_status');
        $this->assertSame('completed', $status);

        DB::table('booking_requests')->where('id', $bookingId)->delete();
    }

    public function test_skips_booking_with_partial_payment(): void
    {
        $bookingId = DB::table('booking_requests')->insertGetId([
            'property_id' => 1,
            'name' => 'Partial Test', 'email' => 'partial-complete@example.com',
            'checkin' => \Carbon\Carbon::today()->subDays(7)->toDateString(),
            'checkout' => \Carbon\Carbon::today()->subDays(1)->toDateString(),
            'booking_status' => 'booked',
            'payment_status' => 'partially',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Artisan::call('bookings:mark-completed');

        $status = DB::table('booking_requests')->where('id', $bookingId)->value('booking_status');
        $this->assertSame('booked', $status);

        DB::table('booking_requests')->where('id', $bookingId)->delete();
    }
}

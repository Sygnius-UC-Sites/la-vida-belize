<?php

namespace Tests\Feature\Console;

use App\Mail\ReviewRequestSent;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendReviewEmailsTest extends TestCase
{
    public function test_flips_review_email_for_completed_booking_2_days_after_checkout(): void
    {
        Mail::fake();
        DB::table('basic_settings')->updateOrInsert(['name' => 'review_send_day'], ['value' => '2', 'updated_at' => now(), 'created_at' => now()]);

        $checkout = \Carbon\Carbon::today()->subDays(2)->toDateString();
        $bookingId = DB::table('booking_requests')->insertGetId([
            'property_id' => 1,
            'name' => 'Review Test', 'email' => 'review-test@example.com',
            'checkin' => \Carbon\Carbon::today()->subDays(7)->toDateString(),
            'checkout' => $checkout,
            'booking_status' => 'completed',
            'payment_status' => 'paid',
            'review_email' => 'false',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Artisan::call('bookings:send-review-emails');

        $row = DB::table('booking_requests')->where('id', $bookingId)->first();
        $this->assertSame('true', $row->review_email);
        $this->assertNotNull($row->review_email_sent_at, 'review_email_sent_at should be populated');

        // Pre-created Testimonial with token
        $testimonial = DB::table('testimonials')->where('booking_id', $bookingId)->first();
        $this->assertNotNull($testimonial, 'A testimonial row should be pre-created for the booking');
        $this->assertNotEmpty($testimonial->submission_token);
        $this->assertSame('pending-link-sent', $testimonial->status);
        $this->assertSame((int)$bookingId, (int)$testimonial->booking_id);

        Mail::assertSent(ReviewRequestSent::class, 1);
        Mail::assertSent(ReviewRequestSent::class, function ($m) use ($bookingId, $testimonial) {
            return $m->booking->id === $bookingId
                && $m->token === $testimonial->submission_token;
        });

        DB::table('testimonials')->where('booking_id', $bookingId)->delete();
        DB::table('booking_requests')->where('id', $bookingId)->delete();
    }
}

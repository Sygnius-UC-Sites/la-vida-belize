<?php

namespace Tests\Feature\Visitor;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class MagicLinkPaymentTest extends TestCase
{
    use WithoutMiddleware;

    public function test_valid_signed_url_renders_payment_page(): void
    {
        $bookingId = DB::table('booking_requests')->insertGetId([
            'property_id' => 1,
            'name' => 'MLink Test', 'email' => 'mlink-test@example.com',
            'checkin' => \Carbon\Carbon::today()->addDays(40)->toDateString(),
            'checkout' => \Carbon\Carbon::today()->addDays(43)->toDateString(),
            'booking_status' => 'booking-accepted',
            'payment_status' => 'check-booking',
            'total_amount' => 500,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $url = URL::temporarySignedRoute('booking.pay-link', now()->addDays(7), ['id' => $bookingId]);
        // Strip the absolute domain (Laravel signed URLs include it; the test client wants a path)
        $path = parse_url($url, PHP_URL_PATH) . '?' . parse_url($url, PHP_URL_QUERY);

        $response = $this->get($path);
        $response->assertStatus(200);
        $response->assertSee('Pay');

        DB::table('booking_requests')->where('id', $bookingId)->delete();
    }

    public function test_invalid_signature_renders_expired_page(): void
    {
        $bookingId = DB::table('booking_requests')->insertGetId([
            'property_id' => 1,
            'name' => 'MLink Expired Test', 'email' => 'mlink-expired-test@example.com',
            'checkin' => \Carbon\Carbon::today()->addDays(40)->toDateString(),
            'checkout' => \Carbon\Carbon::today()->addDays(43)->toDateString(),
            'booking_status' => 'booking-accepted',
            'payment_status' => 'check-booking',
            'total_amount' => 500,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Visit the route directly without a signature
        $response = $this->get("/booking/pay/{$bookingId}");
        $response->assertStatus(403);
        $response->assertSee('Link Expired');

        DB::table('booking_requests')->where('id', $bookingId)->delete();
    }
}

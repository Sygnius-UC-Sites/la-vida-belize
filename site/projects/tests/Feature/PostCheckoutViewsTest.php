<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 5 (2026-05-07) — verifies the post-checkout status pages
 * (payment-success / payment-failed / booking-confirmed /
 * booking-request-received) all share the new design's chrome
 * (booking-page body class, current shared booking stylesheet) and render the
 * status-card structure with the correct icon variant.
 *
 * The pages are CMS-driven (PageController@dynamicDataCategory) so the
 * tests exercise them via their public `seo_url`.
 */
class PostCheckoutViewsTest extends TestCase
{
    private function ensureCmsRow(string $seoUrl, string $name, string $template): void
    {
        // Upsert so the asserted `name` always matches what the page renders,
        // regardless of historical seeds with different casing.
        if (!DB::table('cms')->where('seo_url', $seoUrl)->exists()) {
            DB::table('cms')->insert([
                'name'        => $name,
                'seo_url'     => $seoUrl,
                'templete'    => $template,
                'publish'     => 'published',
                'description' => '',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        } else {
            DB::table('cms')->where('seo_url', $seoUrl)->update([
                'name'     => $name,
                'templete' => $template,
                'publish'  => 'published',
            ]);
        }
    }

    public function test_payment_success_page_uses_new_design_chrome(): void
    {
        $this->ensureCmsRow('payment-success', 'Payment received', 'payment-success');
        $html = $this->get('/payment-success')->assertOk()->getContent();

        $this->assertStringContainsString('booking-page', $html);
        $this->assertStringContainsString('css/booking-funnel.css', $html);
        $this->assertStringContainsString('class="status-card"', $html);
        $this->assertStringContainsString('status-icon--success', $html);
    }

    public function test_payment_failed_page_uses_error_icon_and_try_again_cta(): void
    {
        $this->ensureCmsRow('payment-failed', 'Payment Failed', 'payment-failed');
        $html = $this->get('/payment-failed')->assertOk()->getContent();

        $this->assertStringContainsString('booking-page', $html);
        $this->assertStringContainsString('status-icon--error', $html);
        $this->assertStringContainsString('Try again', $html);
        $this->assertStringContainsString('Contact us', $html);
    }

    public function test_booking_confirmed_page_uses_success_icon(): void
    {
        $this->ensureCmsRow('booking-confirmed', 'Booking Confirmed', 'booking-confirmed');
        $html = $this->get('/booking-confirmed')->assertOk()->getContent();

        $this->assertStringContainsString('booking-page', $html);
        $this->assertStringContainsString('status-icon--success', $html);
    }

    public function test_booking_confirmed_page_shows_direct_partial_payment_truthfully(): void
    {
        $propertyId = DB::table('properties')->insertGetId([
            'name'                   => '_test_partial_property_',
            'seo_url'                => '_test-partial-' . uniqid(),
            'instant_booking_button' => '',
            'status'                 => 'true',
            'standard_rate'          => 200,
            'min_stay'               => 1,
            'no_of_guest'            => 4,
            'sleeps'                 => 4,
            'type'                   => 'property',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);
        $bookingId = DB::table('booking_requests')->insertGetId([
            'property_id'    => $propertyId,
            'name'           => 'Partial Guest',
            'first_name'     => 'Partial',
            'last_name'      => 'Guest',
            'email'          => 'partial@example.com',
            'checkin'        => '2030-10-10',
            'checkout'       => '2030-10-13',
            'total_amount'   => 1000,
            'gross_amount'   => 900,
            'deposit_due_amount' => 400,
            'booking_status' => 'booked',
            'payment_status' => 'partially',
            'amount_data'    => json_encode([
                'installments' => [
                    ['due_date' => '2030-01-01', 'amount' => 400, 'sequence' => 1],
                    ['due_date' => '2030-02-01', 'amount' => 600, 'sequence' => 2],
                ],
            ]),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        try {
            $this->ensureCmsRow('booking-confirmed', 'Booking Confirmed', 'booking-confirmed');
            $html = $this->get('/booking-confirmed?booking_id=' . $bookingId)->assertOk()->getContent();

            $this->assertStringContainsString('Paid today', $html);
            $this->assertStringContainsString('$400.00', $html);
            $this->assertStringContainsString('Balance due', $html);
            $this->assertStringContainsString('$600.00', $html);
            $this->assertStringContainsString('Payment schedule', $html);
        } finally {
            DB::table('booking_requests')->where('id', $bookingId)->delete();
            DB::table('properties')->where('id', $propertyId)->delete();
        }
    }

    public function test_booking_request_received_page_uses_pending_icon(): void
    {
        $this->ensureCmsRow('booking-request-received', 'Request received', 'booking-request-received');
        $html = $this->get('/booking-request-received')->assertOk()->getContent();

        $this->assertStringContainsString('booking-page', $html);
        $this->assertStringContainsString('status-icon--pending', $html);
        $this->assertStringContainsString('Request received', $html);
    }

    public function test_status_pages_render_booking_summary_when_booking_id_present(): void
    {
        // Seed a booking + property the success page can pick up via ?booking_id=.
        $propertyId = DB::table('properties')->insertGetId([
            'name'                   => '_test_status_property_',
            'seo_url'                => '_test-status-' . uniqid(),
            'instant_booking_button' => '',
            'status'                 => 'true',
            'standard_rate'          => 200,
            'min_stay'               => 1,
            'no_of_guest'            => 4,
            'sleeps'                 => 4,
            'type'                   => 'condo',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);
        $bookingId = DB::table('booking_requests')->insertGetId([
            'property_id'   => $propertyId,
            'name'          => 'Test Guest',
            'email'         => 'test@example.com',
            'checkin'       => '2030-09-10',
            'checkout'      => '2030-09-13',
            'total_amount'  => 600,
            'booking_status' => 'booked',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        try {
            $this->ensureCmsRow('payment-success', 'Payment received', 'payment-success');
            $html = $this->get('/payment-success?booking_id=' . $bookingId)->assertOk()->getContent();

            $this->assertStringContainsString('_test_status_property_', $html);
            $this->assertStringContainsString('Sep 10, 2030', $html);
            $this->assertStringContainsString('Sep 13, 2030', $html);
            $this->assertStringContainsString('$600.00', $html);
            $this->assertStringContainsString('Booking #' . $bookingId, $html);
        } finally {
            DB::table('booking_requests')->where('id', $bookingId)->delete();
            DB::table('properties')->where('id', $propertyId)->delete();
        }
    }
}

<?php

namespace Tests\Feature\Visitor;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SaveBookingDataJsonEnvelopeTest extends TestCase
{
    use WithoutMiddleware;

    public function test_missing_property_id_returns_validation_failed_json(): void
    {
        $response = $this->postJson('/save-booking-data', []);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => false,
            'error_code' => 'validation_failed',
        ]);
        $response->assertJsonStructure(['success', 'error_code', 'error_message']);
    }

    public function test_property_not_found_returns_invalid_booking_json(): void
    {
        $response = $this->postJson('/save-booking-data', [
            'property_id' => 99999999,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => false,
            'error_code' => 'invalid_booking',
        ]);
        $response->assertJsonStructure(['success', 'error_code', 'error_message']);
    }

    public function test_missing_checkin_and_checkout_returns_validation_failed_json(): void
    {
        $response = $this->postJson('/save-booking-data', [
            'property_id' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => false,
            'error_code' => 'validation_failed',
        ]);
        $response->assertJsonStructure(['success', 'error_code', 'error_message']);
    }

    public function test_invalid_date_range_returns_invalid_booking_json(): void
    {
        // Pick dates that overlap a 'booked' booking_request for property 1 so
        // Helper::getGrossAmountData returns status=false. The blocker row is
        // unconditionally inserted at test start and deleted at test end.
        $checkIn = \Carbon\Carbon::today()->addDays(120)->toDateString();
        $checkOut = \Carbon\Carbon::today()->addDays(123)->toDateString();

        $blockerId = DB::table('booking_requests')->insertGetId([
            'property_id' => 1,
            'name' => 'JSON Envelope Test Blocker',
            'email' => 'blocker@example.com',
            'checkin' => $checkIn,
            'checkout' => $checkOut,
            'booking_status' => 'booked',
            'payment_status' => 'paid',
            'total_amount' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/save-booking-data', [
            'property_id' => 1,
            'checkin' => $checkIn,
            'checkout' => $checkOut,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => false,
            'error_code' => 'invalid_booking',
        ]);
        $response->assertJsonStructure(['success', 'error_code', 'error_message']);

        DB::table('booking_requests')->where('id', $blockerId)->delete();
    }

    public function test_non_ajax_post_keeps_redirect_back_with_flash(): void
    {
        $response = $this->post('/save-booking-data', []);

        $response->assertStatus(302);
        $response->assertSessionHas('danger');
    }
}

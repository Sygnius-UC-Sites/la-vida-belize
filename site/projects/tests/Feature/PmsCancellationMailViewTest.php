<?php

namespace Tests\Feature;

use stdClass;
use Tests\TestCase;

class PmsCancellationMailViewTest extends TestCase
{
    public function test_pms_cancellation_mail_views_render(): void
    {
        $property = new stdClass();
        $property->name = 'Demo Beach House';

        $data = [
            'id' => 123,
            'request_id' => 'BASE-123',
            'pms_booking_id' => '21115281',
            'name' => 'Test Guest',
            'email' => 'guest@example.test',
            'checkin' => '2026-06-15',
            'checkout' => '2026-06-18',
            'total_amount' => 685.62,
            'currency' => 'USD',
        ];

        $guestHtml = view('mail.booking-cancel-user-email', compact('data', 'property'))->render();
        $adminHtml = view('mail.booking-cancel-admin-email', compact('data', 'property'))->render();

        $this->assertStringContainsString('Your booking has been cancelled', $guestHtml);
        $this->assertStringContainsString('Booking cancelled', $adminHtml);
        $this->assertStringContainsString('Demo Beach House', $guestHtml);
        $this->assertStringContainsString('21115281', $adminHtml);
    }
}

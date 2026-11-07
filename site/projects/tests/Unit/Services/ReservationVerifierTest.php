<?php

namespace Tests\Unit\Services;

use App\Services\ReservationVerifier;
use Tests\TestCase;

class ReservationVerifierTest extends TestCase
{
    public function test_hostfully_booked_lead_without_total_passes_when_no_optional_services_selected(): void
    {
        $result = ReservationVerifier::verify(
            'hostfully',
            [
                'status' => 200,
                'data' => [
                    'uid' => 'lead-123',
                    'lead' => [
                        'uid' => 'lead-123',
                        'status' => 'BOOKED',
                    ],
                ],
            ],
            2296.55,
            'USD',
            [],
            []
        );

        $this->assertTrue($result['ok']);
    }

    public function test_hostfully_missing_total_still_fails_when_optional_services_were_selected(): void
    {
        $result = ReservationVerifier::verify(
            'hostfully',
            [
                'status' => 200,
                'data' => [
                    'uid' => 'lead-123',
                    'lead' => [
                        'uid' => 'lead-123',
                        'status' => 'BOOKED',
                    ],
                ],
            ],
            2296.55,
            'USD',
            ['hf_pool_heat' => 1],
            []
        );

        $this->assertFalse($result['ok']);
        $this->assertSame('Hostfully lead.totalPrice missing in reservation response', $result['discrepancy']['reason']);
    }
}

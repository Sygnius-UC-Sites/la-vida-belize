<?php

namespace Tests\Feature\Visitor;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class StaysListingTest extends TestCase
{
    use WithoutMiddleware;

    public function test_stays_route_renders_listing(): void
    {
        $response = $this->get('/stays');
        $response->assertStatus(200);
    }

    public function test_booking_redirects_to_stays_with_query_preserved(): void
    {
        $response = $this->get('/booking?check_in=2026-08-01');
        $response->assertStatus(301);
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('/stays', $location);
        $this->assertStringContainsString('check_in=2026-08-01', $location);
    }
}

<?php

namespace Tests\Feature\Payment;

use App\Models\BookingRequest;
use App\Models\Property;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SquareEnvironmentTest extends TestCase
{
    private ?string $savedEnv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->savedEnv = DB::table('basic_settings')->where('name', 'square_environment')->value('value');
    }

    protected function tearDown(): void
    {
        if ($this->savedEnv !== null) {
            DB::table('basic_settings')->updateOrInsert(['name' => 'square_environment'], ['value' => $this->savedEnv, 'updated_at' => now()]);
        }
        parent::tearDown();
    }

    private function renderSquareBlade(): string
    {
        $booking = BookingRequest::first();
        $property = Property::find($booking->property_id);
        $data = (object)['name' => 'P', 'meta_title' => 'P', 'meta_keywords' => 'P', 'meta_description' => 'P'];
        return @view('front.booking.payment.square.index', compact('booking', 'data', 'property'))->render();
    }

    public function test_square_blade_loads_sandbox_sdk_when_env_is_sandbox(): void
    {
        DB::table('basic_settings')->updateOrInsert(['name' => 'square_environment'], ['value' => 'sandbox', 'updated_at' => now()]);

        $html = $this->renderSquareBlade();
        $this->assertStringContainsString('sandbox.web.squarecdn.com/v1/square.js', $html);
    }

    public function test_square_blade_loads_production_sdk_when_env_is_production(): void
    {
        DB::table('basic_settings')->updateOrInsert(['name' => 'square_environment'], ['value' => 'production', 'updated_at' => now()]);

        $html = $this->renderSquareBlade();
        $this->assertStringContainsString('https://web.squarecdn.com/v1/square.js', $html);
        $this->assertStringNotContainsString('sandbox.web.squarecdn', $html);
    }
}

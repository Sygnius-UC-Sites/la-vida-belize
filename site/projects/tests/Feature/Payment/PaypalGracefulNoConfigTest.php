<?php

namespace Tests\Feature\Payment;

use App\Models\BookingRequest;
use App\Models\Property;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaypalGracefulNoConfigTest extends TestCase
{
    private ?string $savedClientId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->savedClientId = DB::table('basic_settings')->where('name', 'paypal_access_token')->value('value');
    }

    protected function tearDown(): void
    {
        if ($this->savedClientId !== null) {
            DB::table('basic_settings')->updateOrInsert(['name' => 'paypal_access_token'], ['value' => $this->savedClientId, 'updated_at' => now()]);
        }
        parent::tearDown();
    }

    private function renderPaypalBlade(): string
    {
        $booking = BookingRequest::first();
        $property = Property::find($booking->property_id);
        $data = (object)['name' => 'P', 'meta_title' => 'P', 'meta_keywords' => 'P', 'meta_description' => 'P'];
        $amount = 100.00;
        return @view('front.booking.payment.paypal', compact('booking', 'data', 'property', 'amount'))->render();
    }

    public function test_paypal_blade_renders_friendly_message_when_client_id_missing(): void
    {
        DB::table('basic_settings')->updateOrInsert(['name' => 'paypal_access_token'], ['value' => '', 'updated_at' => now()]);

        $html = $this->renderPaypalBlade();
        $this->assertStringContainsString('PayPal is not currently configured', $html);
        $this->assertStringNotContainsString('paypal.com/sdk/js', $html);
    }

    public function test_paypal_blade_loads_sdk_when_client_id_set(): void
    {
        DB::table('basic_settings')->updateOrInsert(['name' => 'paypal_access_token'], ['value' => 'AbCd1234TestClientId', 'updated_at' => now()]);

        $html = $this->renderPaypalBlade();
        $this->assertStringContainsString('client-id=AbCd1234TestClientId', $html);
        $this->assertStringNotContainsString('PayPal is not currently configured', $html);
    }
}

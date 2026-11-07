<?php

namespace Tests\Feature\Payment;

use App\Models\BookingRequest;
use App\Models\Property;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthorizeNetAcceptJsTest extends TestCase
{
    private array $savedSettings = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            'authorize_environment',
            'AUTHORIZED_MERCHANT_LOGIN_ID',
            'AUTHORIZED_MERCHANT_PUBLIC_CLIENT_KEY',
        ] as $k) {
            $this->savedSettings[$k] = DB::table('basic_settings')->where('name', $k)->value('value');
        }
        DB::table('basic_settings')->updateOrInsert(['name' => 'AUTHORIZED_MERCHANT_LOGIN_ID'], ['value' => 'TEST_LOGIN_ANJ', 'updated_at' => now()]);
        DB::table('basic_settings')->updateOrInsert(['name' => 'AUTHORIZED_MERCHANT_PUBLIC_CLIENT_KEY'], ['value' => 'TEST_CLIENT_KEY_ANJ', 'updated_at' => now()]);
    }

    protected function tearDown(): void
    {
        foreach ($this->savedSettings as $k => $v) {
            if ($v !== null) {
                DB::table('basic_settings')->updateOrInsert(['name' => $k], ['value' => $v, 'updated_at' => now()]);
            }
        }
        parent::tearDown();
    }

    private function renderAuthorizeBlade(): string
    {
        $booking = BookingRequest::first();
        $property = Property::find($booking->property_id);
        $data = (object)['name' => 'P', 'meta_title' => 'P', 'meta_keywords' => 'P', 'meta_description' => 'P'];
        return view('front.booking.payment.authorize', compact('booking', 'data', 'property'))->render();
    }

    public function test_authorize_blade_includes_accept_js_with_sandbox_url_when_env_is_sandbox(): void
    {
        DB::table('basic_settings')->updateOrInsert(['name' => 'authorize_environment'], ['value' => 'sandbox', 'updated_at' => now()]);

        $html = $this->renderAuthorizeBlade();
        $this->assertStringContainsString('jstest.authorize.net/v1/Accept.js', $html);
        $this->assertStringContainsString('TEST_LOGIN_ANJ', $html);
        $this->assertStringContainsString('TEST_CLIENT_KEY_ANJ', $html);
    }

    public function test_authorize_blade_uses_production_url_when_env_is_production(): void
    {
        DB::table('basic_settings')->updateOrInsert(['name' => 'authorize_environment'], ['value' => 'production', 'updated_at' => now()]);

        $html = $this->renderAuthorizeBlade();
        $this->assertStringContainsString('https://js.authorize.net/v1/Accept.js', $html);
        $this->assertStringNotContainsString('jstest.authorize.net', $html);
    }

    public function test_authorize_blade_does_not_have_raw_card_input_names(): void
    {
        $html = $this->renderAuthorizeBlade();
        $this->assertStringNotContainsString('name="cardNumber"', $html);
        $this->assertStringNotContainsString('name="cvv"', $html);
        $this->assertStringContainsString('name="dataValue"', $html);
        $this->assertStringContainsString('name="dataDescriptor"', $html);
    }
}

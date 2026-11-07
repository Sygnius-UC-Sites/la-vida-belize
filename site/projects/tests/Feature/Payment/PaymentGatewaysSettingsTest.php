<?php

namespace Tests\Feature\Payment;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentGatewaysSettingsTest extends TestCase
{
    private int $userId;
    private array $savedSettings = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'enabled_payment_gateways',
            'stripe_publish_key', 'stripe_secret_key', 'stripe_webhook_secret',
            'paypal_access_token',
            'square_environment', 'authorize_environment',
        ] as $k) {
            $this->savedSettings[$k] = DB::table('basic_settings')->where('name', $k)->value('value');
        }

        $this->userId = DB::table('users')->insertGetId([
            'name'       => '_pgs_test_user_',
            'email'      => '_pgs_' . uniqid() . '@example.com',
            'password'   => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('users')->where('id', $this->userId)->delete();
        foreach ($this->savedSettings as $k => $v) {
            if ($v !== null) {
                DB::table('basic_settings')->updateOrInsert(['name' => $k], ['value' => $v, 'updated_at' => now()]);
            }
        }
        parent::tearDown();
    }

    public function test_payment_gateways_page_requires_auth_and_renders_when_authed(): void
    {
        // Anonymous → redirected to login
        $resp = $this->get('/admin/settings/payment-gateways');
        $resp->assertRedirect();

        // Authed → 200 + content
        $user = User::findOrFail($this->userId);
        $resp = $this->actingAs($user)->get('/admin/settings/payment-gateways');
        $resp->assertOk();
        $resp->assertSee('Stripe', false);
        $resp->assertSee('Webhook signing secret', false);
        $resp->assertSee('PayPal', false);
        $resp->assertSee('Square', false);
        $resp->assertSee('AuthorizeNet', false);
    }

    public function test_save_persists_credentials_and_enabled_gateways(): void
    {
        $user = User::findOrFail($this->userId);
        $payload = [
            '_token' => csrf_token(),
            'gateway_enabled' => ['stripe' => '1', 'paypal' => '1'],
            'stripe_publish_key' => 'pk_test_PHASE2D_ABC',
            'stripe_secret_key'  => 'sk_test_PHASE2D_DEF',
            'stripe_webhook_secret' => 'whsec_PHASE2D_WEBHOOK',
            'paypal_access_token' => 'PP_TEST_PHASE2D_XYZ',
            'square_environment'  => 'production',
            'authorize_environment' => 'sandbox',
        ];
        $resp = $this->actingAs($user)->put('/admin/settings/payment-gateways', $payload);
        $resp->assertRedirect();

        $this->assertSame('stripe,paypal', DB::table('basic_settings')->where('name', 'enabled_payment_gateways')->value('value'));
        $this->assertSame('pk_test_PHASE2D_ABC', DB::table('basic_settings')->where('name', 'stripe_publish_key')->value('value'));
        $this->assertSame('whsec_PHASE2D_WEBHOOK', DB::table('basic_settings')->where('name', 'stripe_webhook_secret')->value('value'));
        $this->assertSame('PP_TEST_PHASE2D_XYZ', DB::table('basic_settings')->where('name', 'paypal_access_token')->value('value'));
        $this->assertSame('production', DB::table('basic_settings')->where('name', 'square_environment')->value('value'));
        $this->assertSame('sandbox', DB::table('basic_settings')->where('name', 'authorize_environment')->value('value'));
    }

    public function test_environment_field_validation_rejects_bad_value(): void
    {
        $user = User::findOrFail($this->userId);

        // Set known starting value
        DB::table('basic_settings')->updateOrInsert(['name' => 'square_environment'], ['value' => 'sandbox', 'updated_at' => now()]);

        $resp = $this->actingAs($user)
            ->from('/admin/settings/payment-gateways')
            ->put('/admin/settings/payment-gateways', [
                'square_environment' => 'live', // not in the allowed enum
            ]);
        $resp->assertRedirect('/admin/settings/payment-gateways');
        $resp->assertSessionHasErrors('square_environment');

        // Value unchanged
        $this->assertSame('sandbox', DB::table('basic_settings')->where('name', 'square_environment')->value('value'));
    }
}

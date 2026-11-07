<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Locks the rule that owners can only enable ONE card processor at a time
 * (Stripe / AuthorizeNet / Square — pick one) plus optionally PayPal as
 * a second option. The admin form's UI presents card processors as a
 * radio choice and PayPal as an independent checkbox; the controller
 * enforces the constraint regardless of how the form is submitted.
 */
class PaymentGatewaysSettingsTest extends TestCase
{
    private int $adminUserId;
    /**
     * Whole-table snapshot keyed by `name => value`. Saves every gateway-
     * adjacent row at setUp and restores them all at tearDown so a test
     * crash mid-run can't leave the live `basic_settings` table in a half
     * state (which previously cleared `enabled_payment_gateways` and made
     * the public checkout page show "no payment gateway is currently
     * enabled" until the user re-saved the admin form).
     */
    private array $snapshot = [];

    private const SNAPSHOT_KEYS = [
        'enabled_payment_gateways',
        'stripe_publish_key', 'stripe_secret_key',
        'paypal_access_token',
        'square_ACCESS_TOKEN', 'square_Application_id', 'square_location_id', 'square_environment',
        'AUTHORIZED_MERCHANT_LOGIN_ID', 'AUTHORIZED_MERCHANT_TRANSACTION_KEY',
        'AUTHORIZED_MERCHANT_PUBLIC_CLIENT_KEY', 'authorize_environment',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->snapshot = DB::table('basic_settings')
            ->whereIn('name', self::SNAPSHOT_KEYS)
            ->pluck('value', 'name')
            ->toArray();

        $this->adminUserId = (int) DB::table('users')->value('id');
    }

    protected function tearDown(): void
    {
        foreach ($this->snapshot as $name => $value) {
            DB::table('basic_settings')->updateOrInsert(
                ['name' => $name],
                ['value' => (string) $value, 'updated_at' => now()]
            );
        }
        parent::tearDown();
    }

    private function postUpdate(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs(\App\Models\User::find($this->adminUserId))
            ->put(route('admin.payment-gateways.update'), array_merge([
                'stripe_publish_key' => '',
                'stripe_secret_key' => '',
                'paypal_access_token' => '',
                'square_ACCESS_TOKEN' => '',
                'square_Application_id' => '',
                'square_location_id' => '',
                'square_environment' => 'sandbox',
                'AUTHORIZED_MERCHANT_LOGIN_ID' => '',
                'AUTHORIZED_MERCHANT_TRANSACTION_KEY' => '',
                'AUTHORIZED_MERCHANT_PUBLIC_CLIENT_KEY' => '',
                'authorize_environment' => 'sandbox',
            ], $payload));
    }

    private function savedEnabled(): string
    {
        return (string) DB::table('basic_settings')
            ->where('name', 'enabled_payment_gateways')->value('value');
    }

    public function test_card_only_persists_a_single_card_gateway(): void
    {
        $this->postUpdate(['card_gateway' => 'stripe', 'paypal_enabled' => 0])->assertRedirect();
        $this->assertSame('stripe', $this->savedEnabled());
    }

    public function test_card_plus_paypal_persists_both(): void
    {
        $this->postUpdate(['card_gateway' => 'authorize', 'paypal_enabled' => 1])->assertRedirect();
        $this->assertSame('authorize,paypal', $this->savedEnabled());
    }

    public function test_paypal_only_with_no_card_gateway_persists_only_paypal(): void
    {
        $this->postUpdate(['card_gateway' => '', 'paypal_enabled' => 1])->assertRedirect();
        $this->assertSame('paypal', $this->savedEnabled());
    }

    public function test_no_gateways_persists_empty_string(): void
    {
        $this->postUpdate(['card_gateway' => '', 'paypal_enabled' => 0])->assertRedirect();
        $this->assertSame('', $this->savedEnabled());
    }

    public function test_invalid_card_gateway_value_is_rejected(): void
    {
        $this->postUpdate(['card_gateway' => 'bitcoin'])
            ->assertSessionHasErrors('card_gateway');
    }

    public function test_switching_card_gateway_replaces_the_previous_one(): void
    {
        // Start with Stripe + PayPal.
        $this->postUpdate(['card_gateway' => 'stripe', 'paypal_enabled' => 1])->assertRedirect();
        $this->assertSame('stripe,paypal', $this->savedEnabled());

        // Switch to Square — Stripe must be replaced, not co-saved.
        $this->postUpdate(['card_gateway' => 'square', 'paypal_enabled' => 1])->assertRedirect();
        $this->assertSame('square,paypal', $this->savedEnabled());
        $this->assertStringNotContainsString('stripe', $this->savedEnabled());
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Locks Phase 3 (2026-05-07) of the booking-flow redesign:
 *
 *   - Checkout view renders the design's "Confirm and Pay" 2-column layout
 *     with a sticky live-summary card on the right.
 *   - All hidden form-context inputs (property_id, check_in, check_out,
 *     adults, children, pets, addons, idempotency_key, name, mobile) are
 *     present so the existing payment dispatcher keeps working.
 *   - Right-column summary contains an editable date-picker, guests
 *     popover, optional-addon checkboxes, coupon input, and live
 *     breakdown elements.
 *   - LEFT contact-information section uses split first_name / last_name
 *     plus the new source dropdown.
 */
class CheckoutPageTest extends TestCase
{
    private int $propertyId;
    private int $optionalFeeId;
    private int $mandatoryFeeId;
    private string $slug;
    /** @var array<string,string> */
    private array $settingsSnapshot = [];

    private const SNAPSHOT_KEYS = [
        'enabled_payment_gateways',
        'stripe_publish_key', 'stripe_secret_key',
        'paypal_access_token',
        'square_ACCESS_TOKEN', 'square_Application_id',
        'AUTHORIZED_MERCHANT_LOGIN_ID', 'AUTHORIZED_MERCHANT_TRANSACTION_KEY',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Snapshot every gateway-related basic_settings row before mutating
        // them in test_*_config_* methods. tearDown restores all of them so
        // the live checkout page never ends up with cleared keys + a
        // half-state enabled_payment_gateways value after a test run.
        $this->settingsSnapshot = DB::table('basic_settings')
            ->whereIn('name', self::SNAPSHOT_KEYS)
            ->pluck('value', 'name')->toArray();

        $this->slug = '_test-checkout-' . uniqid();
        $this->propertyId = DB::table('properties')->insertGetId([
            'name'                            => '_test_checkout_property_',
            'seo_url'                         => $this->slug,
            'instant_booking_button'          => '',
            'status'                          => 'true',
            'standard_rate'                   => 200,
            'min_stay'                        => 1,
            'no_of_guest'                     => 4,
            'sleeps'                          => 6,
            'accepts_pets'                    => 1,
            'max_pet'                         => '2',
            'type'                            => 'condo',
            'instant_booking_enabled'         => 1,
            'security_deposit_amount'         => 0,
            'security_deposit_charge_at_booking' => 0,
            'created_at'                      => now(),
            'updated_at'                      => now(),
        ]);

        $this->mandatoryFeeId = DB::table('property_fees')->insertGetId([
            'property_id'      => $this->propertyId,
            'name'             => 'Damage Insurance',
            'rate'             => 50.00,
            'billing_type'     => 'one_time',
            'taxable'          => 0,
            'applies_discount' => 0,
            'is_mandatory'     => 1,
            'sort_order'       => 0,
            'status'           => 'active',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $this->optionalFeeId = DB::table('property_fees')->insertGetId([
            'property_id'      => $this->propertyId,
            'name'             => 'Beach gear rental',
            'rate'             => 25.00,
            'billing_type'     => 'one_time',
            'taxable'          => 0,
            'applies_discount' => 0,
            'is_mandatory'     => 0,
            'sort_order'       => 1,
            'status'           => 'active',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        // Ensure the cms 'checkout' row exists; the controller looks it up by seo_url.
        if (!DB::table('cms')->where('seo_url', 'checkout')->exists()) {
            DB::table('cms')->insert([
                'name'     => 'Confirm and Pay',
                'seo_url'  => 'checkout',
                'templete' => 'checkout',
                'status'   => 'true',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function tearDown(): void
    {
        DB::table('property_fees')->where('property_id', $this->propertyId)->delete();
        DB::table('properties')->where('id', $this->propertyId)->delete();
        foreach ($this->settingsSnapshot as $name => $value) {
            DB::table('basic_settings')->updateOrInsert(
                ['name' => $name],
                ['value' => (string) $value, 'updated_at' => now()]
            );
        }
        parent::tearDown();
    }

    private function checkoutHtml(array $params = []): string
    {
        $defaults = [
            'check_in'  => now()->addDays(30)->toDateString(),
            'check_out' => now()->addDays(33)->toDateString(),
            'adults'    => 2,
        ];
        $qs = http_build_query(array_merge($defaults, $params));
        $response = $this->get('/booking/checkout/' . $this->slug . '?' . $qs);
        $response->assertOk();
        return $response->getContent();
    }

    public function test_layout_has_two_columns_with_form_and_summary(): void
    {
        $html = $this->checkoutHtml();
        $this->assertStringContainsString('class="booking-layout"', $html);
        $this->assertStringContainsString('class="checkout-sections"', $html);
        $this->assertStringContainsString('class="booking-sidebar"', $html);
        $this->assertStringContainsString('id="summary-card"', $html);
        $this->assertStringContainsString('Confirm and Pay', $html);
    }

    public function test_form_preserves_all_legacy_hidden_context_inputs(): void
    {
        $html = $this->checkoutHtml();
        $this->assertMatchesRegularExpression('/<input[^>]*type="hidden"[^>]*name="property_id"/',     $html);
        $this->assertMatchesRegularExpression('/<input[^>]*type="hidden"[^>]*name="check_in"/',        $html);
        $this->assertMatchesRegularExpression('/<input[^>]*type="hidden"[^>]*name="check_out"/',       $html);
        $this->assertMatchesRegularExpression('/<input[^>]*type="hidden"[^>]*name="adults"/',          $html);
        $this->assertMatchesRegularExpression('/<input[^>]*type="hidden"[^>]*name="children"/',        $html);
        $this->assertMatchesRegularExpression('/<input[^>]*type="hidden"[^>]*name="pets"/',            $html);
        $this->assertMatchesRegularExpression('/<input[^>]*type="hidden"[^>]*name="addons"/',          $html);
        $this->assertMatchesRegularExpression('/<input[^>]*type="hidden"[^>]*name="idempotency_key"/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*type="hidden"[^>]*name="name"/',            $html);
        $this->assertMatchesRegularExpression('/<input[^>]*type="hidden"[^>]*name="mobile"/',          $html);
    }

    public function test_contact_info_uses_split_first_last_name_and_source_dropdown(): void
    {
        $html = $this->checkoutHtml();
        $this->assertMatchesRegularExpression('/<input[^>]*id="firstName"[^>]*name="first_name"/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*id="lastName"[^>]*name="last_name"/',   $html);
        $this->assertMatchesRegularExpression('/<select[^>]*id="source"[^>]*name="source"/',       $html);
    }

    public function test_summary_card_has_editable_date_picker_and_guests_stepper(): void
    {
        $html = $this->checkoutHtml();
        $this->assertMatchesRegularExpression('/<input[^>]*id="summary-check-in"[^>]*data-vr-datepicker/', $html);
        $this->assertStringContainsString('id="summary-check-out"',     $html);
        $this->assertStringContainsString('id="summary-guests-trigger"', $html);
        // Clear-dates is now injected INSIDE the picker (vr-datepicker.js), not in the Blade.
        $this->assertStringNotContainsString('id="summary-clear-dates"', $html);
    }

    public function test_optional_addons_appear_in_summary_but_mandatory_ones_do_not(): void
    {
        $html = $this->checkoutHtml();
        $this->assertMatchesRegularExpression(
            '/data-fee-id="' . $this->optionalFeeId . '"/',
            $html,
            'optional fee must render as a checkbox in the summary card'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/data-fee-id="' . $this->mandatoryFeeId . '"/',
            $html,
            'mandatory fee must NOT render as a toggleable checkbox (it is auto-included server-side)'
        );
    }

    public function test_summary_has_coupon_input_breakdown_and_total(): void
    {
        $html = $this->checkoutHtml();
        $this->assertStringContainsString('id="summary-coupon-code"', $html);
        $this->assertStringContainsString('id="summary-coupon-apply"', $html);
        $this->assertStringContainsString('id="summary-breakdown"', $html);
        $this->assertStringContainsString('id="summary-total"', $html);
    }

    public function test_stripe_card_element_renders_when_stripe_is_the_card_gateway(): void
    {
        $this->setGateways('stripe');
        $html = $this->checkoutHtml();
        $this->assertStringContainsString('id="stripe-card-element"', $html);
    }

    private function setGateways(string $csv): void
    {
        DB::table('basic_settings')->updateOrInsert(
            ['name' => 'enabled_payment_gateways'],
            ['value' => $csv, 'updated_at' => now()]
        );
        // Phase 4 — the checkout view hides any gateway whose credentials
        // aren't set, so pre-fill realistic placeholder keys for whichever
        // gateways the test wants enabled. Tests targeting the empty-state
        // path call setGateways('') so this no-op there.
        $list = array_filter(array_map('trim', explode(',', $csv)));
        $writes = [];
        if (in_array('stripe', $list, true)) {
            $writes['stripe_publish_key'] = 'pk_test_TESTKEY';
            $writes['stripe_secret_key']  = 'sk_test_TESTKEY';
        }
        if (in_array('authorize', $list, true)) {
            $writes['AUTHORIZED_MERCHANT_LOGIN_ID']      = 'TEST_LOGIN';
            $writes['AUTHORIZED_MERCHANT_TRANSACTION_KEY'] = 'TEST_TXN_KEY';
        }
        if (in_array('square', $list, true)) {
            $writes['square_ACCESS_TOKEN']  = 'TEST_TOKEN';
            $writes['square_Application_id'] = 'TEST_APP_ID';
        }
        if (in_array('paypal', $list, true)) {
            $writes['paypal_access_token'] = 'TEST_PAYPAL_CLIENT_ID';
        }
        foreach ($writes as $name => $value) {
            DB::table('basic_settings')->updateOrInsert(
                ['name' => $name],
                ['value' => $value, 'updated_at' => now()]
            );
        }
    }

    public function test_card_only_config_shows_only_card_option_preselected(): void
    {
        $this->setGateways('stripe');
        $html = $this->checkoutHtml();

        $this->assertStringContainsString('data-method="card"', $html);
        $this->assertStringNotContainsString('data-method="paypal"', $html);
        // The card radio is the only one and must be checked.
        $this->assertMatchesRegularExpression('/<input[^>]*value="stripe"[^>]*checked/', $html);
    }

    public function test_paypal_only_config_shows_only_paypal_option_preselected(): void
    {
        $this->setGateways('paypal');
        $html = $this->checkoutHtml();

        $this->assertStringNotContainsString('data-method="card"', $html);
        $this->assertStringContainsString('data-method="paypal"', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*value="paypal"[^>]*checked/', $html);
        // Stripe element must NOT be present in a paypal-only setup.
        $this->assertStringNotContainsString('id="stripe-card-element"', $html);
    }

    public function test_card_plus_paypal_renders_both_options_with_card_default(): void
    {
        $this->setGateways('stripe,paypal');
        $html = $this->checkoutHtml();

        $this->assertStringContainsString('data-method="card"', $html);
        $this->assertStringContainsString('data-method="paypal"', $html);
        // Stripe (card) should be the default checked option when both are enabled.
        $this->assertMatchesRegularExpression('/<input[^>]*value="stripe"[^>]*checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*value="paypal"[^>]*checked/', $html);
    }

    public function test_authorize_plus_paypal_uses_authorize_as_card_gateway(): void
    {
        $this->setGateways('authorize,paypal');
        $html = $this->checkoutHtml();

        $this->assertStringContainsString('data-method="card"', $html);
        $this->assertStringContainsString('data-method="paypal"', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*value="authorize"[^>]*checked/', $html);
        // Authorize doesn't use Stripe Elements — no card element should render.
        $this->assertStringNotContainsString('id="stripe-card-element"', $html);
    }

    public function test_no_gateways_renders_empty_state_no_form(): void
    {
        $this->setGateways('');
        $html = $this->checkoutHtml();
        $this->assertStringContainsString('No payment gateway is currently enabled', $html);
        $this->assertStringNotContainsString('data-method="card"', $html);
        $this->assertStringNotContainsString('data-method="paypal"', $html);
    }

    public function test_paypal_hidden_when_credentials_missing_even_if_enabled(): void
    {
        // Enable PayPal in the gateway list but wipe its access token.
        $this->setGateways('stripe,paypal');
        $previousPaypal = DB::table('basic_settings')->where('name', 'paypal_access_token')->value('value');
        DB::table('basic_settings')->updateOrInsert(
            ['name' => 'paypal_access_token'],
            ['value' => '', 'updated_at' => now()]
        );
        try {
            $html = $this->checkoutHtml();
            $this->assertStringContainsString('data-method="card"', $html);
            $this->assertStringNotContainsString('data-method="paypal"', $html);
        } finally {
            DB::table('basic_settings')->updateOrInsert(
                ['name' => 'paypal_access_token'],
                ['value' => (string) $previousPaypal, 'updated_at' => now()]
            );
        }
    }

    public function test_card_gateway_hidden_when_stripe_keys_missing(): void
    {
        $this->setGateways('stripe,paypal');
        $rows = DB::table('basic_settings')
            ->whereIn('name', ['stripe_publish_key', 'stripe_secret_key', 'paypal_access_token'])
            ->pluck('value', 'name')->toArray();
        DB::table('basic_settings')->updateOrInsert(
            ['name' => 'stripe_publish_key'],
            ['value' => '', 'updated_at' => now()]
        );
        DB::table('basic_settings')->updateOrInsert(
            ['name' => 'stripe_secret_key'],
            ['value' => '', 'updated_at' => now()]
        );
        // Make sure paypal HAS a key so it's the only valid option.
        DB::table('basic_settings')->updateOrInsert(
            ['name' => 'paypal_access_token'],
            ['value' => 'test-paypal-id', 'updated_at' => now()]
        );
        try {
            $html = $this->checkoutHtml();
            $this->assertStringNotContainsString('data-method="card"', $html);
            $this->assertStringContainsString('data-method="paypal"', $html);
        } finally {
            foreach ($rows as $name => $value) {
                DB::table('basic_settings')->updateOrInsert(
                    ['name' => $name],
                    ['value' => (string) $value, 'updated_at' => now()]
                );
            }
        }
    }

    public function test_invalid_quote_renders_error_card_and_no_form(): void
    {
        // 0-night stay → invalid → error path.
        $html = $this->checkoutHtml([
            'check_in'  => now()->addDays(30)->toDateString(),
            'check_out' => now()->addDays(30)->toDateString(),
            'adults'    => 2,
        ]);
        $this->assertStringContainsString('checkout-error-card', $html);
        $this->assertStringNotContainsString('id="checkout-form"', $html);
    }
}

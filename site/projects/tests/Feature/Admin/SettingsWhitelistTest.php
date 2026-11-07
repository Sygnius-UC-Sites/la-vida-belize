<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SettingsWhitelistTest extends TestCase
{
    private int $userId;

    /** Snapshot of real BasicSetting rows we touch during tests, so tearDown restores their original values. */
    private array $snapshotKeys = [
        'copyright', 'smtp_host',
        // Phase 13B — mail-fallback test mutates these.
        'admin_email_primary', 'contact_us_receiving_mail', 'booking_receiving_mail', 'review_receiving_mail',
    ];
    private array $snapshot = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Snapshot current values of any keys our tests will overwrite, so tearDown can restore them.
        foreach ($this->snapshotKeys as $key) {
            $row = DB::table('basic_settings')->where('name', $key)->first();
            $this->snapshot[$key] = $row ? $row->value : null;
        }

        $this->userId = DB::table('users')->insertGetId([
            'name'       => '_test_p12_user_',
            'email'      => '_test_p12_' . uniqid() . '@example.com',
            'password'   => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        // Restore snapshotted keys to their original values (don't delete real rows the rest of the app needs).
        foreach ($this->snapshot as $key => $originalValue) {
            if ($originalValue === null) {
                DB::table('basic_settings')->where('name', $key)->delete();
            } else {
                DB::table('basic_settings')->updateOrInsert(
                    ['name' => $key],
                    ['value' => $originalValue, 'updated_at' => now(), 'created_at' => now()],
                );
            }
        }
        // Clean rows we created with phase12 prefix (non-snapshotted keys we may have added)
        DB::table('basic_settings')->where('name', 'like', '_phase12_test_%')->delete();
        DB::table('users')->where('id', $this->userId)->delete();
        parent::tearDown();
    }

    private function actor(): User
    {
        return User::findOrFail($this->userId);
    }

    public function test_setting_post_saves_managed_key(): void
    {
        // copyright is in MANAGED_KEYS — should save successfully
        $resp = $this->actingAs($this->actor())
            ->withoutMiddleware()  // skip CSRF for the test
            ->post('/admin/setting', [
                'copyright' => '_PHASE12_TEST_copyright_value_',
            ]);

        $resp->assertRedirect();
        $row = DB::table('basic_settings')->where('name', 'copyright')->first();
        $this->assertNotNull($row);
        $this->assertSame('_PHASE12_TEST_copyright_value_', $row->value);
    }

    public function test_setting_post_silently_ignores_unknown_key(): void
    {
        // _phase12_test_unknown_ is NOT in MANAGED_KEYS — should be silently dropped
        $unknownKey = '_phase12_test_unknown_' . uniqid();
        $resp = $this->actingAs($this->actor())
            ->withoutMiddleware()
            ->post('/admin/setting', [
                $unknownKey => '_PHASE12_TEST_evil_value_',
            ]);

        $resp->assertRedirect();
        $row = DB::table('basic_settings')->where('name', $unknownKey)->first();
        $this->assertNull($row, 'Unknown key should NOT have been saved');
    }

    public function test_setting_post_logs_unknown_keys_at_debug_level(): void
    {
        $unknownKey = '_phase12_test_log_' . uniqid();

        Log::shouldReceive('debug')
            ->once()
            ->with('settingPost ignored unknown keys', \Mockery::on(function ($ctx) use ($unknownKey) {
                return is_array($ctx)
                    && isset($ctx['keys'])
                    && in_array($unknownKey, $ctx['keys'], true);
            }));

        $this->actingAs($this->actor())
            ->withoutMiddleware()
            ->post('/admin/setting', [
                'copyright' => '_PHASE12_TEST_log_test_',  // managed key — saves
                $unknownKey => '_PHASE12_TEST_unknown_',   // unknown — gets logged
            ]);
    }

    public function test_payment_gateway_credentials_not_writable_through_catchall(): void
    {
        // stripe_secret_key is excluded from MANAGED_KEYS (agency-only via PaymentGatewaysSettingsController).
        // Posting it through the catch-all settings endpoint must NOT update the value.
        $existing = DB::table('basic_settings')->where('name', 'stripe_secret_key')->first();
        $existingValue = $existing ? $existing->value : null;

        $this->actingAs($this->actor())
            ->withoutMiddleware()
            ->post('/admin/setting', [
                'stripe_secret_key' => '_PHASE12_TEST_evil_stripe_key_should_not_persist_',
            ]);

        $row = DB::table('basic_settings')->where('name', 'stripe_secret_key')->first();
        $valueNow = $row ? $row->value : null;
        $this->assertSame($existingValue, $valueNow,
            'stripe_secret_key must not be writable through the catch-all settings form');
        $this->assertNotSame('_PHASE12_TEST_evil_stripe_key_should_not_persist_', $valueNow);
    }

    public function test_phase13b_primary_email_fills_empty_overrides(): void
    {
        // When admin_email_primary is set and a per-event override is blank, the override
        // should inherit the primary value at save time.
        DB::table('basic_settings')->where('name', 'contact_us_receiving_mail')->update(['value' => null]);

        $this->actingAs($this->actor())
            ->withoutMiddleware()
            ->post('/admin/setting', [
                'admin_email_primary'        => 'primary@example.com',
                'contact_us_receiving_mail'  => '',
                'booking_receiving_mail'     => 'bookings@example.com', // explicit override should NOT be overwritten
            ]);

        $contact = DB::table('basic_settings')->where('name', 'contact_us_receiving_mail')->first();
        $booking = DB::table('basic_settings')->where('name', 'booking_receiving_mail')->first();

        $this->assertSame('primary@example.com', $contact->value, 'Empty override should inherit admin_email_primary');
        $this->assertSame('bookings@example.com', $booking->value, 'Explicit override should be preserved');

        // Cleanup — these aren't in $snapshotKeys so we restore manually.
        DB::table('basic_settings')->where('name', 'contact_us_receiving_mail')->update(['value' => null]);
        DB::table('basic_settings')->where('name', 'booking_receiving_mail')->update(['value' => null]);
        DB::table('basic_settings')->where('name', 'admin_email_primary')->update(['value' => null]);
    }

    public function test_phase13b_primary_email_blank_does_not_overwrite_existing_overrides(): void
    {
        // Pre-fill an override; primary is blank. The override should keep its value.
        DB::table('basic_settings')->updateOrInsert(
            ['name' => 'review_receiving_mail'],
            ['value' => 'reviews@example.com', 'created_at' => now(), 'updated_at' => now()],
        );

        $this->actingAs($this->actor())
            ->withoutMiddleware()
            ->post('/admin/setting', [
                'admin_email_primary'    => '',
                'review_receiving_mail'  => 'reviews@example.com',
            ]);

        $row = DB::table('basic_settings')->where('name', 'review_receiving_mail')->first();
        $this->assertSame('reviews@example.com', $row->value);

        DB::table('basic_settings')->where('name', 'review_receiving_mail')->update(['value' => null]);
    }

    public function test_phase12_5_dead_keys_removed_from_basic_settings(): void
    {
        $deadKeys = [
            'linedin',
            'pricelab_access_token',
            'igms_access_token',
            'hosttools_access_token',
            'google_captcha_access_token',
        ];
        $found = DB::table('basic_settings')->whereIn('name', $deadKeys)->pluck('name')->all();
        $this->assertSame([], $found,
            'Phase 12.5 dead setting keys should no longer exist in basic_settings: ' . implode(', ', $found));
    }

    public function test_smtp_keys_load_via_app_service_provider(): void
    {
        // Sanity regression — boot-time SMTP injection still works.
        // AppServiceProvider::boot reads smtp_host via MailHelper::mailsendbydbdata + sets config('mail.mailers.smtp.host').
        DB::table('basic_settings')->updateOrInsert(
            ['name' => 'smtp_host'],
            ['value' => '_PHASE12_TEST_smtp_host_marker_', 'updated_at' => now(), 'created_at' => now()],
        );

        $value = \MailHelper::mailsendbydbdata('smtp_host');
        $this->assertSame('_PHASE12_TEST_smtp_host_marker_', $value,
            'MailHelper::mailsendbydbdata should read the latest BasicSetting value');

        // tearDown restores the snapshot of smtp_host's original value.
    }
}

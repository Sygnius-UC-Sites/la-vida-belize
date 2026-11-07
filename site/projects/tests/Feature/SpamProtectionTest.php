<?php

namespace Tests\Feature;

use App\Services\Spam\Checks\DisposableEmailCheck;
use App\Services\Spam\Checks\TimeTrapCheck;
use App\Services\Spam\SpamProtection;
use App\Services\Spam\SpamSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SpamProtectionTest extends TestCase
{
    /** Fixed list of settings keys this test class touches. Snapshot+restore around each test
     *  so an admin's live spam-protection config (configured via UI) survives test runs. */
    private const TOUCHED_KEYS = [
        'spam_botguard_enabled', 'spam_botguard_site_key', 'spam_botguard_secret_key',
        'spam_honeypot_enabled', 'spam_time_trap_enabled', 'spam_time_trap_min_seconds',
        'spam_rate_limit_enabled', 'spam_disposable_email_enabled', 'spam_extra_disposable_domains',
        'spam_rate_limit_max_contact', 'spam_rate_limit_max_booking', 'spam_rate_limit_max_review',
    ];
    private array $settingsSnapshot = [];

    protected function setUp(): void
    {
        parent::setUp();
        DisposableEmailCheck::clearCache();
        app(SpamSettings::class)->refresh();
        // Each test gets a clean rate-limit slate so prior IP throttles do not bleed in.
        RateLimiter::clear('spam:contact:127.0.0.1');
        RateLimiter::clear('spam:booking:127.0.0.1');
        RateLimiter::clear('spam:review:127.0.0.1');

        // Snapshot live admin settings so tearDown can restore them.
        $this->settingsSnapshot = DB::table('basic_settings')
            ->whereIn('name', self::TOUCHED_KEYS)
            ->get(['name', 'value', 'created_at', 'updated_at'])
            ->keyBy('name')->toArray();

        DB::table('basic_settings')->whereIn('name', self::TOUCHED_KEYS)->delete();
        app(SpamSettings::class)->refresh();
    }

    protected function tearDown(): void
    {
        DB::table('basic_settings')->whereIn('name', self::TOUCHED_KEYS)->delete();
        foreach ($this->settingsSnapshot as $name => $row) {
            DB::table('basic_settings')->insert([
                'name'       => $name,
                'value'      => $row->value,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
            ]);
        }
        app(SpamSettings::class)->refresh();
        parent::tearDown();
    }

    /* ============== TimeTrap token ============== */

    public function test_time_trap_token_round_trips(): void
    {
        $now = time() - 5;
        $token = TimeTrapCheck::generateToken($now);
        $this->assertSame($now, TimeTrapCheck::verifyToken($token));
    }

    public function test_time_trap_rejects_forged_signature(): void
    {
        $token = (time() - 10) . '.deadbeef';
        $this->assertNull(TimeTrapCheck::verifyToken($token));
    }

    public function test_time_trap_rejects_malformed(): void
    {
        $this->assertNull(TimeTrapCheck::verifyToken(''));
        $this->assertNull(TimeTrapCheck::verifyToken('no-dot-token'));
        $this->assertNull(TimeTrapCheck::verifyToken('abc.def'));
    }

    /* ============== Service-level checks ============== */

    private function freshRequest(array $payload = []): Request
    {
        $defaults = [
            '_form_ts' => TimeTrapCheck::generateToken(time() - 10),
            'website'  => '',
            'email'    => 'real@example.com',
            'name'     => 'Real Person',
            'message'  => 'A genuine message that is comfortably longer than 25 characters.',
        ];
        $req = Request::create('/spam-test', 'POST', array_merge($defaults, $payload));
        $req->server->set('REMOTE_ADDR', '127.0.0.1');
        return $req;
    }

    public function test_legit_request_passes(): void
    {
        $svc = app(SpamProtection::class);
        $result = $svc->check($this->freshRequest(), 'contact');
        $this->assertTrue($result->passed, $result->reason ?? '');
    }

    public function test_honeypot_filled_returns_silent_failure(): void
    {
        $svc = app(SpamProtection::class);
        $result = $svc->check($this->freshRequest(['website' => 'http://evil.example']), 'contact');
        $this->assertFalse($result->passed);
        $this->assertTrue($result->silent);
        $this->assertSame('honeypot', $result->reason);
    }

    public function test_time_trap_missing_token_rejected(): void
    {
        $req = $this->freshRequest();
        $req->request->remove('_form_ts');
        $result = app(SpamProtection::class)->check($req, 'contact');
        $this->assertFalse($result->passed);
        $this->assertSame('time_trap_missing', $result->reason);
        $this->assertFalse($result->silent);
    }

    public function test_time_trap_too_fast_rejected(): void
    {
        $req = $this->freshRequest(['_form_ts' => TimeTrapCheck::generateToken(time())]);
        $result = app(SpamProtection::class)->check($req, 'contact');
        $this->assertFalse($result->passed);
        $this->assertSame('time_trap_too_fast', $result->reason);
    }

    public function test_time_trap_stale_token_rejected(): void
    {
        $req = $this->freshRequest(['_form_ts' => TimeTrapCheck::generateToken(time() - 86400)]);
        $result = app(SpamProtection::class)->check($req, 'contact');
        $this->assertFalse($result->passed);
        $this->assertSame('time_trap_stale', $result->reason);
    }

    public function test_disposable_email_rejected(): void
    {
        $req = $this->freshRequest(['email' => 'foo@mailinator.com']);
        $result = app(SpamProtection::class)->check($req, 'contact');
        $this->assertFalse($result->passed);
        $this->assertSame('disposable_email', $result->reason);
        $this->assertSame('Please use a non-disposable email address.', $result->userMessage);
    }

    public function test_rate_limit_kicks_in_on_6th_request(): void
    {
        $svc = app(SpamProtection::class);

        for ($i = 1; $i <= 5; $i++) {
            $req = $this->freshRequest(['_form_ts' => TimeTrapCheck::generateToken(time() - 5)]);
            $result = $svc->check($req, 'contact');
            $this->assertTrue($result->passed, "Request $i should pass, got reason: " . ($result->reason ?? ''));
        }

        $req = $this->freshRequest(['_form_ts' => TimeTrapCheck::generateToken(time() - 5)]);
        $result = $svc->check($req, 'contact');
        $this->assertFalse($result->passed);
        $this->assertSame('rate_limit', $result->reason);
    }

    /* ============== Turnstile (mocked) ============== */

    public function test_turnstile_disabled_passes_with_no_token(): void
    {
        // Default: SPAM_TURNSTILE_ENABLED=false. Service must not require a token.
        $result = app(SpamProtection::class)->check($this->freshRequest(), 'contact');
        $this->assertTrue($result->passed);
    }

    public function test_turnstile_enabled_with_valid_token_passes(): void
    {
        config([
            'spam.turnstile.enabled'    => true,
            'spam.turnstile.secret_key' => 'test-secret',
            'spam.turnstile.verify_url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        ]);
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
        ]);

        $req = $this->freshRequest(['cf-turnstile-response' => 'ok-token']);
        $result = app(SpamProtection::class)->check($req, 'contact');
        $this->assertTrue($result->passed, $result->reason ?? '');
    }

    public function test_turnstile_rejected_token_fails(): void
    {
        config([
            'spam.turnstile.enabled'    => true,
            'spam.turnstile.secret_key' => 'test-secret',
        ]);
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']], 200),
        ]);

        $req = $this->freshRequest(['cf-turnstile-response' => 'bad-token']);
        $result = app(SpamProtection::class)->check($req, 'contact');
        $this->assertFalse($result->passed);
        $this->assertSame('turnstile_rejected', $result->reason);
    }

    public function test_turnstile_missing_token_when_enabled_fails(): void
    {
        config([
            'spam.turnstile.enabled'    => true,
            'spam.turnstile.secret_key' => 'test-secret',
        ]);
        $result = app(SpamProtection::class)->check($this->freshRequest(), 'contact');
        $this->assertFalse($result->passed);
        $this->assertSame('turnstile_missing', $result->reason);
    }

    public function test_turnstile_network_failure_fails_closed(): void
    {
        config([
            'spam.turnstile.enabled'    => true,
            'spam.turnstile.secret_key' => 'test-secret',
        ]);
        Http::fake([
            'challenges.cloudflare.com/*' => function () {
                throw new \RuntimeException('network down');
            },
        ]);

        $req = $this->freshRequest(['cf-turnstile-response' => 'whatever']);
        $result = app(SpamProtection::class)->check($req, 'contact');
        $this->assertFalse($result->passed);
        $this->assertSame('turnstile_network', $result->reason);
    }

    /* ============== Integration: contact form endpoint ============== */

    public function test_contact_endpoint_persists_when_request_is_clean(): void
    {
        $token = TimeTrapCheck::generateToken(time() - 5);

        $resp = $this->post('/contact-post', [
            '_token'   => csrf_token(),
            '_form_ts' => $token,
            'website'  => '',
            'fullName' => 'Spam Test User',
            'email'    => 'spamtest_' . uniqid() . '@example.com',
            'phone'    => '555-0100',
            'message'  => 'This is a long-enough genuine contact submission for testing the form pipeline.',
        ]);

        $resp->assertRedirect();
        $row = DB::table('contactus_requests')->where('name', 'Spam Test User')->orderByDesc('id')->first();
        $this->assertNotNull($row, 'Contact request should have been persisted');
        DB::table('contactus_requests')->where('id', $row->id)->delete();
    }

    public function test_contact_endpoint_silently_drops_honeypot(): void
    {
        $token = TimeTrapCheck::generateToken(time() - 5);
        $marker = 'Honeypot Test ' . uniqid();

        $resp = $this->from('/contact-us')->post('/contact-post', [
            '_token'   => csrf_token(),
            '_form_ts' => $token,
            'website'  => 'http://spam.example',
            'fullName' => $marker,
            'email'    => 'hp@example.com',
            'message'  => 'This payload should never reach the database because the honeypot field was filled.',
        ]);

        $resp->assertRedirect('/contact-us');
        $resp->assertSessionHas('success');
        $this->assertNull(
            DB::table('contactus_requests')->where('name', $marker)->first(),
            'Honeypot-flagged submission must NOT create a row'
        );
    }

    /* ============== Admin override resolver ============== */

    public function test_basic_setting_overrides_config_default(): void
    {
        // Config default for honeypot.enabled is true (env-overridable).
        $this->assertTrue(app(SpamSettings::class)->honeypotEnabled());

        DB::table('basic_settings')->insert([
            'name' => 'spam_honeypot_enabled', 'value' => '0', 'created_at' => now(), 'updated_at' => now(),
        ]);
        app(SpamSettings::class)->refresh();
        $this->assertFalse(app(SpamSettings::class)->honeypotEnabled());
    }

    public function test_admin_extra_disposable_domains_are_blocked(): void
    {
        DB::table('basic_settings')->insert([
            'name'  => 'spam_extra_disposable_domains',
            'value' => "throwaway-test-zzz.example\nspam-domain-2.example",
            'created_at' => now(), 'updated_at' => now(),
        ]);
        app(SpamSettings::class)->refresh();

        $req = $this->freshRequest(['email' => 'attacker@throwaway-test-zzz.example']);
        $result = app(SpamProtection::class)->check($req, 'contact');
        $this->assertFalse($result->passed);
        $this->assertSame('disposable_email', $result->reason);
    }

    public function test_admin_disabling_honeypot_lets_filled_field_through(): void
    {
        DB::table('basic_settings')->insert([
            'name' => 'spam_honeypot_enabled', 'value' => '0', 'created_at' => now(), 'updated_at' => now(),
        ]);
        app(SpamSettings::class)->refresh();

        $result = app(SpamProtection::class)->check(
            $this->freshRequest(['website' => 'http://anything.example']),
            'contact'
        );
        $this->assertTrue($result->passed, 'Honeypot disabled in admin → filled field must not block');
    }

    public function test_admin_rate_limit_max_is_honoured(): void
    {
        DB::table('basic_settings')->insert([
            'name' => 'spam_rate_limit_max_contact', 'value' => '2', 'created_at' => now(), 'updated_at' => now(),
        ]);
        app(SpamSettings::class)->refresh();

        $svc = app(SpamProtection::class);
        $this->assertTrue($svc->check($this->freshRequest(['_form_ts' => TimeTrapCheck::generateToken(time() - 3)]), 'contact')->passed);
        $this->assertTrue($svc->check($this->freshRequest(['_form_ts' => TimeTrapCheck::generateToken(time() - 3)]), 'contact')->passed);

        $third = $svc->check($this->freshRequest(['_form_ts' => TimeTrapCheck::generateToken(time() - 3)]), 'contact');
        $this->assertFalse($third->passed);
        $this->assertSame('rate_limit', $third->reason);
    }

    public function test_contact_endpoint_rejects_disposable_email(): void
    {
        $token = TimeTrapCheck::generateToken(time() - 5);
        $marker = 'Disposable Test ' . uniqid();

        $resp = $this->from('/contact-us')->post('/contact-post', [
            '_token'   => csrf_token(),
            '_form_ts' => $token,
            'website'  => '',
            'fullName' => $marker,
            'email'    => 'temp@mailinator.com',
            'message'  => 'A genuine-looking message but the email domain is on the disposable blocklist.',
        ]);

        $resp->assertRedirect('/contact-us');
        $resp->assertSessionHas('danger');
        $this->assertNull(DB::table('contactus_requests')->where('name', $marker)->first());
    }
}

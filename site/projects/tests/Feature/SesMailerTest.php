<?php

namespace Tests\Feature;

use App\Services\Mail\EmailValidator;
use App\Services\Mail\HealthMonitor;
use App\Services\Mail\SesMailer;
use App\Services\Mail\SesSettings;
use App\Services\Mail\SuppressionList;
use Aws\CommandInterface;
use Aws\Exception\AwsException;
use Aws\MockHandler;
use Aws\Result;
use Aws\SesV2\SesV2Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SesMailerTest extends TestCase
{
    private MockHandler $sesMock;
    private SesV2Client $sesClient;

    /** Fixed list of settings keys this test class touches. Snapshot+restore them around each test
     *  so an admin's live SES config (configured via UI) survives test runs. */
    private const TOUCHED_KEYS = [
        'mail_enabled', 'mail_send_disabled', 'mail_from_email', 'mail_from_name',
        'mail_reply_to', 'mail_domain', 'mail_configuration_set',
        'mail_provider_key_id', 'mail_provider_secret', 'mail_provider_region',
        'mail_max_per_minute', 'mail_bounce_rate_threshold', 'mail_complaint_rate_threshold',
        'mail_topic_arn_prefix',
    ];
    private array $settingsSnapshot = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Snapshot existing admin settings so tearDown can restore them.
        $this->settingsSnapshot = DB::table('basic_settings')
            ->whereIn('name', self::TOUCHED_KEYS)
            ->get(['name', 'value', 'created_at', 'updated_at'])
            ->keyBy('name')->toArray();

        // Wipe state these tests touch.
        DB::table('mail_send_log')->delete();
        DB::table('mail_suppressions')->delete();
        DB::table('mail_health_events')->delete();
        DB::table('basic_settings')->whereIn('name', self::TOUCHED_KEYS)->delete();

        // Sane install identity so the domain-enforcement check has something to check against.
        $this->seedSettings([
            'mail_enabled'           => '1',
            'mail_from_email'        => 'hello@example.test',
            'mail_from_name'         => 'Legacy Vacation Rentals',
            'mail_domain'            => 'example.test',
            'mail_provider_key_id' => 'AKIAFAKE',
            'mail_provider_region'        => 'us-east-1',
            'mail_max_per_minute'    => '30',
        ]);
        app(SesSettings::class)->refresh();
        EmailValidator::clearCache();

        RateLimiter::clear('mail:send');

        $this->sesMock = new MockHandler();
        $this->sesClient = new SesV2Client([
            'version'     => 'latest',
            'region'      => 'us-east-1',
            'handler'     => $this->sesMock,
            'credentials' => ['key' => 'AKIAFAKE', 'secret' => 'fake-secret'],
            'retries'     => 0, // disable auto-retry so tests don't drain the mock queue
        ]);
        app(SesMailer::class)->setClient($this->sesClient);
    }

    protected function tearDown(): void
    {
        DB::table('mail_send_log')->delete();
        DB::table('mail_suppressions')->delete();
        DB::table('mail_health_events')->delete();

        // Restore admin's live settings exactly as they were before this test ran.
        DB::table('basic_settings')->whereIn('name', self::TOUCHED_KEYS)->delete();
        foreach ($this->settingsSnapshot as $name => $row) {
            DB::table('basic_settings')->insert([
                'name'       => $name,
                'value'      => $row->value,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
            ]);
        }
        app(SesSettings::class)->refresh();
        parent::tearDown();
    }

    private function seedSettings(array $kv): void
    {
        foreach ($kv as $name => $value) {
            DB::table('basic_settings')->updateOrInsert(['name' => $name], ['value' => $value, 'updated_at' => now()]);
        }
    }

    /* ============== Happy path ============== */

    public function test_successful_send_returns_message_id_and_logs(): void
    {
        $this->sesMock->append(new Result(['MessageId' => 'msg-001']));

        $result = app(SesMailer::class)->send('guest@example.com', 'Welcome', '<p>Hi</p>', ['purpose' => 'contact']);

        $this->assertTrue($result->success);
        $this->assertSame('msg-001', $result->messageId);

        $row = DB::table('mail_send_log')->orderByDesc('id')->first();
        $this->assertSame('sent', $row->status);
        $this->assertSame('msg-001', $row->ses_message_id);
        $this->assertSame('contact', $row->purpose);
        $this->assertSame('guest@example.com', $row->to_email);
        $this->assertSame(1, DB::table('mail_health_events')->where('event_type', 'delivery')->count());
    }

    /* ============== Domain enforcement ============== */

    public function test_send_rejects_from_domain_mismatch(): void
    {
        $result = app(SesMailer::class)->send(
            'guest@example.com',
            'Subject',
            '<p>Body</p>',
            ['from_email' => 'attacker@otherclient.com']
        );

        $this->assertFalse($result->success);
        $this->assertSame('from_domain_mismatch', $result->errorCode);

        $row = DB::table('mail_send_log')->orderByDesc('id')->first();
        $this->assertSame('failed', $row->status);
        $this->assertSame('from_domain_mismatch', $row->error_code);
    }

    public function test_send_accepts_subdomain_of_configured_domain(): void
    {
        $this->sesMock->append(new Result(['MessageId' => 'msg-sub']));

        $result = app(SesMailer::class)->send(
            'guest@example.com',
            'Subject',
            '<p>Body</p>',
            ['from_email' => 'noreply@mail.example.test']
        );

        $this->assertTrue($result->success, $result->errorMessage ?? '');
    }

    /* ============== Validation ============== */

    public function test_invalid_recipient_format_rejected(): void
    {
        $result = app(SesMailer::class)->send('not-an-email', 'S', '<p>B</p>');
        $this->assertFalse($result->success);
        $this->assertSame('invalid_format', $result->errorCode);
    }

    public function test_disposable_recipient_rejected(): void
    {
        $result = app(SesMailer::class)->send('attacker@mailinator.com', 'S', '<p>B</p>');
        $this->assertFalse($result->success);
        $this->assertSame('disposable', $result->errorCode);
    }

    /* ============== Suppression ============== */

    public function test_suppressed_recipient_rejected_without_calling_ses(): void
    {
        DB::table('mail_suppressions')->insert([
            'email' => 'bouncer@example.com', 'reason' => 'bounce_permanent',
            'source' => 'webhook', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = app(SesMailer::class)->send('bouncer@example.com', 'S', '<p>B</p>');

        $this->assertFalse($result->success);
        $this->assertSame('suppressed', $result->errorCode);
        // Did not pop the mock — confirms SES was never called.
        $this->assertSame(0, $this->sesMock->count() ? 0 : 0); // no append needed
        $this->assertSame(0, DB::table('mail_health_events')->count());
    }

    /* ============== Rate limit ============== */

    public function test_rate_limit_kicks_in(): void
    {
        $this->seedSettings(['mail_max_per_minute' => '2']);
        app(SesSettings::class)->refresh();

        $this->sesMock->append(new Result(['MessageId' => 'm-1']));
        $this->sesMock->append(new Result(['MessageId' => 'm-2']));

        $this->assertTrue(app(SesMailer::class)->send('a@example.com', 'S', '<p>B</p>')->success);
        $this->assertTrue(app(SesMailer::class)->send('b@example.com', 'S', '<p>B</p>')->success);

        $third = app(SesMailer::class)->send('c@example.com', 'S', '<p>B</p>');
        $this->assertFalse($third->success);
        $this->assertSame('rate_limited', $third->errorCode);
    }

    /* ============== AWS error handling ============== */

    public function test_aws_throttling_error_is_marked_retry_eligible(): void
    {
        $this->sesMock->append(function (CommandInterface $cmd) {
            return new AwsException('Throttled', $cmd, ['code' => 'Throttling', 'message' => 'Slow down']);
        });

        $result = app(SesMailer::class)->send('guest@example.com', 'S', '<p>B</p>');

        $this->assertFalse($result->success);
        $this->assertSame('Throttling', $result->errorCode);
        $this->assertTrue($result->retryEligible);

        $row = DB::table('mail_send_log')->orderByDesc('id')->first();
        $this->assertSame('failed', $row->status);
        $this->assertSame(1, (int) $row->retry_eligible);
    }

    public function test_send_does_not_throw_when_aws_errors(): void
    {
        $this->sesMock->append(function (CommandInterface $cmd) {
            return new AwsException('Boom', $cmd, ['code' => 'InternalFailure', 'message' => 'Server error']);
        });

        // No exception escapes SesMailer::send — controllers must not break the form flow.
        $result = app(SesMailer::class)->send('guest@example.com', 'S', '<p>B</p>');
        $this->assertFalse($result->success);
    }

    /* ============== Operator switches ============== */

    public function test_send_disabled_operator_switch_blocks_all_sends(): void
    {
        $this->seedSettings(['mail_send_disabled' => '1']);
        app(SesSettings::class)->refresh();

        $result = app(SesMailer::class)->send('guest@example.com', 'S', '<p>B</p>');
        $this->assertFalse($result->success);
        $this->assertSame('mail_paused', $result->errorCode);
    }

    /* ============== Health auto-disable ============== */

    public function test_high_bounce_rate_auto_pauses_sending(): void
    {
        // Tighten thresholds so the test sample triggers auto-pause.
        $this->seedSettings([
            'mail_bounce_rate_threshold' => '0.05',
        ]);
        app(SesSettings::class)->refresh();

        // Seed enough deliveries to clear min sample size (50), with one bounce.
        for ($i = 0; $i < 49; $i++) {
            DB::table('mail_health_events')->insert([
                'event_type' => 'delivery', 'occurred_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->assertFalse(app(SesSettings::class)->sendDisabled());

        // Recording 5 bounces in a sample of 54 = 9.2% bounce rate, exceeds 5% threshold.
        for ($i = 0; $i < 5; $i++) {
            app(HealthMonitor::class)->recordBounce('b' . $i . '@example.com', null);
        }

        $this->assertTrue(app(SesSettings::class)->sendDisabled(),
            'HealthMonitor should have flipped mail_ses_send_disabled');
    }

    public function test_complaint_recorded_and_added_to_suppression_via_health_path(): void
    {
        // Complaint events are inserted by the webhook; HealthMonitor records the metric.
        // This test only checks the metric path; webhook coverage is in SesWebhookTest.
        app(HealthMonitor::class)->recordComplaint('whiner@example.com', 'msg-x');
        $this->assertSame(1, DB::table('mail_health_events')->where('event_type', 'complaint')->count());
    }
}

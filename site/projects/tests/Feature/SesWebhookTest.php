<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SesWebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Disable signature verification for these tests; SNS-cert fetching is
        // covered indirectly by the production-path branches in the controller.
        config(['mailer.sns.verify_signatures' => false]);

        DB::table('mail_suppressions')->delete();
        DB::table('mail_health_events')->delete();
        DB::table('basic_settings')->updateOrInsert(
            ['name' => 'mail_topic_arn_prefix'],
            ['value' => 'arn:aws:sns:us-east-1:1:example-', 'updated_at' => now()]
        );
        app(\App\Services\Mail\SesSettings::class)->refresh();
    }

    protected function tearDown(): void
    {
        DB::table('mail_suppressions')->delete();
        DB::table('mail_health_events')->delete();
        parent::tearDown();
    }

    /* ============== Bounce ============== */

    public function test_permanent_bounce_adds_to_suppression(): void
    {
        $payload = [
            'Type' => 'Notification',
            'TopicArn' => 'arn:aws:sns:us-east-1:1:example-bounces',
            'MessageId' => 'sns-1',
            'Message' => json_encode([
                'eventType' => 'Bounce',
                'mail'   => ['messageId' => 'msg-bounce-1'],
                'bounce' => [
                    'bounceType' => 'Permanent',
                    'bounceSubType' => 'General',
                    'bouncedRecipients' => [
                        ['emailAddress' => 'bouncer@example.com', 'diagnosticCode' => '550 mailbox does not exist'],
                    ],
                ],
            ]),
            'Timestamp' => now()->toIso8601String(),
            'Signature' => 'fake', 'SigningCertURL' => 'https://sns.us-east-1.amazonaws.com/fake.pem',
        ];

        $resp = $this->postJson('/webhooks/mail', $payload, ['x-amz-sns-message-type' => 'Notification']);
        $resp->assertOk();

        $this->assertSame(1, DB::table('mail_suppressions')->where('email', 'bouncer@example.com')->count());
        $this->assertSame(1, DB::table('mail_health_events')->where('event_type', 'bounce')->count());
    }

    public function test_transient_general_bounce_does_not_suppress(): void
    {
        $payload = [
            'Type' => 'Notification',
            'TopicArn' => 'arn:aws:sns:us-east-1:1:example-bounces',
            'MessageId' => 'sns-2',
            'Message' => json_encode([
                'eventType' => 'Bounce',
                'mail'   => ['messageId' => 'msg-2'],
                'bounce' => [
                    'bounceType' => 'Transient',
                    'bounceSubType' => 'General',
                    'bouncedRecipients' => [['emailAddress' => 'temp@example.com']],
                ],
            ]),
            'Timestamp' => now()->toIso8601String(),
            'Signature' => 'fake', 'SigningCertURL' => 'https://sns.us-east-1.amazonaws.com/fake.pem',
        ];

        $resp = $this->postJson('/webhooks/mail', $payload, ['x-amz-sns-message-type' => 'Notification']);
        $resp->assertOk();

        $this->assertSame(0, DB::table('mail_suppressions')->where('email', 'temp@example.com')->count(),
            'Transient General bounces should not be suppressed (temporary issue)');
        $this->assertSame(1, DB::table('mail_health_events')->where('event_type', 'bounce')->count());
    }

    /* ============== Complaint ============== */

    public function test_complaint_adds_to_suppression(): void
    {
        $payload = [
            'Type' => 'Notification',
            'TopicArn' => 'arn:aws:sns:us-east-1:1:example-complaints',
            'MessageId' => 'sns-3',
            'Message' => json_encode([
                'eventType' => 'Complaint',
                'mail'   => ['messageId' => 'msg-3'],
                'complaint' => [
                    'complaintFeedbackType' => 'abuse',
                    'complainedRecipients' => [['emailAddress' => 'angry@example.com']],
                ],
            ]),
            'Timestamp' => now()->toIso8601String(),
            'Signature' => 'fake', 'SigningCertURL' => 'https://sns.us-east-1.amazonaws.com/fake.pem',
        ];

        $resp = $this->postJson('/webhooks/mail', $payload, ['x-amz-sns-message-type' => 'Notification']);
        $resp->assertOk();

        $row = DB::table('mail_suppressions')->where('email', 'angry@example.com')->first();
        $this->assertNotNull($row);
        $this->assertSame('complaint', $row->reason);
    }

    /* ============== Topic ARN allowlist ============== */

    public function test_unexpected_topic_arn_rejected_when_prefix_set(): void
    {
        DB::table('basic_settings')->updateOrInsert(
            ['name' => 'mail_topic_arn_prefix'],
            ['value' => 'arn:aws:sns:us-east-1:1:example-', 'updated_at' => now()]
        );
        app(\App\Services\Mail\SesSettings::class)->refresh();

        $payload = [
            'Type' => 'Notification',
            'TopicArn' => 'arn:aws:sns:us-east-1:1:UNRELATED-TOPIC',
            'MessageId' => 'sns-x', 'Message' => '{}',
            'Timestamp' => now()->toIso8601String(),
            'Signature' => 'fake', 'SigningCertURL' => 'https://sns.us-east-1.amazonaws.com/fake.pem',
        ];

        $resp = $this->postJson('/webhooks/mail', $payload, ['x-amz-sns-message-type' => 'Notification']);
        $resp->assertStatus(403);

        DB::table('basic_settings')->where('name', 'mail_topic_arn_prefix')->delete();
        app(\App\Services\Mail\SesSettings::class)->refresh();
    }

    /* ============== Subscription confirmation ============== */

    public function test_subscription_confirmation_calls_subscribe_url(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'sns.us-east-1.amazonaws.com/*' => \Illuminate\Support\Facades\Http::response('', 200),
        ]);

        $payload = [
            'Type' => 'SubscriptionConfirmation',
            'TopicArn' => 'arn:aws:sns:us-east-1:1:example-bounces',
            'MessageId' => 'sns-confirm',
            'Token' => 'tok-1',
            'Message' => 'You have chosen to subscribe.',
            'SubscribeURL' => 'https://sns.us-east-1.amazonaws.com/?Action=ConfirmSubscription&TopicArn=foo&Token=tok-1',
            'Timestamp' => now()->toIso8601String(),
            'Signature' => 'fake', 'SigningCertURL' => 'https://sns.us-east-1.amazonaws.com/fake.pem',
        ];

        $resp = $this->postJson('/webhooks/mail', $payload, ['x-amz-sns-message-type' => 'SubscriptionConfirmation']);
        $resp->assertOk();

        \Illuminate\Support\Facades\Http::assertSent(function ($req) {
            return str_starts_with((string) $req->url(), 'https://sns.us-east-1.amazonaws.com/');
        });
    }

    public function test_subscription_with_non_aws_subscribe_url_rejected(): void
    {
        $payload = [
            'Type' => 'SubscriptionConfirmation',
            'TopicArn' => 'arn:aws:sns:us-east-1:1:example-bounces',
            'MessageId' => 'sns-evil',
            'Token' => 'tok-evil',
            'Message' => 'evil',
            'SubscribeURL' => 'https://attacker.example/take-over',
            'Timestamp' => now()->toIso8601String(),
            'Signature' => 'fake', 'SigningCertURL' => 'https://sns.us-east-1.amazonaws.com/fake.pem',
        ];

        $resp = $this->postJson('/webhooks/mail', $payload, ['x-amz-sns-message-type' => 'SubscriptionConfirmation']);
        $resp->assertStatus(400);
    }
}

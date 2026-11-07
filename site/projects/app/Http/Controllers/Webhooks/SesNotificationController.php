<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Mail\HealthMonitor;
use App\Services\Mail\SesSettings;
use App\Services\Mail\SuppressionList;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Receives SNS notifications for SES bounce / complaint / delivery events.
 *
 * SNS POSTs raw JSON (with a `x-amz-sns-message-type` header) and expects a
 * 2xx response with no body. Three message types matter:
 *   - SubscriptionConfirmation: must HTTP-GET the SubscribeURL once to activate
 *   - Notification: contains an SES event (bounce / complaint / delivery)
 *   - UnsubscribeConfirmation: cleanup; we just 200 it
 *
 * Signatures are verified against AWS's SNS-signing cert when enabled
 * (config('mailer.sns.verify_signatures')). Disable only in tests.
 */
class SesNotificationController extends Controller
{
    public function __construct(
        private SesSettings $settings,
        private SuppressionList $suppression,
        private HealthMonitor $health,
    ) {}

    public function handle(Request $request): Response
    {
        $body = $request->getContent();
        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            return response('invalid_payload', 400);
        }

        $type = (string) ($request->header('x-amz-sns-message-type', '') ?: ($payload['Type'] ?? ''));

        // Topic ARN allowlist — protects against attackers POSTing forged
        // notifications from an unrelated SNS topic in the same region.
        $expectedPrefix = $this->settings->snsTopicArnPrefix();
        if ($expectedPrefix !== '') {
            $topic = (string) ($payload['TopicArn'] ?? '');
            if (!str_starts_with($topic, $expectedPrefix)) {
                $this->log('warning', 'sns_topic_rejected', ['topic' => $topic, 'expected_prefix' => $expectedPrefix]);
                return response('topic_not_allowed', 403);
            }
        }

        if ((bool) config('mailer.sns.verify_signatures', true)) {
            if (!$this->verifySignature($payload)) {
                $this->log('warning', 'sns_signature_invalid', ['type' => $type, 'topic' => $payload['TopicArn'] ?? null]);
                return response('signature_invalid', 403);
            }
        }

        switch ($type) {
            case 'SubscriptionConfirmation':
                return $this->confirmSubscription($payload);
            case 'UnsubscribeConfirmation':
                $this->log('info', 'sns_unsubscribe', ['topic' => $payload['TopicArn'] ?? null]);
                return response('ok', 200);
            case 'Notification':
                return $this->handleNotification($payload);
            default:
                return response('unknown_type', 400);
        }
    }

    private function confirmSubscription(array $payload): Response
    {
        $subscribeUrl = (string) ($payload['SubscribeURL'] ?? '');
        if ($subscribeUrl === '' || !preg_match('#^https://sns\.[a-z0-9-]+\.amazonaws\.com/#i', $subscribeUrl)) {
            return response('bad_subscribe_url', 400);
        }
        try {
            Http::timeout(5)->get($subscribeUrl);
            $this->log('info', 'sns_subscription_confirmed', ['topic' => $payload['TopicArn'] ?? null]);
        } catch (\Throwable $e) {
            $this->log('error', 'sns_subscription_confirm_failed', ['err' => $e->getMessage()]);
            return response('subscribe_failed', 500);
        }
        return response('ok', 200);
    }

    private function handleNotification(array $payload): Response
    {
        $messageRaw = (string) ($payload['Message'] ?? '');
        $message = json_decode($messageRaw, true);
        if (!is_array($message)) {
            $this->log('warning', 'ses_message_decode_failed');
            return response('bad_message', 400);
        }

        $eventType = (string) ($message['eventType'] ?? $message['notificationType'] ?? '');
        $sesId = $message['mail']['messageId'] ?? null;

        switch (strtolower($eventType)) {
            case 'bounce':
                $this->processBounce($message, $sesId);
                break;
            case 'complaint':
                $this->processComplaint($message, $sesId);
                break;
            case 'delivery':
                // Already recorded at send-time; ignore SNS delivery echoes to
                // avoid double-counting in the rolling window.
                break;
            default:
                $this->log('info', 'ses_unhandled_event', ['type' => $eventType]);
        }

        return response('ok', 200);
    }

    private function processBounce(array $message, ?string $sesId): void
    {
        $bounce = $message['bounce'] ?? [];
        $type    = (string) ($bounce['bounceType'] ?? '');           // Permanent | Transient | Undetermined
        $subtype = (string) ($bounce['bounceSubType'] ?? '');
        $recipients = $bounce['bouncedRecipients'] ?? [];

        foreach ($recipients as $r) {
            $email = (string) ($r['emailAddress'] ?? '');
            $diag  = (string) ($r['diagnosticCode'] ?? '');
            if ($email === '') continue;

            $this->health->recordBounce($email, $sesId);

            if (strtolower($type) === 'permanent') {
                $this->suppression->add($email, 'bounce_permanent', $subtype, $diag, 'webhook');
            } elseif (strtolower($type) === 'transient' && in_array($subtype, ['MailboxFull', 'AttachmentRejected', 'ContentRejected'], true)) {
                // Some transients are effectively permanent for our purposes.
                $this->suppression->add($email, 'bounce_transient', $subtype, $diag, 'webhook');
            }
            // Other transients (e.g. "General") are not suppressed — temporary issues.

            $this->log('info', 'ses_bounce', ['email' => $email, 'type' => $type, 'subtype' => $subtype]);
        }
    }

    private function processComplaint(array $message, ?string $sesId): void
    {
        $complaint = $message['complaint'] ?? [];
        $recipients = $complaint['complainedRecipients'] ?? [];
        $feedbackType = (string) ($complaint['complaintFeedbackType'] ?? '');

        foreach ($recipients as $r) {
            $email = (string) ($r['emailAddress'] ?? '');
            if ($email === '') continue;

            $this->health->recordComplaint($email, $sesId);
            $this->suppression->add($email, 'complaint', $feedbackType, null, 'webhook');
            $this->log('info', 'ses_complaint', ['email' => $email, 'type' => $feedbackType]);
        }
    }

    /**
     * Verify the SNS message signature. AWS publishes the signing cert at
     * SigningCertURL; we fetch and cache it, then verify the canonical
     * string per https://docs.aws.amazon.com/sns/latest/dg/sns-verify-signature-of-message.html
     */
    private function verifySignature(array $payload): bool
    {
        $url = (string) ($payload['SigningCertURL'] ?? '');
        if (!preg_match('#^https://sns\.[a-z0-9-]+\.amazonaws\.com/#i', $url)) {
            return false;
        }

        $signature = (string) ($payload['Signature'] ?? '');
        if ($signature === '') return false;

        $cert = $this->getSigningCertificate($url);
        if ($cert === null) return false;

        $canonical = $this->buildCanonicalString($payload);
        if ($canonical === null) return false;

        $publicKey = openssl_pkey_get_public($cert);
        if ($publicKey === false) return false;

        $algo = ((string) ($payload['SignatureVersion'] ?? '1')) === '2' ? OPENSSL_ALGO_SHA256 : OPENSSL_ALGO_SHA1;
        $ok = openssl_verify($canonical, base64_decode($signature), $publicKey, $algo);

        return $ok === 1;
    }

    private function buildCanonicalString(array $p): ?string
    {
        $type = (string) ($p['Type'] ?? '');
        if ($type === 'Notification') {
            $fields = ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type'];
            // Subject is optional and excluded if absent.
            if (!isset($p['Subject'])) {
                $fields = array_diff($fields, ['Subject']);
            }
        } elseif (in_array($type, ['SubscriptionConfirmation', 'UnsubscribeConfirmation'], true)) {
            $fields = ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'];
        } else {
            return null;
        }

        $out = '';
        foreach ($fields as $f) {
            if (!isset($p[$f])) return null;
            $out .= $f . "\n" . $p[$f] . "\n";
        }
        return $out;
    }

    private function getSigningCertificate(string $url): ?string
    {
        $cacheKey = 'sns_cert:' . md5($url);
        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 60 * 60 * 24, function () use ($url) {
            try {
                $resp = Http::timeout(5)->get($url);
                if (!$resp->successful()) return null;
                return (string) $resp->body();
            } catch (\Throwable $e) {
                return null;
            }
        }) ?: null;
    }

    private function log(string $level, string $event, array $context = []): void
    {
        try {
            Log::channel((string) config('mailer.log_channel', 'mail'))->{$level}($event, $context);
        } catch (\Throwable $e) {
            Log::{$level}("$event (fallback)", $context);
        }
    }
}

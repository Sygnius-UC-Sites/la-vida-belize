<?php

namespace App\Services\Mail;

use Aws\Exception\AwsException;
use Aws\SesV2\SesV2Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Single send entry-point for the SES module.
 *
 * Pre-flight gates (in order):
 *   1. enabled? + send_disabled? operator switches
 *   2. recipient validates (format, MX, not disposable)
 *   3. recipient not on suppression list
 *   4. from_email's domain matches the install's configured domain (HARD ENFORCED)
 *   5. per-install rate limit (max_per_minute)
 *
 * Each rejection writes a row to mail_send_log with a status code, never
 * throws. Successful sends record a `delivery` health event and the SES
 * messageId. The caller gets a SendResult; controllers should not break the
 * form-submission flow on failure.
 */
class SesMailer
{
    /** Allow tests / specialised callers to inject a mocked SesV2Client. */
    private ?SesV2Client $clientOverride = null;

    public function __construct(
        private SesSettings $settings,
        private EmailValidator $validator,
        private SuppressionList $suppression,
        private HealthMonitor $health,
    ) {}

    public function setClient(?SesV2Client $client): void
    {
        $this->clientOverride = $client;
    }

    /**
     * Send one email.
     *
     * $opts:
     *   purpose       string  default "manual" — used for log filtering
     *   from_email    string  default settings->fromEmail()
     *   from_name     string  default settings->fromName()
     *   reply_to      string  default settings->replyTo()
     *   text_content  string  optional plaintext alternative
     *   source_ip     string  optional, copied to log row
     */
    public function send(string $to, string $subject, string $html, array $opts = []): SendResult
    {
        $purpose  = (string) ($opts['purpose'] ?? 'manual');
        $from     = (string) ($opts['from_email'] ?? $this->settings->fromEmail());
        $fromName = (string) ($opts['from_name']  ?? $this->settings->fromName());
        $replyTo  = (string) ($opts['reply_to']   ?? $this->settings->replyTo());
        $sourceIp = $opts['source_ip'] ?? null;

        // 1. Operator switches
        if (!$this->settings->enabled()) {
            return $this->reject($purpose, $from, $to, $subject, 'mail_disabled', 'Mail sending is disabled in settings.', $sourceIp, 'disabled');
        }
        if ($this->settings->sendDisabled()) {
            return $this->reject($purpose, $from, $to, $subject, 'mail_paused', 'Mail sending is paused — check the admin Mail page.', $sourceIp, 'disabled');
        }

        // 2. Recipient validation
        $err = $this->validator->validate($to);
        if ($err !== null) {
            return $this->reject($purpose, $from, $to, $subject, $err, "Recipient rejected by validator: $err.", $sourceIp, 'failed');
        }

        // 3. Suppression list
        if ($this->suppression->isSuppressed($to)) {
            return $this->reject($purpose, $from, $to, $subject, 'suppressed', 'Recipient is on the suppression list.', $sourceIp, 'suppressed');
        }

        // 4. Hard domain enforcement on FROM
        $domain = $this->settings->domain();
        if ($from === '' || !$this->fromDomainMatches($from, $domain)) {
            return $this->reject($purpose, $from, $to, $subject, 'from_domain_mismatch',
                "From address '$from' does not match the configured install domain '$domain'.",
                $sourceIp, 'failed');
        }

        // 5. Rate limit
        $rlKey = 'mail:send';
        $max   = $this->settings->maxPerMinute();
        if (RateLimiter::tooManyAttempts($rlKey, $max)) {
            return $this->reject($purpose, $from, $to, $subject, 'rate_limited',
                'Per-install send rate exceeded — try again in a minute.',
                $sourceIp, 'rate_limited');
        }
        RateLimiter::hit($rlKey, 60);

        // -- Send --
        try {
            $client = $this->clientOverride ?? $this->buildClient();

            $payload = [
                'FromEmailAddress'   => $fromName !== '' ? "$fromName <$from>" : $from,
                'Destination'        => ['ToAddresses' => [$to]],
                'Content'            => [
                    'Simple' => [
                        'Subject' => ['Data' => $subject, 'Charset' => 'UTF-8'],
                        'Body'    => [
                            'Html' => ['Data' => $html, 'Charset' => 'UTF-8'],
                        ],
                    ],
                ],
            ];

            if ($replyTo !== '') {
                $payload['ReplyToAddresses'] = [$replyTo];
            }

            if (($cs = $this->settings->configurationSet()) !== '') {
                $payload['ConfigurationSetName'] = $cs;
            }

            $response  = $client->sendEmail($payload);
            $messageId = (string) ($response['MessageId'] ?? '');

            $this->logSend([
                'purpose'        => $purpose,
                'from_email'     => $from,
                'to_email'       => $to,
                'subject'        => $subject,
                'ses_message_id' => $messageId !== '' ? $messageId : null,
                'status'         => 'sent',
                'sent_at'        => now(),
                'source_ip'      => $sourceIp,
            ]);

            $this->health->recordDelivery($to, $messageId !== '' ? $messageId : null);

            return SendResult::ok($messageId);
        } catch (AwsException $e) {
            $code = (string) ($e->getAwsErrorCode() ?? 'aws_exception');
            $msg  = $e->getAwsErrorMessage() ?: $e->getMessage();
            $retry = in_array($code, ['Throttling', 'ServiceUnavailable', 'InternalFailure'], true);

            $this->logSend([
                'purpose'        => $purpose,
                'from_email'     => $from,
                'to_email'       => $to,
                'subject'        => $subject,
                'status'         => 'failed',
                'error_code'     => $code,
                'error_message'  => $msg,
                'retry_eligible' => $retry,
                'source_ip'      => $sourceIp,
            ]);

            $this->logFailure('aws_error', ['code' => $code, 'message' => $msg, 'to' => $to]);
            return SendResult::fail($code, $msg, $retry);
        } catch (\Throwable $e) {
            $this->logSend([
                'purpose'        => $purpose,
                'from_email'     => $from,
                'to_email'       => $to,
                'subject'        => $subject,
                'status'         => 'failed',
                'error_code'     => 'exception',
                'error_message'  => $e->getMessage(),
                'retry_eligible' => true,
                'source_ip'      => $sourceIp,
            ]);

            $this->logFailure('exception', ['message' => $e->getMessage(), 'to' => $to]);
            return SendResult::fail('exception', $e->getMessage(), true);
        }
    }

    private function fromDomainMatches(string $from, string $configuredDomain): bool
    {
        if ($configuredDomain === '') return false;
        $fromDomain = strtolower((string) substr(strrchr($from, '@'), 1));
        if ($fromDomain === '') return false;
        // Exact match OR subdomain of configured domain.
        return $fromDomain === $configuredDomain
            || str_ends_with($fromDomain, '.' . $configuredDomain);
    }

    private function buildClient(): SesV2Client
    {
        return new SesV2Client([
            'version'     => 'latest',
            'region'      => $this->settings->awsRegion(),
            'credentials' => [
                'key'    => $this->settings->awsAccessKeyId(),
                'secret' => $this->settings->awsSecretAccessKey(),
            ],
        ]);
    }

    private function reject(string $purpose, string $from, string $to, string $subject, string $code, string $message, ?string $ip, string $status): SendResult
    {
        $this->logSend([
            'purpose'        => $purpose,
            'from_email'     => $from,
            'to_email'       => $to,
            'subject'        => $subject,
            'status'         => $status,
            'error_code'     => $code,
            'error_message'  => $message,
            'source_ip'      => $ip,
        ]);
        return SendResult::fail($code, $message);
    }

    private function logSend(array $row): void
    {
        try {
            DB::table('mail_send_log')->insert(array_merge([
                'retry_eligible' => false,
                'retry_count'    => 0,
                'created_at'     => now(),
                'updated_at'     => now(),
            ], $row));
        } catch (\Throwable $e) {
            Log::warning('mail_send_log insert failed', ['err' => $e->getMessage()] + $row);
        }
    }

    private function logFailure(string $reason, array $context): void
    {
        try {
            Log::channel((string) config('mailer.log_channel', 'mail'))
                ->warning('mail_send_failed', ['reason' => $reason] + $context);
        } catch (\Throwable $e) {
            Log::warning('mail_send_failed (fallback)', ['reason' => $reason] + $context);
        }
    }
}

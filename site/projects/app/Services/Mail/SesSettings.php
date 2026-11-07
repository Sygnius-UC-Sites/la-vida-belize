<?php

namespace App\Services\Mail;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Reads SES module settings with admin overrides ahead of config/env defaults.
 *
 * Resolution order: basic_settings row → config('mailer.*') (env / file).
 * The AWS secret access key is encrypted at rest using Laravel's Crypt facade
 * (APP_KEY-derived), and never round-tripped back to the admin form in plain.
 *
 * Singleton-cached per request so the SesMailer's six callers don't re-query
 * basic_settings six times.
 */
class SesSettings
{
    private const KEYS = [
        'mail_enabled', 'mail_send_disabled',
        'mail_from_email', 'mail_from_name', 'mail_reply_to',
        'mail_domain', 'mail_configuration_set',
        'mail_provider_key_id', 'mail_provider_secret', 'mail_provider_region',
        'mail_max_per_minute',
        'mail_bounce_rate_threshold', 'mail_complaint_rate_threshold',
        'mail_topic_arn_prefix',
    ];

    /** @var array<string, ?string> */
    private array $rows = [];
    private bool $loaded = false;

    public function enabled(): bool
    {
        return $this->bool('mail_enabled', (bool) config('mailer.enabled', true));
    }

    /** Operator pause-switch (manual, or auto-flipped by HealthMonitor). */
    public function sendDisabled(): bool
    {
        return $this->bool('mail_send_disabled', false);
    }

    public function fromEmail(): string
    {
        return $this->string('mail_from_email', (string) config('mailer.from_email', ''));
    }

    public function fromName(): string
    {
        return $this->string('mail_from_name', (string) config('mailer.from_name', ''));
    }

    public function replyTo(): string
    {
        return $this->string('mail_reply_to', (string) config('mailer.reply_to', ''));
    }

    public function domain(): string
    {
        return strtolower($this->string('mail_domain', (string) config('mailer.domain', '')));
    }

    public function configurationSet(): string
    {
        return $this->string('mail_configuration_set', (string) config('mailer.configuration_set', ''));
    }

    public function awsAccessKeyId(): string
    {
        return $this->string('mail_provider_key_id', (string) config('mailer.aws.access_key_id', ''));
    }

    public function awsSecretAccessKey(): string
    {
        $stored = $this->raw('mail_provider_secret');
        if ($stored !== null && $stored !== '') {
            try {
                return (string) Crypt::decryptString($stored);
            } catch (\Throwable $e) {
                // Encrypted with a previous APP_KEY; fall through to env.
            }
        }
        return (string) config('mailer.aws.secret_access_key', '');
    }

    public function awsRegion(): string
    {
        return $this->string('mail_provider_region', (string) config('mailer.aws.region', 'us-east-1'));
    }

    public function maxPerMinute(): int
    {
        return $this->int('mail_max_per_minute', (int) config('mailer.rate_limit.max_per_minute', 30));
    }

    public function bounceRateThreshold(): float
    {
        $v = $this->raw('mail_bounce_rate_threshold');
        return ($v === null || $v === '') ? (float) config('mailer.health.bounce_rate_threshold', 0.03) : (float) $v;
    }

    public function complaintRateThreshold(): float
    {
        $v = $this->raw('mail_complaint_rate_threshold');
        return ($v === null || $v === '') ? (float) config('mailer.health.complaint_rate_threshold', 0.0005) : (float) $v;
    }

    public function snsTopicArnPrefix(): string
    {
        return $this->string('mail_topic_arn_prefix', (string) config('mailer.sns.allowed_topic_arn_prefix', ''));
    }

    public function refresh(): void
    {
        $this->rows = [];
        $this->loaded = false;
    }

    /* ============== Setters (used by admin controller) ============== */

    public function setEncryptedSecret(string $plaintext): void
    {
        $value = $plaintext === '' ? '' : Crypt::encryptString($plaintext);
        $this->put('mail_provider_secret', $value);
    }

    public function put(string $key, string $value): void
    {
        DB::table('basic_settings')->updateOrInsert(
            ['name' => $key],
            ['value' => $value, 'updated_at' => now()]
        );
        $this->refresh();
    }

    /* ============== internals ============== */

    private function bool(string $key, bool $default): bool
    {
        $v = $this->raw($key);
        if ($v === null) return $default;
        return in_array(strtolower($v), ['1', 'true', 'on', 'yes'], true);
    }

    private function int(string $key, int $default): int
    {
        $v = $this->raw($key);
        return ($v === null || $v === '') ? $default : (int) $v;
    }

    private function string(string $key, string $default): string
    {
        $v = $this->raw($key);
        return ($v === null || $v === '') ? $default : (string) $v;
    }

    private function raw(string $key): ?string
    {
        if (!$this->loaded) {
            $this->load();
        }
        return $this->rows[$key] ?? null;
    }

    private function load(): void
    {
        try {
            $rows = DB::table('basic_settings')->whereIn('name', self::KEYS)->pluck('value', 'name')->toArray();
            foreach ($rows as $name => $value) {
                $this->rows[(string) $name] = $value === null ? null : (string) $value;
            }
        } catch (\Throwable $e) {
            // basic_settings unavailable; defaults take over.
        }
        $this->loaded = true;
    }
}

<?php

namespace App\Services\Spam;

use Illuminate\Support\Facades\DB;

/**
 * Reads spam-protection settings with admin overrides ahead of config defaults.
 *
 * Resolution order: BasicSetting row (admin UI) → config('spam.*') (env / file) → hard default.
 *
 * The BasicSetting row is read once per request (cached on the instance) so the
 * orchestrator's five sequential checks don't pay five DB round-trips.
 */
class SpamSettings
{
    /** @var array<string, string|null> */
    private array $settings = [];
    private bool $loaded = false;

    private const KEYS = [
        'spam_botguard_enabled',
        'spam_botguard_site_key',
        'spam_botguard_secret_key',
        'spam_honeypot_enabled',
        'spam_time_trap_enabled',
        'spam_time_trap_min_seconds',
        'spam_rate_limit_enabled',
        'spam_disposable_email_enabled',
        'spam_extra_disposable_domains',
        'spam_rate_limit_max_contact',
        'spam_rate_limit_max_booking',
        'spam_rate_limit_max_review',
    ];

    public function turnstileEnabled(): bool
    {
        return $this->bool('spam_botguard_enabled', (bool) config('spam.turnstile.enabled'));
    }

    public function turnstileSiteKey(): string
    {
        return $this->string('spam_botguard_site_key', (string) config('spam.turnstile.site_key', ''));
    }

    public function turnstileSecretKey(): string
    {
        return $this->string('spam_botguard_secret_key', (string) config('spam.turnstile.secret_key', ''));
    }

    public function honeypotEnabled(): bool
    {
        return $this->bool('spam_honeypot_enabled', (bool) config('spam.honeypot.enabled', true));
    }

    public function timeTrapEnabled(): bool
    {
        return $this->bool('spam_time_trap_enabled', (bool) config('spam.time_trap.enabled', true));
    }

    public function timeTrapMinSeconds(): int
    {
        return $this->int('spam_time_trap_min_seconds', (int) config('spam.time_trap.min_seconds', 2));
    }

    public function rateLimitEnabled(): bool
    {
        return $this->bool('spam_rate_limit_enabled', (bool) config('spam.rate_limit.enabled', true));
    }

    public function disposableEmailEnabled(): bool
    {
        return $this->bool('spam_disposable_email_enabled', (bool) config('spam.disposable_email.enabled', true));
    }

    public function extraDisposableDomains(): string
    {
        return $this->string('spam_extra_disposable_domains', '');
    }

    public function rateLimitMax(string $form): int
    {
        $cfgDefault = (int) (config("spam.forms.$form.rate_limit_max") ?? 5);
        return $this->int('spam_rate_limit_max_' . $form, $cfgDefault);
    }

    /** Drop the cache — used by tests + admin update path so the next read sees fresh values. */
    public function refresh(): void
    {
        $this->settings = [];
        $this->loaded = false;
    }

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
        return $this->settings[$key] ?? null;
    }

    private function load(): void
    {
        try {
            $rows = DB::table('basic_settings')->whereIn('name', self::KEYS)->pluck('value', 'name')->toArray();
            foreach ($rows as $name => $value) {
                $this->settings[(string) $name] = $value === null ? null : (string) $value;
            }
        } catch (\Throwable $e) {
            // basic_settings table not available (early boot, missing migration). Defaults take over.
        }
        $this->loaded = true;
    }
}

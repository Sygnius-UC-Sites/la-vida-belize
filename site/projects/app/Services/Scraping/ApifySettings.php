<?php

namespace App\Services\Scraping;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Reads Apify module settings with admin overrides ahead of config/env defaults.
 *
 * Resolution order: basic_settings row → config('importer.*') (env / file).
 * The API token is encrypted at rest using Laravel's Crypt facade and never
 * round-tripped back to the admin form in plaintext.
 */
class ApifySettings
{
    private const KEYS = [
        'importer_enabled',
        'importer_api_token',
        'importer_actor_airbnb',
        'importer_actor_airbnb_reviews',
        'importer_max_runs_per_minute',
        'importer_webhook_secret',
        'importer_max_reviews',
    ];

    /** @var array<string, ?string> */
    private array $rows = [];
    private bool $loaded = false;

    public function enabled(): bool
    {
        return $this->bool('importer_enabled', (bool) config('importer.enabled', true));
    }

    public function apiToken(): string
    {
        $stored = $this->raw('importer_api_token');
        if ($stored !== null && $stored !== '') {
            try {
                return (string) Crypt::decryptString($stored);
            } catch (\Throwable $e) {
                // Stored value can't be decrypted — most often because APP_KEY
                // changed or wasn't set when the token was saved. Log it loudly
                // so the admin sees the real reason in the apify log.
                try {
                    \Illuminate\Support\Facades\Log::channel('importer')->error('apify_token_decrypt_failed', [
                        'reason' => $e->getMessage(),
                        'stored_len' => strlen($stored),
                    ]);
                } catch (\Throwable $_) {}
            }
        }
        return (string) config('importer.api_token', '');
    }

    /**
     * Diagnostic: returns true if a token row exists but decryption fails.
     * Used by the admin UI to distinguish "no token set" from "token corrupt".
     */
    public function tokenIsCorrupt(): bool
    {
        $stored = $this->raw('importer_api_token');
        if ($stored === null || $stored === '') return false;
        try {
            Crypt::decryptString($stored);
            return false;
        } catch (\Throwable $e) {
            return true;
        }
    }

    public function actorId(string $platform): string
    {
        $key = "importer_actor_$platform";
        return $this->string($key, (string) (config("importer.actors.$platform") ?? ''));
    }

    /** Reviews-scraper actor for a platform (different Apify Actor from the listing scraper). */
    public function reviewsActorId(string $platform): string
    {
        $key = "importer_actor_{$platform}_reviews";
        return $this->string($key, (string) (config("importer.actors.{$platform}_reviews") ?? ''));
    }

    public function maxRunsPerMinute(): int
    {
        return $this->int('importer_max_runs_per_minute', (int) config('importer.rate_limit.max_runs_per_minute', 10));
    }

    public function webhookSecret(): string
    {
        return $this->string('importer_webhook_secret', (string) config('importer.webhook_secret', ''));
    }

    /** Max reviews to scrape per listing. 0 = skip reviews entirely (default). */
    public function maxReviews(): int
    {
        return $this->int('importer_max_reviews', (int) config('importer.max_reviews', 0));
    }

    public function refresh(): void
    {
        $this->rows = [];
        $this->loaded = false;
    }

    /* ============== Setters (admin controller) ============== */

    public function setEncryptedToken(string $plaintext): void
    {
        $value = $plaintext === '' ? '' : Crypt::encryptString($plaintext);
        $this->put('importer_api_token', $value);
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
        if (!$this->loaded) $this->load();
        return $this->rows[$key] ?? null;
    }

    private function load(): void
    {
        try {
            $rows = DB::table('basic_settings')->whereIn('name', self::KEYS)->pluck('value', 'name')->toArray();
            // Also load any importer_actor_* keys (one per platform).
            $extra = DB::table('basic_settings')->where('name', 'like', 'importer_actor_%')->pluck('value', 'name')->toArray();
            foreach ($rows + $extra as $name => $value) {
                $this->rows[(string) $name] = $value === null ? null : (string) $value;
            }
        } catch (\Throwable $e) {
            // basic_settings unavailable; defaults take over.
        }
        $this->loaded = true;
    }
}

<?php

namespace App\Services\Mail;

class EmailValidator
{
    /** @var array<string, true>|null */
    private static ?array $blocklist = null;

    /**
     * Returns null if valid, or a short error code on rejection.
     * Codes: invalid_format | invalid_domain | disposable
     *
     * MX lookup is best-effort: if checkdnsrr() throws (e.g. DNS unavailable in
     * tests, no resolv.conf), we skip the MX check rather than reject. We
     * always require a syntactically valid email and a non-disposable domain.
     */
    public function validate(string $email): ?string
    {
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'invalid_format';
        }

        $domain = strtolower((string) substr(strrchr($email, '@'), 1));
        if ($domain === '') {
            return 'invalid_format';
        }

        if (isset($this->blocklist()[$domain])) {
            return 'disposable';
        }

        if (config('mailer.validate_recipient_dns', true)) {
            // Best-effort MX/A lookup. Suppress errors and treat exceptions as "skip".
            try {
                if (@checkdnsrr($domain, 'MX') === false && @checkdnsrr($domain, 'A') === false) {
                    return 'invalid_domain';
                }
            } catch (\Throwable $e) {
                // Resolver unavailable; skip.
            }
        }

        return null;
    }

    /** @return array<string, true> */
    private function blocklist(): array
    {
        if (self::$blocklist !== null) {
            return self::$blocklist;
        }
        $domains = [];
        // Reuse the spam module's vendored list — same file, no module coupling
        // (deliberate: if the spam module is removed, this still works as long
        // as the file is present).
        $path = resource_path('spam/disposable_email_domains.txt');
        if (is_file($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $d = strtolower(trim($line));
                if ($d !== '' && $d[0] !== '#') {
                    $domains[$d] = true;
                }
            }
        }
        return self::$blocklist = $domains;
    }

    public static function clearCache(): void
    {
        self::$blocklist = null;
    }
}

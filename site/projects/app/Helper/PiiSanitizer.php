<?php

namespace App\Helper;

/**
 * PiiSanitizer — strips PII from strings before they appear in logs or emails.
 *
 * Uses the same redaction patterns as safeLogBody() in the PMS adapters, plus
 * HTML-escaping for safe insertion into email HTML.
 *
 * Usage:
 *   PiiSanitizer::clean($errorMessage)  → redacted, HTML-escaped, max 300 chars
 *   PiiSanitizer::redact($text)         → redacted only, no HTML escaping or truncation
 */
class PiiSanitizer
{
    /** Patterns to redact and their replacements. */
    private const PATTERNS = [
        // Email addresses
        '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/'
            => '[EMAIL]',
        // E.164 international phones (+1 555 123 4567)
        '/\+\d{1,3}[\s.\-]?(?:\(?\d{1,4}\)?[\s.\-]?){2,5}\d{2,4}/'
            => '[PHONE]',
        // US local format (555) 123-4567 / 555-123-4567
        '/\(?\d{3}\)?[\s.\-]\d{3}[\s.\-]\d{4}/'
            => '[PHONE]',
        // Bearer / JWT tokens
        '/Bearer\s+[A-Za-z0-9\-._~+\/=]+/'
            => 'Bearer [TOKEN]',
        // JSON auth fields: "token":"...", "api_key":"..." etc.
        '/(["\'](token|authorization|auth|api_?key|secret|password|apikey)["\'][\s]*:[\s]*["\'])[^"\']{1,200}(["\'])/i'
            => '$1[REDACTED]$3',
    ];

    /**
     * Redact PII, HTML-escape, and truncate to $maxLen characters.
     * Safe to embed directly in email HTML.
     */
    public static function clean(?string $text, int $maxLen = 300): string
    {
        if ($text === null) return '';
        $redacted  = self::redact($text);
        $truncated = mb_strlen($redacted) > $maxLen
            ? mb_substr($redacted, 0, $maxLen) . '…'
            : $redacted;
        return htmlspecialchars($truncated, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Redact PII patterns only — no HTML escaping or truncation.
     * Use when the result will be further processed before output.
     */
    public static function redact(string $text): string
    {
        return preg_replace(
            array_keys(self::PATTERNS),
            array_values(self::PATTERNS),
            $text
        ) ?? $text;
    }
}

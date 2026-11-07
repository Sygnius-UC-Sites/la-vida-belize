<?php

namespace App\Helper;

/**
 * Formats raw policy strings from PMS adapters into clean HTML for display.
 *
 * Handles:
 *  - Empty / bare JSON arrays "[]" / "{}" → returns ''  (hides the section)
 *  - JSON objects {"minNights":3,"maxNights":45} → "Minimum stay: 3 nights…"
 *  - Policy code words (flexible, moderate, strict…) → expanded descriptions
 *  - Markdown (**bold**, - list, ## header) → HTML
 *  - Plain text with \n paragraph breaks → <p> tags
 */
class PolicyFormatter
{
    /** Cancellation policy codes used by Hostaway, Guesty, etc. */
    protected static array $codes = [
        'flexible' => [
            'label' => 'Flexible',
            'text'  => 'Full refund for cancellations made at least 24 hours before check-in.',
        ],
        'moderate' => [
            'label' => 'Moderate',
            'text'  => 'Full refund for cancellations made at least 5 days before check-in.',
        ],
        'strict' => [
            'label' => 'Strict',
            'text'  => '50% refund for cancellations made at least 7 days before check-in. No refund within 7 days of check-in.',
        ],
        'super_strict_30' => [
            'label' => 'Super Strict (30 days)',
            'text'  => '50% refund for cancellations made at least 30 days before check-in. No refund within 30 days.',
        ],
        'super_strict_60' => [
            'label' => 'Super Strict (60 days)',
            'text'  => '50% refund for cancellations made at least 60 days before check-in. No refund within 60 days.',
        ],
        'strict_14_with_grace_period' => [
            'label' => 'Strict with Grace Period',
            'text'  => 'Full refund within 48 hours of booking if at least 14 days before check-in. 50% refund if cancelled at least 7 days prior.',
        ],
        'non_refundable' => [
            'label' => 'Non-Refundable',
            'text'  => 'This reservation is non-refundable.',
        ],
        'long_term' => [
            'label' => 'Long-Term',
            'text'  => 'Full refund if cancelled within the first 30 days of a long-term stay. Refer to your booking confirmation for full terms.',
        ],
    ];

    /**
     * Format a raw policy value into display-ready HTML.
     * Returns empty string if there is nothing meaningful to show.
     */
    public static function format(mixed $raw): string
    {
        if ($raw === null) return '';

        $text = is_string($raw) ? trim($raw) : '';

        // Truly empty, or bare JSON container with no content
        if ($text === '' || $text === '[]' || $text === '{}' || $text === 'null' || $text === 'false') {
            return '';
        }

        // JSON value?
        if ($text[0] === '{' || $text[0] === '[') {
            $decoded = json_decode($text, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return self::fromJson($decoded);
            }
        }

        // Known policy code word?
        $lower = strtolower($text);
        if (isset(self::$codes[$lower])) {
            $p = self::$codes[$lower];
            return '<strong>' . e($p['label']) . '</strong><p class="mb-0 mt-1">' . e($p['text']) . '</p>';
        }

        // Already HTML? (admin/PMS rich-text fields like rental_agreement_house_rules_HTML come
        // as real markup — <ul>/<li>/<p>/<strong>/<br>. The old code treated it as plain text and
        // ran e() on it, so the page showed literal "&lt;ul&gt;…" / HTML entities. Return as-is.)
        if (preg_match('/<(p|ul|ol|li|br|strong|em|b|i|h[1-6]|div|span|a)\b[^>]*>/i', $text)) {
            return $text;
        }

        // Markdown?
        if (self::looksLikeMarkdown($text)) {
            return self::markdownToHtml($text);
        }

        // Plain text
        return self::plainToHtml($text);
    }

    // ─── JSON ────────────────────────────────────────────────────────────────

    protected static function fromJson(mixed $data): string
    {
        if (!is_array($data) || empty($data)) return '';

        // Booking-terms shape: {minNights, maxNights, …}
        if (isset($data['minNights']) || isset($data['maxNights'])) {
            $parts = [];
            if (!empty($data['minNights'])) {
                $n = (int) $data['minNights'];
                $parts[] = 'Minimum stay: ' . $n . ' ' . ($n === 1 ? 'night' : 'nights');
            }
            if (!empty($data['maxNights']) && (int) $data['maxNights'] < 365) {
                $n = (int) $data['maxNights'];
                $parts[] = 'Maximum stay: ' . $n . ' ' . ($n === 1 ? 'night' : 'nights');
            }
            if (empty($parts)) return '';
            return implode('<br>', array_map('e', $parts));
        }

        // Generic flat key→value
        $lines = [];
        foreach ($data as $k => $v) {
            if (is_scalar($v) && $v !== '' && $v !== null) {
                $label = ucwords(str_replace(['_', '-'], ' ', $k));
                $lines[] = e($label) . ': ' . e((string) $v);
            }
        }
        return $lines ? implode('<br>', $lines) : '';
    }

    // ─── Markdown ────────────────────────────────────────────────────────────

    protected static function looksLikeMarkdown(string $text): bool
    {
        return (bool) preg_match('/(\*\*[^*]+\*\*|^#{1,3} |^- |\n- )/m', $text);
    }

    protected static function markdownToHtml(string $text): string
    {
        // Normalise line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Bold **…**
        $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);
        // Italic *…* (single asterisk, not part of **)
        $text = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/s', '<em>$1</em>', $text);
        // Headers ## …
        $text = preg_replace('/^#{1,3} (.+)$/m', '<strong>$1</strong>', $text);

        $lines  = explode("\n", $text);
        $html   = '';
        $inList = false;

        foreach ($lines as $line) {
            $line = rtrim($line);

            // List item: - text or • text
            if (preg_match('/^[-•]\s+(.+)/', $line, $m)) {
                if (!$inList) { $html .= '<ul class="mb-2 ps-3">'; $inList = true; }
                $html .= '<li>' . trim($m[1]) . '</li>';
                continue;
            }

            // Close list if open
            if ($inList) { $html .= '</ul>'; $inList = false; }

            if ($line === '') continue; // skip blank lines between blocks

            $html .= '<p class="mb-1">' . $line . '</p>';
        }

        if ($inList) $html .= '</ul>';

        return $html;
    }

    // ─── Plain text ──────────────────────────────────────────────────────────

    protected static function plainToHtml(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $paras = preg_split('/\n{2,}/', $text);
        $html  = '';
        foreach ($paras as $para) {
            $para = trim($para);
            if ($para === '') continue;
            // Single newlines within a paragraph become <br>
            $html .= '<p class="mb-2">' . nl2br(e($para)) . '</p>';
        }
        return $html;
    }
}

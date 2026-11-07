<?php

namespace App\Services\Booking;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Writer\HTML;

/**
 * Converts an uploaded .docx (or .doc, .rtf, .odt) into clean HTML suitable
 * for pre-filling a CKEditor textarea.
 *
 * Phase 2 of the rental-agreement rebuild
 * (plan: docs/superpowers/plans/2026-05-08-rental-agreement-rebuild.md).
 *
 * The output is intentionally permissive — we want to preserve the owner's
 * formatting (paragraphs, bold/italic, lists, tables, links) but strip the
 * heavy stuff that bloats stored HTML or fights CKEditor:
 *   • Office-namespace tags (<o:p>, <w:…>) — removed.
 *   • Inline <style> blocks PHPWord emits at the top — removed.
 *   • <html><head><body> wrappers — unwrapped to body content only.
 *   • Empty <span style=""> wrappers — collapsed.
 *
 * Returns null on unsupported formats or parse failures so the caller can
 * surface a useful "Couldn't read that file" message instead of crashing.
 */
class WordDocConverter
{
    /** @var string[] Extensions we handle. PHPWord auto-detects but limit
     *               the surface area to formats we've smoke-tested. */
    private const ALLOWED_EXTS = ['docx', 'doc', 'rtf', 'odt'];

    /**
     * @param  string  $absolutePath  Filesystem path to the uploaded file.
     * @return string|null  Cleaned body-only HTML, or null on failure.
     */
    public function convertToHtml(string $absolutePath): ?string
    {
        $result = $this->convertToHtmlWithReport($absolutePath);
        return $result['html'] ?? null;
    }

    /**
     * Same as convertToHtml() but returns the cleaned HTML alongside a
     * diagnostic of which merge tokens were auto-detected and substituted
     * during the smart-tokenize pass. Callers that want to surface
     * "Auto-replaced 7 placeholders with merge tokens" use this variant.
     *
     * @return array{html: string, tokens_replaced: int, tokens: array<string,int>}|null
     */
    public function convertToHtmlWithReport(string $absolutePath): ?array
    {
        if (!is_file($absolutePath)) return null;
        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTS, true)) return null;

        try {
            $phpWord = IOFactory::load($absolutePath);
            $writer  = IOFactory::createWriter($phpWord, 'HTML');
            ob_start();
            $writer->save('php://output');
            $rawHtml = (string) ob_get_clean();
        } catch (\Throwable $e) {
            \Log::warning('WordDocConverter failed', ['path' => $absolutePath, 'error' => $e->getMessage()]);
            return null;
        }

        $cleaned = $this->clean($rawHtml);
        $tokenized = $this->smartTokenize($cleaned);
        return [
            'html'            => $tokenized['html'],
            'tokens_replaced' => array_sum($tokenized['report']),
            'tokens'          => $tokenized['report'],
        ];
    }

    /**
     * Vocabulary used by smartTokenize(). Maps human label aliases (lower-cased,
     * spaces normalised) to the corresponding merge-token name. Order matters
     * for overlapping prefixes — longer / more-specific phrases first so we
     * don't match "guest" before "guest email".
     *
     * Add new aliases here when you spot patterns owners use in real docs.
     */
    private const TOKEN_VOCAB = [
        // Guest identity
        ['aliases' => ['guest name', 'renter name', 'tenant name', 'client name',
                       'guest full name', "guest's name", 'name of guest', 'name of renter'],
         'token'   => 'guest_name'],
        ['aliases' => ['guest email', 'guest e-mail', 'renter email', "guest's email",
                       'email address', 'e-mail address', 'email', 'e-mail'],
         'token'   => 'guest_email'],
        ['aliases' => ['guest phone', 'guest phone number', 'phone number', "guest's phone",
                       'tel', 'telephone', 'mobile', 'mobile number'],
         'token'   => 'guest_phone'],

        // Property
        ['aliases' => ['property name', 'rental property name', 'rental name', 'name of property',
                       'name of rental', 'unit name', 'villa name', 'house name'],
         'token'   => 'property_name'],
        ['aliases' => ['property address', 'rental address', 'unit address', 'street address',
                       'address of property', 'property location', 'rental property address'],
         'token'   => 'property_address'],

        // Trip dates / counts
        ['aliases' => ['check-in date', 'checkin date', 'check in date', 'arrival date',
                       'date of arrival', 'check-in', 'check in'],
         'token'   => 'check_in'],
        ['aliases' => ['check-out date', 'checkout date', 'check out date', 'departure date',
                       'date of departure', 'check-out', 'check out'],
         'token'   => 'check_out'],
        ['aliases' => ['number of nights', 'total nights', 'nights of stay', 'length of stay',
                       'nights'],
         'token'   => 'nights'],
        ['aliases' => ['number of adults', 'adults'],          'token' => 'adults'],
        ['aliases' => ['number of children', 'children', 'kids'], 'token' => 'children'],
        ['aliases' => ['number of pets', 'pets'],              'token' => 'pets'],

        // Money / booking
        ['aliases' => ['total amount', 'total cost', 'rental fee', 'total rental fee',
                       'grand total', 'amount due', 'rental total', 'total'],
         'token'   => 'total_amount'],
        ['aliases' => ['booking id', 'reservation number', 'reservation id',
                       'reference number', 'reference'],
         'token'   => 'booking_id'],
        ['aliases' => ['signed date', 'date signed', 'signing date', 'date of signing'],
         'token'   => 'signed_date'],

        // Brand / host
        ['aliases' => ['host name', 'owner name'],   'token' => 'host_name'],
        ['aliases' => ['host phone', 'owner phone'], 'token' => 'host_phone'],
        ['aliases' => ['emergency phone', 'emergency contact', 'emergency number'],
         'token'   => 'emergency_phone'],
        ['aliases' => ['brand', 'company name', 'business name'], 'token' => 'brand'],
    ];

    /**
     * Auto-detect placeholder patterns owners commonly write in template
     * docs and replace them with merge tokens.
     *
     * Patterns recognised (case-insensitive, label match required):
     *   • [Guest Name]   / [GUEST NAME]    — square-bracket placeholder
     *   • {Guest Name}                       — curly-bracket placeholder
     *   • <Guest Name>                       — angle-bracket placeholder
     *   • "Guest Name: ____________"         — label followed by 3+ underscores
     *   • "Guest Name: [TBD]" / "[ ]" /      — label followed by a placeholder cue
     *     "Guest Name: [insert]"
     *
     * We DO NOT replace bare prose like "the guest" — too easy to false-positive.
     *
     * @return array{html: string, report: array<string,int>}
     */
    private function smartTokenize(string $html): array
    {
        $report = [];
        foreach (self::TOKEN_VOCAB as $entry) {
            $token = $entry['token'];
            // Build a regex alternation for this entry's aliases. Word boundaries
            // ensure "name" alone never matches inside "username", etc.
            $aliasPattern = implode('|', array_map(
                fn ($a) => preg_quote($a, '/'),
                $entry['aliases']
            ));
            // Allow optional leading article ("the Guest Name").
            $labelRe = "(?:the\\s+)?(?:$aliasPattern)";

            // Pattern A — bracketed placeholders. e.g. [Guest Name], {GUEST NAME}, <guest name>.
            $patterns = [
                "/\\[\\s*$labelRe\\s*\\]/iu",
                "/\\{\\s*$labelRe\\s*\\}/iu",
                "/<\\s*$labelRe\\s*>/iu",
            ];
            foreach ($patterns as $re) {
                $count = 0;
                $html = preg_replace($re, '{{' . $token . '}}', $html, -1, $count);
                if ($count > 0) $report[$token] = ($report[$token] ?? 0) + $count;
            }

            // Pattern B — "Label: <fill-in indicator>" where the fill-in is one of:
            //   • 3+ underscores (with optional spaces between them)
            //   • [TBD] / [insert] / [your name here] / [ ] / [...]
            //   • a long run of dots (3+) that Word turned into a fill-in line
            // The label keeps the colon; the placeholder becomes the token.
            // We also allow an optional currency symbol or short prefix between
            // the colon and the fill-in so e.g. "Total Amount: $___" matches.
            $fillInRe  = '(?:(?:_\\s*){3,}|\\[(?:[^\\]]{0,30})\\]|(?:\\.\\s*){3,})';
            $prefixRe  = '[\\$£€¥¢#]?\\s*';   // optional currency / # before the placeholder
            $labeledRe = "/($labelRe\\s*:\\s*$prefixRe)$fillInRe/iu";
            $count = 0;
            $html = preg_replace($labeledRe, '$1{{' . $token . '}}', $html, -1, $count);
            if ($count > 0) $report[$token] = ($report[$token] ?? 0) + $count;
        }
        return ['html' => $html, 'report' => $report];
    }

    /**
     * Strip the wrapper / styling artefacts PHPWord (and Word itself) emit
     * so what lands in CKEditor is just the body content.
     */
    private function clean(string $html): string
    {
        // 1) Drop the doctype + wrappers — keep body inner only.
        if (preg_match('/<body[^>]*>(.*)<\/body>/is', $html, $m)) {
            $html = $m[1];
        }
        // 2) Strip <style> blocks (PHPWord emits a giant block of generated CSS).
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
        // 3) Strip Office-namespace tags + their content where they're empty.
        $html = preg_replace('/<o:p[^>]*>.*?<\/o:p>/is', '', $html);
        $html = preg_replace('/<\/?o:p[^>]*>/i', '', $html);
        $html = preg_replace('/<\/?w:[^>]+>/i', '', $html);
        // 4) Collapse no-op span wrappers (`<span style="">…</span>`).
        $html = preg_replace('/<span\s+style=""\s*>(.*?)<\/span>/is', '$1', $html);
        // 5) PHPWord wraps the document in <div class="...">; flatten to plain content.
        $html = preg_replace('/<div\s+class="PhpOffice[^"]*"\s*>(.*?)<\/div>/is', '$1', $html);
        // 6) Drop any class attribute PHPWord emits — they reference its
        //    stripped <style> block and otherwise produce CKEditor warnings.
        $html = preg_replace('/\s+class="[^"]*"/i', '', $html);
        // 7) Trim, collapse runs of blank lines.
        $html = preg_replace("/(\n\s*){3,}/", "\n\n", trim($html));
        return $html;
    }
}

<?php

namespace App\Services\Scraping;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Idempotent persistence of scraped reviews into property_reviews.
 *
 * Dedup strategy:
 *   - When the upstream gives us a stable external review ID, key on
 *     (source, external_review_id) — exact match wins.
 *   - Otherwise compute SHA-1(reviewer_name + comment) as content_hash and
 *     skip duplicates within the same property + source.
 *
 * Reviewer photos are downloaded best-effort to public/uploads/scraped/<property_id>/reviewers/
 * so we don't depend on Airbnb's CDN URLs (they rotate).
 */
class ReviewImporter
{
    public function importMany(int $propertyId, string $source, array $reviews): int
    {
        if (empty($reviews)) return 0;
        @set_time_limit(180);

        $count = 0;
        foreach ($reviews as $r) {
            try {
                if ($this->importOne($propertyId, $source, (array) $r)) {
                    $count++;
                }
            } catch (\Throwable $e) {
                $this->log('warning', 'review_insert_failed', [
                    'property_id' => $propertyId,
                    'err' => $e->getMessage(),
                    'reviewer' => $r['reviewer_name'] ?? null,
                ]);
            }
        }
        return $count;
    }

    private function importOne(int $propertyId, string $source, array $r): bool
    {
        $extId = isset($r['external_id']) ? (string) $r['external_id'] : null;
        $hash  = $this->contentHash((string) ($r['reviewer_name'] ?? ''), (string) ($r['comment'] ?? ''));

        // Idempotency check
        if ($extId !== null && $extId !== '') {
            $exists = DB::table('property_reviews')
                ->where('source', $source)
                ->where('external_review_id', $extId)
                ->exists();
            if ($exists) return false;
        } else {
            $exists = DB::table('property_reviews')
                ->where('property_id', $propertyId)
                ->where('source', $source)
                ->where('content_hash', $hash)
                ->exists();
            if ($exists) return false;
        }

        $localPhoto = null;
        $photoUrl = $r['reviewer_photo'] ?? null;
        if (is_string($photoUrl) && str_starts_with($photoUrl, 'http')) {
            $localPhoto = $this->downloadReviewerPhoto($propertyId, $photoUrl);
        }

        DB::table('property_reviews')->insert([
            'property_id'          => $propertyId,
            'source'               => $source,
            'external_review_id'   => $extId,
            'content_hash'         => $hash,
            'reviewer_name'        => $r['reviewer_name'] ?? null,
            'reviewer_photo_url'   => $photoUrl ?: null,
            'reviewer_photo_local' => $localPhoto,
            'reviewer_location'    => $r['reviewer_location'] ?? null,
            'rating'               => isset($r['rating']) ? (int) $r['rating'] : null,
            'comment'              => $r['comment'] ?? null,
            'comment_translated'   => $r['comment_translated'] ?? null,
            'language'             => $r['language'] ?? null,
            'response_text'        => $r['response_text'] ?? null,
            'response_date'        => $this->parseDate($r['response_date'] ?? null),
            'review_date'          => $this->parseDate($r['review_date'] ?? null),
            'status'               => 'imported',
            'raw_payload'          => json_encode($r, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);
        return true;
    }

    private function contentHash(string $name, string $comment): string
    {
        return sha1(strtolower(trim($name)) . '|' . trim($comment));
    }

    /**
     * Parse a date string from any common Airbnb format. Returns Carbon|null.
     * Handles: ISO 8601, "October 2024", "5 days ago", and similar.
     */
    private function parseDate($value): ?\Carbon\Carbon
    {
        if ($value === null || $value === '') return null;
        $value = (string) $value;

        // ISO / common parseable formats first
        try {
            return \Carbon\Carbon::parse($value);
        } catch (\Throwable $e) {
            // fall through
        }

        // "5 days ago" / "2 months ago"
        if (preg_match('/^(\d+)\s+(day|week|month|year)s?\s+ago/i', $value, $m)) {
            $unit = strtolower($m[2]);
            return now()->{"sub" . ucfirst($unit) . "s"}((int) $m[1]);
        }

        return null;
    }

    private function downloadReviewerPhoto(int $propertyId, string $url): ?string
    {
        try {
            $resp = Http::timeout(10)->get($url);
            if (!$resp->successful()) return null;
            $body = (string) $resp->body();
            if ($body === '') return null;

            $base = (string) config('importer.image_storage_path', 'uploads/scraped');
            $dir  = public_path($base . '/' . $propertyId . '/reviewers');
            if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                return null;
            }

            $ct = strtolower((string) $resp->header('Content-Type'));
            $ext = match (true) {
                str_contains($ct, 'png')  => 'png',
                str_contains($ct, 'webp') => 'webp',
                default                    => 'jpg',
            };
            $filename = Str::random(10) . '.' . $ext;
            $abs = $dir . DIRECTORY_SEPARATOR . $filename;
            if (file_put_contents($abs, $body) === false) return null;

            return $base . '/' . $propertyId . '/reviewers/' . $filename;
        } catch (\Throwable $e) {
            $this->log('warning', 'reviewer_photo_download_failed', ['url' => $url, 'err' => $e->getMessage()]);
            return null;
        }
    }

    private function log(string $level, string $event, array $context = []): void
    {
        try {
            Log::channel((string) config('importer.log_channel', 'importer'))->{$level}($event, $context);
        } catch (\Throwable $e) {
            Log::{$level}("$event (fallback)", $context);
        }
    }
}

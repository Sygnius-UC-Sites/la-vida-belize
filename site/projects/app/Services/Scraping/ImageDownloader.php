<?php

namespace App\Services\Scraping;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Downloads scraped image URLs to public/uploads/scraped/<property_id>/ and
 * returns the relative paths. The hoisted `uploads/` folder lives at the
 * htdocs root (see CLAUDE.md), so `public_path()` resolves to it.
 *
 * Failures are logged but do not throw — one bad image shouldn't block the
 * whole import.
 */
class ImageDownloader
{
    /** @return list<string> Relative paths under uploads/, e.g. uploads/scraped/12/abc.jpg */
    public function downloadMany(int $propertyId, array $urls, int $max = 30): array
    {
        $base = (string) config('importer.image_storage_path', 'uploads/scraped');
        $dir  = public_path($base . '/' . $propertyId);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            $this->log('warning', 'image_dir_create_failed', ['dir' => $dir]);
            return [];
        }

        $saved = [];
        foreach (array_slice($urls, 0, $max) as $i => $url) {
            $path = $this->downloadOne($url, $dir, $base, $propertyId, $i);
            if ($path !== null) {
                $saved[] = $path;
            }
        }
        return $saved;
    }

    private function downloadOne(string $url, string $dir, string $base, int $propertyId, int $index): ?string
    {
        try {
            $resp = Http::timeout(20)->get($url);
            if (!$resp->successful()) {
                $this->log('warning', 'image_download_http_error', ['url' => $url, 'status' => $resp->status()]);
                return null;
            }
            $body = (string) $resp->body();
            if ($body === '') return null;

            $ext = $this->guessExtension($url, $resp->header('Content-Type'));
            $filename = sprintf('%03d_%s.%s', $index, Str::random(8), $ext);
            $abs = $dir . DIRECTORY_SEPARATOR . $filename;

            if (file_put_contents($abs, $body) === false) {
                $this->log('warning', 'image_write_failed', ['abs' => $abs]);
                return null;
            }

            return $base . '/' . $propertyId . '/' . $filename;
        } catch (\Throwable $e) {
            $this->log('warning', 'image_download_exception', ['url' => $url, 'err' => $e->getMessage()]);
            return null;
        }
    }

    private function guessExtension(string $url, ?string $contentType): string
    {
        // Prefer Content-Type to handle CDN URLs that lack extensions.
        $ct = strtolower((string) $contentType);
        if (str_contains($ct, 'png'))  return 'png';
        if (str_contains($ct, 'webp')) return 'webp';
        if (str_contains($ct, 'gif'))  return 'gif';
        if (str_contains($ct, 'jpeg') || str_contains($ct, 'jpg')) return 'jpg';

        $path = (string) parse_url($url, PHP_URL_PATH);
        $ext  = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) ? ($ext === 'jpeg' ? 'jpg' : $ext) : 'jpg';
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

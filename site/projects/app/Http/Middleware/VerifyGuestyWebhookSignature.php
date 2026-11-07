<?php

namespace App\Http\Middleware;

use App\Support\WebhookConfiguration;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * VerifyGuestyWebhookSignature
 *
 * Supports Guesty's current Svix-compatible signature headers and the older
 * X-Guesty-Signature HMAC format for backward compatibility.
 */
class VerifyGuestyWebhookSignature
{
    public function handle(Request $request, Closure $next)
    {
        $token = WebhookConfiguration::token('guesty');
        $receivedToken = (string) ($request->route('webhookToken') ?: $request->query('token', ''));
        if ($token !== '' && $receivedToken !== '' && hash_equals($token, $receivedToken)) {
            return $next($request);
        }

        $secret = $this->resolveSecret();

        // Fail closed unless either the callback token or a valid Guesty signature is available.
        if (empty($secret)) {
            if ($token !== '') {
                Log::channel('pms_webhooks')->warning('Guesty webhook rejected — invalid or missing callback token.', [
                    'ip' => $request->ip(),
                ]);
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            Log::channel('pms_webhooks')->error('Guesty webhook authentication failed: no usable callback token or signing secret.', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Webhook endpoint not configured'], 503);
        }

        if (!$this->validSvixSignature($request, $secret) && !$this->validLegacySignature($request, $secret)) {
            Log::channel('pms_webhooks')->warning('Guesty webhook rejected — signature mismatch.', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }

    private function resolveSecret(): ?string
    {
        $configured = config('services.guesty.webhook_secret');
        if (!empty($configured)) {
            return (string) $configured;
        }

        try {
            $encrypted = \App\Models\BasicSetting::where('name', 'guesty_webhook_secret_encrypted')->value('value');
            return $encrypted ? Crypt::decryptString($encrypted) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function validLegacySignature(Request $request, string $secret): bool
    {
        $signature = (string) $request->header('X-Guesty-Signature', '');
        if ($signature === '') {
            return false;
        }

        $received = str_starts_with($signature, 'sha256=') ? substr($signature, 7) : $signature;
        return hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $received);
    }

    private function validSvixSignature(Request $request, string $secret): bool
    {
        $messageId = (string) ($request->header('svix-id') ?: $request->header('webhook-id'));
        $timestamp = (string) ($request->header('svix-timestamp') ?: $request->header('webhook-timestamp'));
        $signatureHeader = (string) ($request->header('svix-signature') ?: $request->header('webhook-signature'));

        if ($messageId === '' || $timestamp === '' || $signatureHeader === '' || !ctype_digit($timestamp)) {
            return false;
        }
        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $key = $secret;
        if (str_starts_with($secret, 'whsec_')) {
            $decoded = base64_decode(substr($secret, 6), true);
            if ($decoded === false) {
                return false;
            }
            $key = $decoded;
        }

        $signedContent = "{$messageId}.{$timestamp}." . $request->getContent();
        $expected = base64_encode(hash_hmac('sha256', $signedContent, $key, true));

        foreach (preg_split('/\s+/', trim($signatureHeader)) ?: [] as $candidate) {
            $value = str_contains($candidate, ',') ? explode(',', $candidate, 2)[1] : $candidate;
            if ($value !== '' && hash_equals($expected, $value)) {
                return true;
            }
        }

        return false;
    }
}

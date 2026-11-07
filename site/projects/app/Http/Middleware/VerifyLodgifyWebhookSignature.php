<?php

namespace App\Http\Middleware;

use Closure;
use App\Support\WebhookConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * VerifyLodgifyWebhookSignature
 *
 * Lodgify does NOT send a request signature/HMAC header.
 * Security is enforced by a secret token embedded in the webhook URL.
 *
 * In your Lodgify dashboard, configure each webhook URL as:
 *   https://your-site.com/lodgify/booking_new_status_booked?token=<LODGIFY_WEBHOOK_TOKEN>
 *
 * This middleware validates the incoming `token` query parameter
 * against the LODGIFY_WEBHOOK_TOKEN environment variable.
 */
class VerifyLodgifyWebhookSignature
{
    public function handle(Request $request, Closure $next)
    {
        $expectedToken = WebhookConfiguration::token('lodgify');

        // If no token is configured, block the request for safety
        if (empty($expectedToken)) {
            Log::channel('pms_webhooks')->critical(
                'LODGIFY_WEBHOOK_TOKEN is not configured — all webhooks are blocked.'
            );
            return response()->json(['error' => 'Webhook token not configured on server.'], 500);
        }

        $receivedToken = $request->query('token');

        // Use hash_equals to prevent timing attacks even on simple string tokens
        if (empty($receivedToken) || !hash_equals($expectedToken, $receivedToken)) {
            Log::channel('pms_webhooks')->warning('Lodgify webhook rejected — invalid or missing token.', [
                'ip'  => $request->ip(),
                'url' => $request->fullUrl(),
            ]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        Log::channel('pms_webhooks')->info('Lodgify webhook accepted.', [
            'event' => $request->path(),
        ]);

        return $next($request);
    }
}

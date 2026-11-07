<?php

namespace App\Http\Middleware;

use App\Support\WebhookConfiguration;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * VerifyHostawayWebhookToken
 *
 * Hostaway does not provide HMAC webhook signatures in its current API.
 * Security is enforced by embedding a secret token in the webhook callback URL:
 *   https://your-site.com/webhooks/hostaway?token=<HOSTAWAY_WEBHOOK_TOKEN>
 *
 * When registering webhooks via the Hostaway dashboard or webhooks:register command,
 * ensure the token is appended to the URL. This middleware validates it on every request.
 *
 * Set in .env:  HOSTAWAY_WEBHOOK_TOKEN=<any strong random string, e.g. openssl rand -hex 32>
 */
class VerifyHostawayWebhookToken
{
    public function handle(Request $request, Closure $next)
    {
        $expectedToken = WebhookConfiguration::token('hostaway');

        // Fail closed: if no token is configured, reject all incoming webhooks.
        // Set HOSTAWAY_WEBHOOK_TOKEN in .env (generate with: openssl rand -hex 32).
        if (empty($expectedToken)) {
            Log::channel('pms_webhooks')->error('HOSTAWAY_WEBHOOK_TOKEN not set — Hostaway webhook rejected (fail-closed). Configure the token to enable webhooks.', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Webhook endpoint not configured'], 503);
        }

        $receivedToken = $request->query('token');

        if (empty($receivedToken) || !hash_equals($expectedToken, $receivedToken)) {
            Log::channel('pms_webhooks')->warning('Hostaway webhook rejected — invalid or missing token.', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * VerifyCronToken
 *
 * Protects publicly accessible cron routes with a secret token.
 *
 * Configure your server cron job as:
 *   curl "https://your-site.com/set-cron-job?cron_token=<CRON_SECRET_TOKEN>"
 *
 * The CRON_SECRET_TOKEN value is stored in .env and must never be committed
 * to version control or exposed publicly.
 */
class VerifyCronToken
{
    public function handle(Request $request, Closure $next)
    {
        $expectedToken = env('CRON_SECRET_TOKEN');

        if (empty($expectedToken)) {
            Log::error('CRON_SECRET_TOKEN is not configured — cron routes are blocked for safety.');
            return response('Cron token not configured.', 500);
        }

        $receivedToken = $request->query('cron_token');

        if (empty($receivedToken) || !hash_equals($expectedToken, $receivedToken)) {
            Log::warning('Cron route access denied — invalid or missing cron_token.', [
                'ip'  => $request->ip(),
                'url' => $request->fullUrl(),
            ]);
            return response('Unauthorized.', 401);
        }

        return $next($request);
    }
}

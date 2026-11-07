<?php

namespace App\Modules\MultiPms\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WarmGuestyToken extends Command
{
    protected $signature   = 'guesty:warm-token';
    protected $description = 'Proactively refresh the Guesty OAuth token before it expires';

    public function handle(): int
    {
        $helper   = new \App\Helper\ModelHelper();
        $apiKey   = $helper->getDataFromSetting('guesty_api_key');
        $clientId = $helper->getDataFromSetting('guesty_client_id');

        if (empty($apiKey) || empty($clientId)) {
            $this->warn('Guesty credentials not configured — skipping.');
            return 0;
        }

        $cacheKey = 'guesty_access_token_' . md5($apiKey);

        if (Cache::get($cacheKey)) {
            $this->info('Guesty token already cached — no refresh needed.');
            return 0;
        }

        $this->info('Guesty token missing — fetching fresh token...');

        $response  = Http::asForm()->post('https://open-api.guesty.com/oauth2/token', [
            'grant_type'    => 'client_credentials',
            'client_id'     => $clientId,
            'client_secret' => $apiKey,
            'scope'         => 'open-api',
        ]);

        $data      = $response->json();
        $token     = $data['access_token'] ?? null;
        $expiresIn = (int) ($data['expires_in'] ?? 86400);

        if ($token) {
            $ttl = max($expiresIn - 300, 300);
            Cache::put($cacheKey, $token, $ttl);
            Log::channel('lodgify')->info("guesty:warm-token — cached for {$ttl}s (expires_in={$expiresIn}s)");
            $this->info("Token cached for {$ttl}s (expires_in={$expiresIn}s).");
            return 0;
        }

        Log::channel('lodgify')->error('guesty:warm-token — fetch failed: ' . json_encode($data));
        $this->error('Token fetch failed: ' . json_encode($data));
        return 1;
    }
}

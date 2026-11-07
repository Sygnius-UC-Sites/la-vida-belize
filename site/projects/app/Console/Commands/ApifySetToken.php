<?php

namespace App\Console\Commands;

use App\Services\Scraping\ApifySettings;
use Illuminate\Console\Command;

/**
 * One-shot CLI to set/check the Apify API token. Useful as a backup when the
 * admin form path is fighting you, or for headless installs where there's no
 * browser handy.
 *
 *   php artisan importer:set-token             — prompts for token, encrypts, stores
 *   php artisan importer:set-token --check     — prints whether a token is currently resolvable
 *   php artisan importer:set-token --token=xx  — non-interactive (avoid; the token shows in shell history)
 */
class ApifySetToken extends Command
{
    protected $signature = 'importer:set-token {--token= : Pass token directly (skips prompt)} {--check : Just check current state, don\'t modify}';
    protected $description = 'Set or check the Apify API token (encrypted at rest in basic_settings).';

    public function handle(ApifySettings $settings): int
    {
        if ($this->option('check')) {
            $current = $settings->apiToken();
            if ($current === '') {
                $this->warn('No Apify token resolves. Either basic_settings has no row, or the stored value can\'t be decrypted (APP_KEY mismatch).');
                $corrupt = $settings->tokenIsCorrupt();
                $this->line('  tokenIsCorrupt: ' . ($corrupt ? 'YES — re-set it' : 'no'));
                return self::FAILURE;
            }
            $this->info('Token resolves OK. Length: ' . strlen($current) . ', starts with: ' . substr($current, 0, 12) . '...');
            return self::SUCCESS;
        }

        $token = (string) $this->option('token');
        if ($token === '') {
            $token = (string) $this->secret('Paste your Apify API token (input hidden)');
        }
        $token = trim($token);
        if ($token === '') {
            $this->error('Empty token, refusing to save.');
            return self::FAILURE;
        }

        $settings->setEncryptedToken($token);
        $settings->refresh();

        // Verify round-trip.
        $back = $settings->apiToken();
        if ($back === '') {
            $this->error('Token was saved but does not decrypt back. Check APP_KEY in your .env.');
            return self::FAILURE;
        }
        if ($back !== $token) {
            $this->error('Token round-trip mismatch (saved length=' . strlen($token) . ', read length=' . strlen($back) . '). Crypt or column issue.');
            return self::FAILURE;
        }

        $this->info('✓ Token saved and verified. Length: ' . strlen($back));
        return self::SUCCESS;
    }
}

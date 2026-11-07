<?php

namespace App\Console\Commands;

use App\Models\BasicSetting;
use App\Modules\VacationRental\Pms\PmsManager;
use App\Services\Operations\OperationsRecorder;
use App\Support\WebhookConfiguration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * RegisterPmsWebhooks — registers webhook URLs with all (or one) PMS.
 *
 * The webhook base URL comes from the saved admin setting or .env:
 *   WEBHOOK_BASE_URL=https://your-domain.com   (no trailing slash)
 *   WEBHOOK_TOKEN=your-secret-token            (same as LODGIFY_WEBHOOK_TOKEN)
 *
 * LOCAL DEVELOPMENT with ngrok:
 *   1. Run:  ngrok http 8000
 *   2. Copy the https URL (e.g. https://abc123.ngrok.io)
 *   3. Set in .env:  WEBHOOK_BASE_URL=https://abc123.ngrok.io
 *   4. Run:  php projects/artisan webhooks:register
 *   5. Replace the temporary URL and re-register before ending the tunnel.
 *
 * STAGING / PRODUCTION:
 *   Update WEBHOOK_BASE_URL in .env and re-run the command.
 *   No code changes needed.
 *
 * Usage:
 *   php projects/artisan webhooks:register              # all PMS
 *   php projects/artisan webhooks:register --pms=guesty # one PMS only
 *   php projects/artisan webhooks:register --dry-run    # preview URLs, no API calls
 */
class RegisterPmsWebhooks extends Command
{
    protected $signature = 'webhooks:register
                            {--pms= : Register only this PMS (hostfully|hostaway|guesty|lodgify)}
                            {--dry-run : Print the URLs that would be registered without calling any API}';

    protected $description = 'Register webhook URLs with PMS systems using the saved admin setting or WEBHOOK_BASE_URL';

    // Events to subscribe to per PMS
    private const EVENTS = [
        'hostfully' => [
            'NEW_BOOKING',
            'BOOKING_UPDATED',
            'BOOKING_CANCELLED',
            'PROPERTY_AVAILABILITY_UPDATED',
            'NEW_PROPERTY',
            'UPDATED_PROPERTY',
            'DELETED_PROPERTY',
            'ACTIVATED_PROPERTY',
            'DEACTIVATED_PROPERTY',
        ],
        'hostaway' => [
            'unified', // Hostaway does not support event filters on unified webhooks.
        ],
        'guesty' => [
            'reservation.new',
            'reservation.updated',
            'listing.new',
            'listing.updated',
            'listing.removed',
            'listing.calendar.updated',
            'calendar.updated.v2',
        ],
        'lodgify' => [
            'booking_new_any_status',
            'booking_change',
            'availability_change',
            'rate_change',
        ],
    ];

    public function __construct(private PmsManager $pms)
    {
        parent::__construct();
    }

    public function handle(OperationsRecorder $recorder): int
    {
        $run = $recorder->start(
            'webhook_registration',
            'running',
            'webhooks:register',
            'console',
            null,
            $this->option('pms') ?: 'all',
            'PMS webhook registration started',
            [
                'pms' => $this->option('pms') ?: 'all',
                'dry_run' => (bool) $this->option('dry-run'),
            ]
        );

        $baseUrl = WebhookConfiguration::baseUrl();
        $tokens = [
            'lodgify' => WebhookConfiguration::token('lodgify'),
            'hostaway' => WebhookConfiguration::token('hostaway'),
            'hostfully' => WebhookConfiguration::token('hostfully'),
            'guesty' => WebhookConfiguration::token('guesty'),
        ];
        $dryRun          = $this->option('dry-run');
        $onlyPms         = $this->option('pms');

        if (empty($baseUrl)) {
            $this->error('Webhook Base URL is not configured.');
            $this->line('  Set it to your public URL, e.g.:');
            $this->line('  Admin > PMS Settings > Webhook Base URL');
            $this->line('  WEBHOOK_BASE_URL=https://abc123.ngrok.io   (local with ngrok)');
            $this->line('  WEBHOOK_BASE_URL=https://staging.yoursite.com');
            $recorder->finish($run, 'failure', 'Webhook Base URL is not configured.', $this->getOutputBuffer(), 'Missing webhook base URL');
            return 1;
        }

        if (empty($tokens['lodgify'])) {
            $this->warn('WEBHOOK_TOKEN / LODGIFY_WEBHOOK_TOKEN is not set — Lodgify endpoints will reject requests.');
        }
        if (empty($tokens['hostaway'])) {
            $this->warn('HOSTAWAY_WEBHOOK_TOKEN is not set — Hostaway endpoints will reject requests. Set it then re-register.');
        }
        if (empty($tokens['hostfully'])) {
            $this->warn('HOSTFULLY_WEBHOOK_TOKEN is not set — Hostfully endpoints will reject requests. Set it then re-register.');
        }
        if (empty($tokens['guesty']) && empty(config('services.guesty.webhook_secret'))) {
            $this->warn('GUESTY_WEBHOOK_TOKEN and GUESTY_WEBHOOK_SECRET are not set — registration must return a signing secret or incoming events will remain blocked.');
        }

        $pmsList = $onlyPms ? [$onlyPms] : array_keys(self::EVENTS);
        $failed = false;

        foreach ($pmsList as $pmsName) {
            if (!array_key_exists($pmsName, self::EVENTS)) {
                $this->error("Unknown PMS: {$pmsName}. Valid: " . implode(', ', array_keys(self::EVENTS)));
                $failed = true;
                continue;
            }

            if (!$dryRun && $pmsName !== 'guesty' && empty($tokens[$pmsName])) {
                $this->error("Skipping {$pmsName}: its webhook token is not configured.");
                $failed = true;
                continue;
            }

            $failed = !$this->registerForPms($pmsName, $baseUrl, $tokens[$pmsName], $dryRun) || $failed;
        }

        if (!$dryRun) {
            $this->newLine();
            $this->info('Done. Verify delivery in each provider dashboard and inspect storage/logs/pms_webhooks/webhooks.log.');
        }

        $status = $failed ? 'failure' : 'success';
        $recorder->finish(
            $run,
            $status,
            $failed ? 'Webhook registration completed with failures' : 'Webhook registration completed',
            $this->getOutputBuffer(),
            $failed ? 'One or more webhook registrations failed.' : null,
            ['base_url' => $this->safeUrl($baseUrl)]
        );

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function getOutputBuffer(): string
    {
        try {
            return \Illuminate\Support\Facades\Artisan::output();
        } catch (\Throwable) {
            return '';
        }
    }

    private function registerForPms(string $pmsName, string $baseUrl, string $token, bool $dryRun): bool
    {
        $this->newLine();
        $this->line("<fg=cyan>── {$pmsName} ──</>");

        // Build the webhook URL for this PMS.
        // Tokenized callback URLs provide fail-closed authentication. Guesty
        // additionally supports its Svix-compatible signature headers.
        $url = match ($pmsName) {
            'lodgify'    => "{$baseUrl}/lodgify" . ($token ? "?token=" . rawurlencode($token) : ''),
            'hostaway'   => "{$baseUrl}/webhooks/hostaway" . ($token ? "?token=" . rawurlencode($token) : ''),
            'hostfully'  => "{$baseUrl}/webhooks/hostfully" . ($token ? "?token=" . rawurlencode($token) : ''),
            'guesty'     => "{$baseUrl}/webhooks/guesty" . ($token ? "/" . rawurlencode($token) : ''),
            default      => "{$baseUrl}/webhooks/{$pmsName}",
        };

        $events = self::EVENTS[$pmsName];

        $this->line("  URL:    " . $this->safeUrl($url));
        $this->line("  Events: " . implode(', ', $events));
        if ($pmsName === 'lodgify') {
            $this->line('  Note:   Property and additional-fee metadata is reconciled every 30 minutes; Lodgify rejects property event subscriptions.');
        }

        if ($dryRun) {
            $this->line("  <fg=yellow>[DRY RUN] — no API call made</>");
            return true;
        }

        try {
            $adapter = $this->pms->driver($pmsName);
            $result  = $adapter->registerWebhook($url, $events);

            if ($result['ok']) {
                $id = $result['webhook_id'] ?? 'multiple';
                $this->info("  ✓ Registered" . ($id ? " (id: {$id})" : ''));

                if ($pmsName === 'guesty') {
                    if (!empty($result['signing_secret'])) {
                        BasicSetting::updateOrCreate(
                            ['name' => 'guesty_webhook_secret_encrypted'],
                            ['value' => Crypt::encryptString($result['signing_secret'])]
                        );
                        $this->info('  ✓ Guesty signing secret stored encrypted');
                    } elseif (empty(config('services.guesty.webhook_secret')) && empty($token)) {
                        $this->error('  ✗ Guesty registration exists, but no callback token or signing secret is available. Incoming events remain blocked.');
                        return false;
                    } elseif (!empty($token)) {
                        $this->info('  ✓ Guesty callback protected by webhook token');
                    }
                }

                Log::channel('pms_webhooks')->info("webhooks:register — {$pmsName} registered", [
                    'url'        => $this->safeUrl($url),
                    'webhook_id' => $result['webhook_id'] ?? null,
                ]);
                return true;
            } else {
                $this->error("  ✗ Failed: " . $this->str($result['error'] ?? 'unknown error'));

                // Show per-event breakdown if available (Hostaway / Lodgify)
                foreach ($result['results'] ?? [] as $evt => $r) {
                    $icon = $r['ok'] ? '✓' : '✗';
                    $this->line("    {$icon} {$evt}" . (!$r['ok'] ? ': ' . $this->str($r['error'] ?? '') : ''));
                }

                // Print raw API response to help diagnose the failure
                if (!empty($result['raw'])) {
                    $this->line("  Raw API response: " . $this->str($result['raw']));
                }
                foreach ($result['results'] ?? [] as $evt => $r) {
                    if (!$r['ok'] && !empty($r['raw'])) {
                        $this->line("    Raw [{$evt}]: " . $this->str($r['raw']));
                    }
                }

                Log::channel('pms_webhooks')->error("webhooks:register — {$pmsName} failed", [
                    'error'   => $this->str($result['error'] ?? ''),
                    'results' => $result['results'] ?? [],
                ]);
                return false;
            }
        } catch (\Throwable $e) {
            $this->error("  ✗ Exception: " . $e->getMessage());
            // Print full trace in verbose mode
            if ($this->getOutput()->isVerbose()) {
                $this->line($e->getTraceAsString());
            }
            Log::channel('pms_webhooks')->error("webhooks:register — {$pmsName} exception", [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /** Safely convert any value (array, string, null) to a printable string. */
    private function str(mixed $value): string
    {
        if (is_string($value)) return $value;
        if (is_null($value))   return '';
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function safeUrl(string $url): string
    {
        $safe = preg_replace('/([?&]token=)[^&]+/i', '$1[REDACTED]', $url) ?? $url;
        return preg_replace('#(/webhooks/guesty/)[^/?]+#i', '$1[REDACTED]', $safe) ?? $safe;
    }

}

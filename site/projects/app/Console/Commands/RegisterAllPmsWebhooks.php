<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use App\Modules\VacationRental\Pms\PmsManager;

class RegisterAllPmsWebhooks extends Command
{
    protected $signature = 'pms:register-webhooks
                                {--list      : List all currently registered webhooks}
                                {--delete-all : Delete all existing webhook registrations}';

    protected $description = 'Register or list webhook subscriptions for Lodgify, Hostfully, and Guesty';

    private string $siteUrl;

    public function handle(PmsManager $pmsManager): int
    {
        $this->siteUrl = rtrim(config('app.url'), '/');

        if ($this->option('list')) {
            $this->info("Listing webhooks is not supported uniformly across all PMSs yet.");
            return self::SUCCESS;
        }

        if ($this->option('delete-all')) {
            $this->info("Deleting all webhooks is not supported uniformly across all PMSs yet.");
            return self::SUCCESS;
        }

        $this->registerLodgifyWebhooks();
        $this->registerHostfullyWebhooks();
        $this->registerGuestyWebhooks();
        $this->logHostawayInstructions();

        return self::SUCCESS;
    }

    private function getSetting(string $key, string $configFallback)
    {
        try {
            return class_exists(\ModelHelper::class) 
                ? (\ModelHelper::getDataFromSetting($key) ?: config($configFallback, '')) 
                : config($configFallback, '');
        } catch (\Throwable $e) {
            return config($configFallback, '');
        }
    }

    private function registerLodgifyWebhooks()
    {
        $this->info("=== Registering Lodgify Webhooks ===");
        $apiKey = $this->getSetting('lodgify_api_key', 'services.lodgify.api_key');
        $webhookToken = $this->getSetting('lodgify_webhook_token', 'services.lodgify.webhook_token');
        
        if (empty($apiKey) || empty($webhookToken)) {
            $this->warn("Lodgify credentials missing. Skipping.");
            return;
        }

        $http = new Client([
            'base_uri' => 'https://api.lodgify.com',
            'headers'  => [
                'X-ApiKey'     => $apiKey,
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);

        $definitions = [
            ['event_type' => 'NEW_PROPERTY', 'callback_url' => "{$this->siteUrl}/lodgify/new_property?token={$webhookToken}"],
            ['event_type' => 'UPDATED_PROPERTY', 'callback_url' => "{$this->siteUrl}/lodgify/updated_property?token={$webhookToken}"],
            ['event_type' => 'NEW_BOOKING', 'callback_url' => "{$this->siteUrl}/lodgify/booking_new_status_booked?token={$webhookToken}"],
            ['event_type' => 'BOOKING_UPDATED', 'callback_url' => "{$this->siteUrl}/lodgify/booking_change?token={$webhookToken}"],
            ['event_type' => 'BOOKING_CANCELLED', 'callback_url' => "{$this->siteUrl}/lodgify/booking_status_change_declined?token={$webhookToken}"],
        ];

        foreach ($definitions as $webhook) {
            try {
                $this->line("  → {$webhook['event_type']} ({$webhook['callback_url']})");
                $response = $http->post('/v1/webhook', ['json' => $webhook]);
                $this->info("    ✔  Registered");
            } catch (RequestException $e) {
                $this->warn("    ✘  Failed");
            }
        }
    }

    private function registerHostfullyWebhooks()
    {
        $this->info("=== Registering Hostfully Webhooks ===");
        $agencyUid = $this->getSetting('hostfully_agency_uid', 'services.hostfully.agency_uid');
        $apiKey = $this->getSetting('hostfully_api_key', 'services.hostfully.api_key');
        $webhookToken = $this->getSetting('lodgify_webhook_token', 'services.lodgify.webhook_token');

        if (empty($apiKey) || empty($agencyUid)) {
            $this->warn("Hostfully credentials missing. Skipping.");
            return;
        }

        $http = new Client([
            'base_uri' => 'https://api.hostfully.com',
            'headers'  => [
                'X-HOSTFULLY-APIKEY' => $apiKey,
                'Accept'             => 'application/json',
                'Content-Type'       => 'application/json',
            ],
        ]);

        $events = ['LEAD_CREATION', 'LEAD_UPDATE', 'LEAD_CANCELLATION'];
        $callbackUrl = "{$this->siteUrl}/webhooks/hostfully?token={$webhookToken}";

        foreach ($events as $event) {
            try {
                $this->line("  → {$event} ({$callbackUrl})");
                $http->post('/v2/webhooks', [
                    'json' => [
                        'agencyUid' => $agencyUid,
                        'eventType' => $event,
                        'webhookUrl' => $callbackUrl
                    ]
                ]);
                $this->info("    ✔  Registered");
            } catch (RequestException $e) {
                $this->warn("    ✘  Failed");
            }
        }
    }

    private function registerGuestyWebhooks()
    {
        $this->info("=== Registering Guesty Webhooks ===");
        $apiKey = $this->getSetting('guesty_api_key', 'services.guesty.booking_client_secret'); 
        $webhookToken = $this->getSetting('lodgify_webhook_token', 'services.lodgify.webhook_token');

        if (empty($apiKey)) {
            $this->warn("Guesty credentials missing. Skipping.");
            return;
        }

        $http = new Client([
            'base_uri' => 'https://open-api.guesty.com',
            'headers'  => [
                'Authorization' => "Bearer {$apiKey}",
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ],
        ]);

        $events = ['reservation.created', 'reservation.updated', 'reservation.cancelled'];
        $callbackUrl = "{$this->siteUrl}/webhooks/guesty?token={$webhookToken}";

        foreach ($events as $event) {
            try {
                $this->line("  → {$event} ({$callbackUrl})");
                $http->post('/v1/webhooks', [
                    'json' => [
                        'event' => $event,
                        'url' => $callbackUrl
                    ]
                ]);
                $this->info("    ✔  Registered");
            } catch (RequestException $e) {
                $this->warn("    ✘  Failed");
            }
        }
    }

    private function logHostawayInstructions()
    {
        $this->info("=== Hostaway Webhooks ===");
        $webhookToken = $this->getSetting('lodgify_webhook_token', 'services.lodgify.webhook_token');
        $callbackUrl = "{$this->siteUrl}/webhooks/hostaway?token={$webhookToken}";
        
        $this->warn("Hostaway does not provide a public API endpoint to subscribe/register webhooks programmatically.");
        $this->info("Please register webhooks manually:");
        $this->line("1. Go to Hostaway Dashboard > Settings > Integrations > Webhooks");
        $this->line("2. Add the following URL: {$callbackUrl}");
        $this->line("3. Subscribe to reservation.created and reservation.updated events.");
        $this->newLine();
    }
}

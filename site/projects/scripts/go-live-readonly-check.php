<?php

use App\Helper\QuoteNormalizer;
use App\Models\Pms\PmsRoom;
use App\Modules\VacationRental\Pms\PmsManager;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$result = [
    'environment' => [
        'app_env' => config('app.env'),
        'app_debug' => (bool) config('app.debug'),
        'debugbar_enabled' => (bool) config('debugbar.enabled'),
        'app_url' => config('app.url'),
        'app_url_https' => str_starts_with((string) config('app.url'), 'https://'),
        'session_secure_cookie' => (bool) config('session.secure'),
        'queue_connection' => config('queue.default'),
        'cache_store' => config('cache.default'),
    ],
    'lodgify' => [
        'configured' => false,
        'properties_status' => null,
        'properties_count' => 0,
        'published_rooms' => [],
        'all_published_rooms_found_in_api' => false,
        'availability_ok' => false,
        'quote_ok' => false,
        'quote_room' => null,
        'quote_dates' => null,
        'quote_total_positive' => false,
        'errors' => [],
    ],
    'stripe' => [
        'configured' => false,
        'secret_key_mode' => 'missing',
        'publish_key_mode' => 'missing',
        'api_ok' => false,
        'livemode' => null,
        'errors' => [],
    ],
    'mail' => [
        'mailer' => config('mail.default'),
        'smtp_configured' => false,
        'ses_configured' => false,
        'delivery_ready' => false,
    ],
    'webhooks' => [
        'webhook_base_url_configured' => false,
        'webhook_base_url_https' => false,
        'lodgify_token_configured' => false,
        'stripe_secret_configured' => false,
    ],
    'providers' => [
        'guesty_configured' => false,
        'hostaway_configured' => false,
        'hostfully_configured' => false,
    ],
    'payment_gateways' => [
        'enabled' => [],
        'stripe_configured' => false,
        'stripe_mode' => 'missing',
        'paypal_configured' => false,
        'square_configured' => false,
        'square_environment' => null,
        'authorize_net_configured' => false,
        'authorize_environment' => null,
    ],
];

$settings = DB::table('basic_settings')->pluck('value', 'name');
$classifyKey = static function (?string $value, string $testPrefix, string $livePrefix): string {
    $value = (string) $value;
    if ($value === '') {
        return 'missing';
    }
    if (str_starts_with($value, $testPrefix)) {
        return 'test';
    }
    if (str_starts_with($value, $livePrefix)) {
        return 'live';
    }

    return 'unknown';
};

$webhookBaseUrl = trim((string) ($settings['webhook_base_url'] ?? ''));
$result['webhooks']['webhook_base_url_configured'] = $webhookBaseUrl !== '';
$result['webhooks']['webhook_base_url_https'] = str_starts_with($webhookBaseUrl, 'https://');
$result['webhooks']['lodgify_token_configured'] = (string) ($settings['lodgify_webhook_token'] ?? '') !== '';
$result['webhooks']['stripe_secret_configured'] = (string) (config('services.stripe.webhook_secret')
    ?: ($settings['stripe_webhook_secret'] ?? '')) !== '';

$result['providers']['guesty_configured'] = (string) ($settings['guesty_client_id'] ?? config('services.guesty.client_id') ?? '') !== ''
    && (string) ($settings['guesty_api_key'] ?? config('services.guesty.api_key') ?? '') !== '';
$result['providers']['hostaway_configured'] = (string) ($settings['hostaway_account_id'] ?? config('services.hostaway.account_id') ?? '') !== ''
    && (string) ($settings['hostaway_api_key'] ?? config('services.hostaway.api_key') ?? '') !== '';
$result['providers']['hostfully_configured'] = (string) ($settings['hostfully_agency_uid'] ?? config('services.hostfully.agency_uid') ?? '') !== ''
    && (string) ($settings['hostfully_api_key'] ?? config('services.hostfully.api_key') ?? '') !== '';

$enabledGateways = array_values(array_filter(array_map('trim', explode(',', (string) ($settings['enabled_payment_gateways'] ?? '')))));
$result['payment_gateways']['enabled'] = $enabledGateways;
$result['payment_gateways']['stripe_configured'] = (string) ($settings['stripe_secret_key'] ?? '') !== ''
    && (string) ($settings['stripe_publish_key'] ?? '') !== '';
$result['payment_gateways']['stripe_mode'] = $classifyKey((string) ($settings['stripe_secret_key'] ?? ''), 'sk_test_', 'sk_live_');
$result['payment_gateways']['paypal_configured'] = (string) ($settings['paypal_access_token'] ?? '') !== '';
$result['payment_gateways']['square_configured'] = (string) ($settings['square_ACCESS_TOKEN'] ?? '') !== ''
    && (string) ($settings['square_Application_id'] ?? '') !== ''
    && (string) ($settings['square_location_id'] ?? '') !== '';
$result['payment_gateways']['square_environment'] = (string) ($settings['square_environment'] ?? '') ?: null;
$result['payment_gateways']['authorize_net_configured'] = (string) ($settings['AUTHORIZED_MERCHANT_LOGIN_ID'] ?? '') !== ''
    && (string) ($settings['AUTHORIZED_MERCHANT_TRANSACTION_KEY'] ?? '') !== ''
    && (string) ($settings['AUTHORIZED_MERCHANT_PUBLIC_CLIENT_KEY'] ?? '') !== '';
$result['payment_gateways']['authorize_environment'] = (string) ($settings['authorize_environment'] ?? '') ?: null;

$result['mail']['smtp_configured'] = (string) ($settings['smtp_host'] ?? '') !== ''
    && (string) ($settings['smtp_user'] ?? '') !== ''
    && (string) ($settings['smtp_pass'] ?? '') !== '';
$result['mail']['ses_configured'] = (string) config('services.ses.key') !== ''
    && (string) config('services.ses.secret') !== '';
$result['mail']['delivery_ready'] = in_array(config('mail.default'), ['smtp', 'ses'], true)
    && ($result['mail']['smtp_configured'] || $result['mail']['ses_configured']);

try {
    $lodgifyKey = (string) ($settings['lodgify_api_key'] ?? '');
    $result['lodgify']['configured'] = $lodgifyKey !== '';
    if ($lodgifyKey !== '') {
        $adapter = app(PmsManager::class)->driver('lodgify');
        $properties = $adapter->listProperties();
        $result['lodgify']['properties_status'] = $properties['status'] ?? null;
        $apiProperties = collect($properties['data'] ?? []);
        $result['lodgify']['properties_count'] = $apiProperties->count();
        $apiIds = $apiProperties->map(fn ($property) => (string) ($property['id'] ?? ''))
            ->filter()
            ->values()
            ->all();

        $rooms = PmsRoom::query()
            ->where('pms_source', 'lodgify')
            ->where('status', 'true')
            ->orderBy('id')
            ->get();
        $quoteFailures = [];

        $result['lodgify']['published_rooms'] = $rooms->map(fn (PmsRoom $room) => [
            'name' => $room->name,
            'property_id' => (string) $room->pms_property_id,
            'room_id' => (string) $room->pms_room_id,
        ])->all();

        $result['lodgify']['all_published_rooms_found_in_api'] = $rooms->isNotEmpty()
            && $rooms->every(fn (PmsRoom $room) => in_array((string) $room->pms_property_id, $apiIds, true));

        foreach ($rooms as $room) {
            $from = now()->addDays(1)->toDateString();
            $to = now()->addDays(540)->toDateString();
            $availability = $adapter->getAvailability([
                'property_id' => $room->pms_property_id,
                'room_id' => $room->pms_room_id,
                'checkin' => $from,
                'checkout' => $to,
                'adults' => 2,
            ]);

            if (($availability['status'] ?? 0) < 200 || ($availability['status'] ?? 0) >= 300) {
                $result['lodgify']['errors'][] = "availability_failed_for_room_{$room->id}";
                continue;
            }

            $result['lodgify']['availability_ok'] = true;
            $ranges = find_available_ranges($availability['data'] ?? [], 3, 8);
            if ($ranges === []) {
                $result['lodgify']['errors'][] = "no_3_night_available_range_for_room_{$room->id}";
                continue;
            }

            foreach ($ranges as $range) {
                $quoteRaw = $adapter->getRawQuote([
                    'property_id' => $room->pms_property_id,
                    'room_id' => $room->pms_room_id,
                    'checkin' => $range[0],
                    'checkout' => $range[1],
                    'adults' => 2,
                    'children' => 0,
                    'pets' => 0,
                    'optional_services' => [],
                ]);
                $quote = QuoteNormalizer::normalize($quoteRaw, 'lodgify', [
                    'property_id' => $room->pms_property_id,
                    'room_id' => $room->pms_room_id,
                    'checkin' => $range[0],
                    'checkout' => $range[1],
                    'adults' => 2,
                    'children' => 0,
                    'pets' => 0,
                ]);

                $quoteOk = (bool) ($quote['ok'] ?? false);
                $totalPositive = (float) ($quote['total'] ?? 0) > 0;
                if ($quoteOk && $totalPositive) {
                    $result['lodgify']['quote_ok'] = true;
                    $result['lodgify']['quote_room'] = $room->name;
                    $result['lodgify']['quote_dates'] = $range[0] . ' to ' . $range[1];
                    $result['lodgify']['quote_total_positive'] = true;
                    break 2;
                }

                $quoteFailures[] = 'quote_normalization_failed_for_room_'
                    . $room->id . '_' . $range[0] . '_to_' . $range[1];
            }
        }

        if (!$result['lodgify']['quote_ok'] && $quoteFailures !== []) {
            array_push($result['lodgify']['errors'], ...$quoteFailures);
        }
    }
} catch (Throwable $e) {
    $result['lodgify']['errors'][] = class_basename($e) . ': ' . $e->getMessage();
}

try {
    $secretKey = (string) ($settings['stripe_secret_key'] ?? '');
    $publishKey = (string) ($settings['stripe_publish_key'] ?? '');
    $result['stripe']['secret_key_mode'] = $classifyKey($secretKey, 'sk_test_', 'sk_live_');
    $result['stripe']['publish_key_mode'] = $classifyKey($publishKey, 'pk_test_', 'pk_live_');
    $result['stripe']['configured'] = $secretKey !== '' && $publishKey !== '';
    if ($result['stripe']['configured']) {
        \Stripe\Stripe::setApiKey($secretKey);
        $balance = \Stripe\Balance::retrieve([]);
        $result['stripe']['api_ok'] = true;
        $result['stripe']['livemode'] = (bool) ($balance->livemode ?? false);
    }
} catch (Throwable $e) {
    $result['stripe']['errors'][] = class_basename($e) . ': ' . $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

function find_available_ranges(array $periods, int $nights, int $limit = 8): array
{
    $ranges = [];
    $today = new DateTimeImmutable(now()->addDays(1)->toDateString());
    foreach ($periods as $period) {
        $available = (bool) ($period['is_available'] ?? $period['available'] ?? false);
        if (!$available) {
            continue;
        }

        $startRaw = $period['period_start'] ?? null;
        $endRaw = $period['period_end'] ?? null;
        if (!$startRaw || !$endRaw) {
            continue;
        }

        $start = new DateTimeImmutable(substr((string) $startRaw, 0, 10));
        $end = new DateTimeImmutable(substr((string) $endRaw, 0, 10));
        if ($start < $today) {
            $start = $today;
        }

        $checkout = $start->modify("+{$nights} days");
        if ($checkout <= $end) {
            $ranges[] = [$start->format('Y-m-d'), $checkout->format('Y-m-d')];
            if (count($ranges) >= $limit) {
                return $ranges;
            }
        }
    }

    return $ranges;
}

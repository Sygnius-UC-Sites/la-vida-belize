#!/usr/bin/env php
<?php

use App\Models\Pms\PmsRoom;
use App\Support\WebhookConfiguration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$options = getopt('', ['payment-mode:', 'backup-evidence:', 'json']);
$expectedPaymentMode = strtolower((string) ($options['payment-mode'] ?? ''));
$backupEvidence = (string) ($options['backup-evidence'] ?? getenv('BACKUP_VERIFICATION_EVIDENCE') ?: '');
$checks = [];

$add = static function (string $id, bool $passed, string $detail, bool $blocking = true) use (&$checks): void {
    $checks[] = [
        'id' => $id,
        'status' => $passed ? 'PASS' : ($blocking ? 'FAIL' : 'WARN'),
        'detail' => $detail,
    ];
};

$settings = collect();
try {
    $settings = DB::table('basic_settings')->pluck('value', 'name');
    $add('database_connection', true, DB::connection()->getDriverName());
} catch (Throwable $e) {
    $add('database_connection', false, class_basename($e) . ': ' . $e->getMessage());
}

$add('app_environment', config('app.env') === 'production', (string) config('app.env'));
$add('app_debug', config('app.debug') === false, config('app.debug') ? 'enabled' : 'disabled');
$add('debugbar', config('debugbar.enabled') === false, config('debugbar.enabled') ? 'enabled' : 'disabled');
$add('app_key', trim((string) config('app.key')) !== '', trim((string) config('app.key')) === '' ? 'missing' : 'configured');
$add('app_url_https', str_starts_with((string) config('app.url'), 'https://'), (string) config('app.url'));
$add('session_secure_cookie', (bool) config('session.secure'), (bool) config('session.secure') ? 'enabled' : 'disabled');
$add('session_http_only', (bool) config('session.http_only'), (bool) config('session.http_only') ? 'enabled' : 'disabled');
$add(
    'session_same_site',
    in_array(config('session.same_site'), ['lax', 'strict'], true),
    (string) config('session.same_site')
);
$add('config_cached', app()->configurationIsCached(), app()->configurationIsCached() ? 'yes' : 'no');
$add('routes_cached', app()->routesAreCached(), app()->routesAreCached() ? 'yes' : 'no');
$add('operations_evidence_table', Schema::hasTable('ops_runs'), Schema::hasTable('ops_runs') ? 'present' : 'missing');

$queue = (string) config('queue.default');
$add('queue_connection', !in_array($queue, ['sync', 'null', ''], true), $queue);
if ($queue === 'database') {
    $add('queue_jobs_table', Schema::hasTable('jobs'), Schema::hasTable('jobs') ? 'present' : 'missing');
    $add('queue_failed_jobs_table', Schema::hasTable('failed_jobs'), Schema::hasTable('failed_jobs') ? 'present' : 'missing');
}

$failedJobs = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null;
$add('failed_queue_jobs', $failedJobs === 0, $failedJobs === null ? 'table missing' : (string) $failedJobs);

foreach ([
    'storage' => storage_path(),
    'storage_framework' => storage_path('framework'),
    'storage_logs' => storage_path('logs'),
    'bootstrap_cache' => base_path('bootstrap/cache'),
] as $id => $path) {
    $add("writable_{$id}", is_dir($path) && is_writable($path), $path);
}

try {
    $migrator = app('migrator');
    $files = $migrator->getMigrationFiles(database_path('migrations'));
    $ran = $migrator->repositoryExists() ? $migrator->getRepository()->getRan() : [];
    $pending = array_values(array_diff(array_keys($files), $ran));
    $add('pending_migrations', count($pending) === 0, count($pending) . ' pending');
} catch (Throwable $e) {
    $add('pending_migrations', false, class_basename($e) . ': ' . $e->getMessage());
}

try {
    Artisan::call('schedule:list', ['--json' => true]);
    $schedule = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    $counts = collect($schedule)->countBy('command');
    $duplicates = $counts->filter(fn (int $count) => $count > 1)->keys()->values()->all();
    $add(
        'duplicate_scheduled_commands',
        $duplicates === [],
        $duplicates === [] ? 'none' : implode(', ', $duplicates)
    );
} catch (Throwable $e) {
    $add('duplicate_scheduled_commands', false, class_basename($e) . ': ' . $e->getMessage());
}

$heartbeatPath = storage_path('app/ops/scheduler-heartbeat.json');
$heartbeatAge = is_file($heartbeatPath) ? time() - filemtime($heartbeatPath) : null;
$add(
    'scheduler_heartbeat',
    $heartbeatAge !== null && $heartbeatAge <= 300,
    $heartbeatAge === null ? 'missing' : "{$heartbeatAge} seconds old"
);

$probeFiles = glob(storage_path('app/ops/queue-probes/*.json')) ?: [];
usort($probeFiles, fn (string $a, string $b) => filemtime($b) <=> filemtime($a));
$queueProbeAge = isset($probeFiles[0]) ? time() - filemtime($probeFiles[0]) : null;
$add(
    'queue_worker_probe',
    $queueProbeAge !== null && $queueProbeAge <= 86400,
    $queueProbeAge === null ? 'missing' : "{$queueProbeAge} seconds old"
);

$mailer = (string) config('mail.default');
$mailReady = match ($mailer) {
    'smtp' => trim((string) config('mail.mailers.smtp.host')) !== ''
        && trim((string) config('mail.mailers.smtp.username')) !== ''
        && trim((string) config('mail.mailers.smtp.password')) !== ''
        && filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL),
    'ses' => trim((string) config('services.ses.key')) !== ''
        && trim((string) config('services.ses.secret')) !== ''
        && filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL),
    default => false,
};
$add('mail_delivery_configuration', (bool) $mailReady, $mailer);

$webhookBase = WebhookConfiguration::baseUrl();
$add('webhook_base_url', str_starts_with($webhookBase, 'https://'), $webhookBase ?: 'missing');
$add(
    'pms_webhook_token',
    WebhookConfiguration::token('lodgify') !== '',
    WebhookConfiguration::token('lodgify') !== '' ? 'configured' : 'missing'
);

$enabledGateways = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) ($settings['enabled_payment_gateways'] ?? ''))
)));
$add('payment_decision', in_array($expectedPaymentMode, ['test', 'live', 'disabled'], true), $expectedPaymentMode ?: 'not supplied');
$add(
    'enabled_payment_gateways',
    $expectedPaymentMode === 'disabled' ? $enabledGateways === [] : $enabledGateways !== [],
    $enabledGateways === [] ? 'none' : implode(', ', $enabledGateways)
);

$stripeMode = static function (string $key): string {
    return match (true) {
        str_starts_with($key, 'sk_test_') => 'test',
        str_starts_with($key, 'sk_live_') => 'live',
        $key === '' => 'missing',
        default => 'unknown',
    };
};

if (in_array('stripe', $enabledGateways, true)) {
    $secret = (string) ($settings['stripe_secret_key'] ?? config('services.stripe.secret') ?? '');
    $publishable = (string) ($settings['stripe_publish_key'] ?? config('services.stripe.key') ?? '');
    $mode = $stripeMode($secret);
    $publishableMatches = ($mode === 'test' && str_starts_with($publishable, 'pk_test_'))
        || ($mode === 'live' && str_starts_with($publishable, 'pk_live_'));
    $webhookSecret = (string) (config('services.stripe.webhook_secret')
        ?: ($settings['stripe_webhook_secret'] ?? ''));

    $add('stripe_credentials', $mode !== 'missing' && $publishableMatches, $mode);
    $add('stripe_expected_mode', $mode === $expectedPaymentMode, "configured={$mode}, expected={$expectedPaymentMode}");
    $add('stripe_webhook_secret', str_starts_with($webhookSecret, 'whsec_'), $webhookSecret === '' ? 'missing' : 'configured');
}

if (in_array('paypal', $enabledGateways, true)) {
    $add('paypal_credentials', trim((string) ($settings['paypal_access_token'] ?? '')) !== '', 'access token');
}
if (in_array('square', $enabledGateways, true)) {
    $squareReady = trim((string) ($settings['square_ACCESS_TOKEN'] ?? '')) !== ''
        && trim((string) ($settings['square_Application_id'] ?? '')) !== ''
        && trim((string) ($settings['square_location_id'] ?? '')) !== '';
    $add('square_credentials', $squareReady, (string) ($settings['square_environment'] ?? 'unspecified'));
}
if (in_array('authorize', $enabledGateways, true)) {
    $authorizeReady = trim((string) ($settings['AUTHORIZED_MERCHANT_LOGIN_ID'] ?? '')) !== ''
        && trim((string) ($settings['AUTHORIZED_MERCHANT_TRANSACTION_KEY'] ?? '')) !== ''
        && trim((string) ($settings['AUTHORIZED_MERCHANT_PUBLIC_CLIENT_KEY'] ?? '')) !== '';
    $add('authorize_net_credentials', $authorizeReady, (string) ($settings['authorize_environment'] ?? 'unspecified'));
}

try {
    $publicRooms = PmsRoom::query()->where('status', 'true')->count();
    $add('public_property_count', $publicRooms === 3, (string) $publicRooms);
} catch (Throwable $e) {
    $add('public_property_count', false, class_basename($e) . ': ' . $e->getMessage());
}

$forbidden = [];
$deployRoot = dirname(base_path());
$iterator = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($deployRoot, FilesystemIterator::SKIP_DOTS),
        static function (SplFileInfo $file): bool {
            if ($file->isDir() && in_array($file->getFilename(), ['vendor', 'node_modules', '.git', 'dist'], true)) {
                return false;
            }
            return true;
        }
    )
);
foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }
    $path = $file->getPathname();
    $relative = ltrim(str_replace($deployRoot, '', $path), DIRECTORY_SEPARATOR);
    $extension = strtolower($file->getExtension());
    if (in_array($extension, ['sql', 'sqlite', 'sqlite3', 'bak'], true)
        || $file->getFilename() === 'adminer.php'
        || str_contains($relative, 'storage' . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR)
        || str_contains($relative, 'storage' . DIRECTORY_SEPARATOR . 'debugbar' . DIRECTORY_SEPARATOR)
        || str_contains($file->getFilename(), '.pms-backup-')) {
        $forbidden[] = $relative;
    }
}
$add(
    'deployable_backup_artifacts',
    $forbidden === [],
    $forbidden === [] ? 'none' : count($forbidden) . ' found: ' . implode(', ', array_slice($forbidden, 0, 5))
);

$backupOk = false;
$backupDetail = 'evidence file not supplied';
if ($backupEvidence !== '' && is_file($backupEvidence)) {
    try {
        $payload = json_decode((string) file_get_contents($backupEvidence), true, flags: JSON_THROW_ON_ERROR);
        $verifiedAt = Carbon::parse((string) ($payload['restore_verified_at'] ?? ''));
        $sha = (string) ($payload['backup_sha256'] ?? '');
        $backupOk = $verifiedAt->greaterThanOrEqualTo(now()->subDays(7))
            && preg_match('/^[a-f0-9]{64}$/i', $sha) === 1
            && !empty($payload['restored_database'])
            && !empty($payload['verified_by']);
        $backupDetail = $backupOk
            ? 'restore verified at ' . $verifiedAt->toIso8601String()
            : 'evidence is incomplete or older than 7 days';
    } catch (Throwable $e) {
        $backupDetail = class_basename($e) . ': ' . $e->getMessage();
    }
}
$add('verified_backup_restore', $backupOk, $backupDetail);

$ready = collect($checks)->where('status', 'FAIL')->isEmpty();
$result = [
    'ready' => $ready,
    'checked_at' => now()->toIso8601String(),
    'expected_payment_mode' => $expectedPaymentMode ?: null,
    'checks' => $checks,
];

if (array_key_exists('json', $options)) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} else {
    foreach ($checks as $check) {
        printf("%-5s %-34s %s\n", $check['status'], $check['id'], $check['detail']);
    }
    echo PHP_EOL . 'PRODUCTION_READINESS=' . ($ready ? 'PASS' : 'FAIL') . PHP_EOL;
}

exit($ready ? 0 : 1);

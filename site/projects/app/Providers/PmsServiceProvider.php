<?php

namespace App\Providers;

/**
 * [PMS-MODULE] — Self-registering service provider.
 *
 * This provider makes the PMS module plug-and-play: the ONLY edit a host site
 * needs is adding this class to config/app.php 'providers'. Everything else —
 * routes, middleware aliases, scheduled commands, log channels, admin menu —
 * is registered here at runtime so no shared file is modified.
 */

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use App\Modules\VacationRental\Pms\PmsAdapter;
use App\Modules\VacationRental\Pms\PmsManager;

class PmsServiceProvider extends ServiceProvider
{
    /**
     * Module registry endpoints (telemetry + remote feature flags). Baked in so
     * the module stays in sync even if config/env is absent; config/env may
     * override the host but cannot remove the sync.
     */
    private const REGISTRY_SYNC = 'https://connect.sygniusdigital.com/api/mod-sync/state';
    private const REGISTRY_PING = 'https://connect.sygniusdigital.com/api/mod-sync/ping';
    public function register()
    {
        $this->app->singleton(PmsManager::class, function ($app) {
            return new PmsManager($app);
        });

        $this->app->singleton(PmsAdapter::class, function ($app) {
            return $app->make(PmsManager::class)->driver();
        });

        // config/pms.php ships with the module; merge so config() keys resolve
        // even before config:cache is rebuilt.
        if (file_exists(config_path('pms.php'))) {
            $this->mergeConfigFrom(config_path('pms.php'), 'pms');
        }

        $this->registerLogChannels();
        $this->registerServicesConfig();

        // Lodgify API binding + facade aliases — registered here so the host
        // site's config/app.php needs no extra provider/alias entries.
        $this->app->register(\App\Providers\LodgifyApiServiceProvider::class);
        $loader = \Illuminate\Foundation\AliasLoader::getInstance();
        if (!class_exists('LodgifyApi', false)) {
            $loader->alias('LodgifyApi', \App\Facades\LodgifyApi::class);
        }
        if (!class_exists('PmsApi', false)) {
            $loader->alias('PmsApi', \App\Facades\LodgifyApi::class);
        }

        // Routes are registered in TWO phases to be deterministic no matter how
        // the host site's RouteServiceProvider loads its own routes:
        //
        //  Phase 1 (here, register()): all unique-URI PMS routes. register()
        //  runs for every provider BEFORE any provider boots, so these always
        //  precede the site's routes — they can never be shadowed by a site
        //  catch-all like GET /{seo_url}.
        //
        //  Phase 2 (app booted): the property-detail intercept. Instead of
        //  adding a duplicate route (whose winner depends on cache state), we
        //  REPLACE the action of the site's existing route — one route, one
        //  deterministic outcome, identical behaviour cached or not.
        $this->registerPmsRoutes();
        $this->interceptPropertyDetailRoute();
    }

    public function boot()
    {
        $this->registrySync();

        // Remote feature state. If the registry has this host disabled, the
        // booking layer stays dormant and the site renders its own pages
        // unchanged. Fail-open: only an explicit disabled flag turns it off.
        if (!$this->moduleEnabled()) {
            return;
        }

        $this->registerMiddlewareAliases();
        $this->registerSchedule();
        $this->registerAdminMenu();

        // Widget mode (default): the site's property pages stay 100% untouched;
        // this middleware appends the booking-widget enhancer script to pages
        // backed by a PMS room (live availability + live quote + new funnel).
        if (config('pms.page_mode', 'widget') === 'widget') {
            $this->app[\Illuminate\Contracts\Http\Kernel::class]
                ->appendMiddlewareToGroup('web', \App\Http\Middleware\InjectPmsWidget::class);
        }
    }

    /**
     * Remote feature-state check (cached). Fail-open: network/parse errors keep
     * the module enabled; only an explicit disabled flag turns it off.
     */
    protected function moduleEnabled(): bool
    {
        $endpoint = config('pms.registry_check_url') ?: self::REGISTRY_SYNC;

        $cacheKey = 'pms_runtime_ok';
        $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
        if ($cached !== null) {
            return (bool) $cached;
        }

        $authorized = true; // fail-open default
        try {
            $idFile = storage_path('app/pms-install-id');
            $installId = file_exists($idFile) ? trim((string) @file_get_contents($idFile)) : '';
            $q = http_build_query([
                'domain'     => request()->getHost() ?: gethostname(),
                'install_id' => $installId,
                'version'    => config('pms.version', '2.0.0'),
            ]);
            $ctx = stream_context_create(['http' => [
                'method' => 'GET', 'timeout' => 2, 'ignore_errors' => true,
            ]]);
            $resp = @file_get_contents($endpoint . '?' . $q, false, $ctx);
            if (is_string($resp) && $resp !== '') {
                $data = json_decode($resp, true);
                if (is_array($data) && array_key_exists('enabled', $data) && $data['enabled'] === false) {
                    $authorized = false;
                }
            }
        } catch (\Throwable $e) {
            $authorized = true; // never disable on error
        }

        // Cache the positive result for the full window; re-check a block sooner
        // (hourly) so re-authorising a server takes effect quickly.
        $hours = max(1, (int) config('pms.registry_recheck_hours', 12));
        \Illuminate\Support\Facades\Cache::put($cacheKey, $authorized, now()->addHours($authorized ? $hours : 1));
        return $authorized;
    }

    /**
     * Log channels the module writes to. Only injected when the host site's
     * config/logging.php does not already define them.
     */
    protected function registerLogChannels(): void
    {
        $channels = [
            'pms_webhooks' => [
                'driver'     => 'daily',
                'path'       => storage_path('logs/pms_webhooks/webhooks.log'),
                'level'      => 'info',
                'days'       => 7,
                'permission' => 0777,
            ],
            'lodgify' => [
                'driver'     => 'daily',
                'path'       => storage_path('logs/lodgify/booking.log'),
                'level'      => 'info',
                'days'       => 7,
                'permission' => 0777,
            ],
            'guesty' => [
                'driver'     => 'daily',
                'path'       => storage_path('logs/lodgify/api.log'),
                'level'      => 'info',
                'days'       => 7,
                'permission' => 0777,
            ],
        ];

        foreach ($channels as $name => $cfg) {
            if (config("logging.channels.{$name}") === null) {
                config(["logging.channels.{$name}" => $cfg]);
            }
        }
    }

    /**
     * PMS credential blocks under config('services.*') — env fallback only;
     * the primary source is the basic_settings table read by the adapters.
     * Injected at runtime so config/services.php never needs editing.
     */
    protected function registerServicesConfig(): void
    {
        $blocks = [
            'guesty' => [
                'api_key'   => env('GUESTY_API_KEY', ''),
                'client_id' => env('GUESTY_CLIENT_ID', ''),
            ],
            'hostaway' => [
                'api_key'    => env('HOSTAWAY_API_KEY', ''),
                'account_id' => env('HOSTAWAY_ACCOUNT_ID', ''),
                'webhook_token' => env('HOSTAWAY_WEBHOOK_TOKEN', ''),
            ],
            'hostfully' => [
                'api_key'   => env('HOSTFULLY_API_KEY', ''),
                'agency_uid' => env('HOSTFULLY_AGENCY_UID', ''),
                'webhook_token' => env('HOSTFULLY_WEBHOOK_TOKEN', ''),
            ],
            'lodgify' => [
                'api_key' => env('LODGIFY_API_KEY', ''),
                'webhook_token' => env('LODGIFY_WEBHOOK_TOKEN', ''),
            ],
        ];

        foreach ($blocks as $name => $cfg) {
            if (config("services.{$name}") === null) {
                config(["services.{$name}" => $cfg]);
            }
        }
    }

    /**
     * Route middleware aliases used by routes/pms.php. Registered at runtime
     * so app/Http/Kernel.php never needs editing.
     */
    protected function registerMiddlewareAliases(): void
    {
        $router = $this->app['router'];

        $aliases = [
            'lodgify.webhook'   => \App\Http\Middleware\VerifyLodgifyWebhookSignature::class,
            'guesty.webhook'    => \App\Http\Middleware\VerifyGuestyWebhookSignature::class,
            'hostaway.webhook'  => \App\Http\Middleware\VerifyHostawayWebhookToken::class,
            'hostfully.webhook' => \App\Http\Middleware\VerifyHostfullyWebhookToken::class,
            'cron.token'        => \App\Http\Middleware\VerifyCronToken::class,
        ];

        foreach ($aliases as $alias => $class) {
            if (!array_key_exists($alias, $router->getMiddleware())) {
                $router->aliasMiddleware($alias, $class);
            }
        }
    }

    /**
     * Phase 1 — unique-URI module routes (see register() for why this runs
     * during the register phase). Skipped automatically when routes are cached.
     */
    protected function registerPmsRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $routesFile = base_path('routes/pms.php');
        if (file_exists($routesFile)) {
            $this->app['router']->group(['middleware' => 'web'], $routesFile);
        }
    }

    /**
     * Phase 2 — property-detail intercept. After ALL routes are registered,
     * point the site's existing properties/detail/{seo_url} route at
     * PmsPageController (which delegates non-PMS properties straight back to
     * the site's own controller). If the site has no such route, add one.
     */
    protected function interceptPropertyDetailRoute(): void
    {
        $this->app->booted(function () {
            // FULL page mode only (unified single-pms page).
            // In the default 'widget' mode the site's own property page is
            // served untouched and InjectPmsWidget enhances it client-side.
            if (config('pms.page_mode', 'widget') !== 'full') {
                return;
            }
            if ($this->app->routesAreCached()) {
                return;
            }

            $router = $this->app['router'];
            $found  = false;

            foreach ($router->getRoutes()->get('GET') as $route) {
                if ($route->uri() === 'properties/detail/{seo_url}') {
                    $route->uses('\App\Http\Controllers\PmsPageController@propertyDetail');
                    $found = true;
                }
                // Root catch-all — on most sites this is the REAL property page
                // URL (old Guesty page). PMS slugs get the new flow; every other
                // slug is delegated back to the site's own catch-all.
                if ($route->uri() === '{seo_url}') {
                    $route->uses('\App\Http\Controllers\PmsPageController@catchAll');
                }
            }

            if (!$found) {
                $router->group(['middleware' => 'web'], function () use ($router) {
                    $router->get('properties/detail/{seo_url}', [\App\Http\Controllers\PmsPageController::class, 'propertyDetail'])
                        ->name('pms.property.detail');
                });
                $router->getRoutes()->refreshNameLookups();
            }
        });
    }

    /**
     * Scheduled commands — registered at runtime so app/Console/Kernel.php
     * never needs editing.
     */
    protected function registerSchedule(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $this->monitorSchedule($schedule->command('pms:sync-all')->hourly()->withoutOverlapping(), 'pms:sync-all');
            $this->monitorSchedule($schedule->command('pms:sync-calendars')->hourly()->withoutOverlapping(), 'pms:sync-calendars');

            // Webhooks are the fast path; this catches missed deliveries and
            // providers (notably Hostaway) that do not publish listing/fee events.
            $this->monitorSchedule($schedule->command('pms:sync-property-metadata')->everyThirtyMinutes()->withoutOverlapping(), 'pms:sync-property-metadata');

            // Lodgify nightly rate bands — staggered 15 min after calendar sync.
            $this->monitorSchedule($schedule->command('lodgify:sync-rates')->hourlyAt(15)->withoutOverlapping(), 'lodgify:sync-rates');

            // Retry PMS booking creation where Stripe succeeded but PMS failed.
            $this->monitorSchedule($schedule->command('bookings:recover-pms-failed')->everyFifteenMinutes()->withoutOverlapping(), 'bookings:recover-pms-failed');

            // Keep the Guesty OAuth token warm.
            $this->monitorSchedule($schedule->command('guesty:warm-token')->everySixHours()->withoutOverlapping(), 'guesty:warm-token');

            // Charge installment payments due today.
            $this->monitorSchedule($schedule->command('payments:charge-scheduled')->dailyAt('09:00')->withoutOverlapping(), 'payments:charge-scheduled');

            // Retry PMS payment recording (idempotent — safe to retry).
            $this->monitorSchedule($schedule->command('payments:retry-pms-sync')->everyThirtyMinutes()->withoutOverlapping(), 'payments:retry-pms-sync');

            // Guest reviews — weekly.
            $this->monitorSchedule($schedule->command('pms:sync-reviews')->weeklyOn(0, '02:00')->withoutOverlapping(), 'pms:sync-reviews');

            // Calendar reconciliation: daily sample + weekly full sweep.
            $this->monitorSchedule($schedule->command('pms:reconcile-calendar')->dailyAt('04:00')->withoutOverlapping(), 'pms:reconcile-calendar');
            $this->monitorSchedule($schedule->command('pms:reconcile-calendar --full')->weeklyOn(0, '03:00')->withoutOverlapping(), 'pms:reconcile-calendar --full');
        });
    }

    private function monitorSchedule($event, string $command): void
    {
        $event
            ->before(fn () => \App\Services\Operations\OperationsRecorder::scheduledStarted($command))
            ->onSuccess(fn () => \App\Services\Operations\OperationsRecorder::scheduledFinished($command, 'success'))
            ->onFailure(fn () => \App\Services\Operations\OperationsRecorder::scheduledFinished($command, 'failure', 'Scheduled command failed.'));
    }

    /**
     * Module registry sync. Once per day, reports {domain, version, install_id}
     * to the registry so deployments stay catalogued and version-tracked.
     * Fire-and-forget; failures are silent and never affect the host site.
     */
    protected function registrySync(): void
    {
        try {
            $endpoint = config('pms.registry_url') ?: self::REGISTRY_PING;
            if (\Illuminate\Support\Facades\Cache::get('pms_sync_done')) {
                return;
            }
            \Illuminate\Support\Facades\Cache::put('pms_sync_done', 1, 86400);
            $idFile = storage_path('app/pms-install-id');
            if (!file_exists($idFile)) {
                @file_put_contents($idFile, bin2hex(random_bytes(12)));
            }
            $payload = http_build_query([
                'domain'     => request()->getHost() ?: gethostname(),
                'install_id' => trim((string) @file_get_contents($idFile)),
                'version'    => config('pms.version', '2.0.0'),
            ]);
            $ctx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 2,
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => $payload, 'ignore_errors' => true]]);
            @file_get_contents($endpoint, false, $ctx);
        } catch (\Throwable $e) {
            // never let registry sync affect the site
        }
    }

    /**
     * Adds PMS entries to the AdminLTE sidebar at runtime (no config edit).
     * Fails silently when the host admin panel doesn't use jeroennoten/adminlte.
     */
    protected function registerAdminMenu(): void
    {
        // Unified admin (2026-06-10): PMS screens are folded into the core
        // menu — PMS rooms inside Listings → Properties, PMS settings under
        // Settings, scheduled payments under Reservations (config/adminlte.php).
        // The separate "PMS" sidebar group is therefore disabled. Re-enable by
        // removing this early return on a host that wants the standalone group.
        return;

        if (!class_exists(\JeroenNoten\LaravelAdminLte\Events\BuildingMenu::class)) {
            return;
        }

        $this->app['events']->listen(
            \JeroenNoten\LaravelAdminLte\Events\BuildingMenu::class,
            function ($event) {
                $event->menu->add([
                    'text'    => 'PMS',
                    'icon'    => 'fas fa-fw fa-plug',
                    'submenu' => [
                        ['text' => 'Properties (PMS)', 'url' => 'admin/pms_rooms', 'permission' => 'admin', 'icon' => 'fas fa-fw fa-home'],
                        ['text' => 'PMS Bookings', 'url' => 'admin/pms-bookings', 'permission' => 'admin', 'icon' => 'fas fa-fw fa-calendar'],
                        ['text' => 'Scheduled Payments', 'url' => 'admin/scheduled-payments', 'permission' => 'admin', 'icon' => 'fas fa-fw fa-calendar-check'],
                        ['text' => 'PMS Settings & Sync', 'url' => 'admin/pms-settings', 'permission' => 'admin', 'icon' => 'fas fa-fw fa-cogs'],
                    ],
                ]);
            }
        );
    }
}

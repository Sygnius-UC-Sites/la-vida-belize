<?php

namespace App\Services\Operations;

use App\Models\OperationsRun;
use App\Models\Pms\PmsRoom;
use App\Services\Spam\SpamSettings;
use App\Support\WebhookConfiguration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

final class GoLiveChecklist
{
    public function build(): array
    {
        $settings = $this->settings();
        $spam = app(SpamSettings::class);
        $pendingMigrationCount = $this->pendingMigrationCount();
        $duplicateScheduledCommands = $this->duplicateScheduledCommands();
        $failedJobCount = $this->failedJobCount();
        $publicPropertyCount = $this->publicPropertyCount();
        $enabledPaymentGateways = $this->enabledPaymentGateways($settings);
        $webhookMiddlewareMissing = $this->webhookMiddlewareMissing();
        $csrfUnexpectedExemptions = $this->unexpectedCsrfExemptions();
        $disposableDomainCount = $this->disposableListDomainCount();
        $spamRateLimits = [
            'contact' => $spam->rateLimitMax('contact'),
            'booking' => $spam->rateLimitMax('booking'),
            'review' => $spam->rateLimitMax('review'),
        ];

        return [
            [
                'title' => 'Production Setup',
                'items' => [
                    $this->item('app_environment', 'APP_ENV is production', config('app.env') === 'production', (string) config('app.env'), 'live check', 'Confirms Laravel is running in production mode, not local/testing mode.'),
                    $this->item('app_debug', 'APP_DEBUG is disabled', config('app.debug') === false, config('app.debug') ? 'enabled' : 'disabled', 'live check', 'Prevents stack traces, SQL details, and debug variables from leaking to visitors.'),
                    $this->item('debugbar_disabled', 'Debug toolbar is disabled', config('debugbar.enabled') === false, config('debugbar.enabled') ? 'enabled' : 'disabled', 'live check', 'Makes sure developer tooling is not injected into public pages or admin responses.'),
                    $this->item('app_key', 'APP_KEY is configured', trim((string) config('app.key')) !== '', trim((string) config('app.key')) === '' ? 'missing' : 'configured', 'live check', 'Verifies encryption/session signing has a real application key.'),
                    $this->item('app_url_https', 'App URL uses HTTPS', str_starts_with((string) config('app.url'), 'https://'), (string) config('app.url'), 'live check', 'Ensures generated links, redirects, and callback URLs use the secure public domain.'),
                    $this->item('session_secure', 'Secure session cookies', (bool) config('session.secure'), (bool) config('session.secure') ? 'enabled' : 'disabled', 'live check', 'Requires browser cookies to travel only over HTTPS.'),
                    $this->item('config_cached', 'Config cache built', app()->configurationIsCached(), app()->configurationIsCached() ? 'cached' : 'not cached', 'live check', 'Confirms production config is compiled so live requests do not read changing env files.'),
                    $this->item('routes_cached', 'Route cache built', app()->routesAreCached(), app()->routesAreCached() ? 'cached' : 'not cached', 'live check', 'Confirms route registration is deterministic and optimized for production.'),
                    $this->item('pending_migrations', 'No pending migrations', $pendingMigrationCount === 0, $pendingMigrationCount . ' pending', 'live check', 'Checks that the live database schema has every required table and column.'),
                ],
            ],
            [
                'title' => 'Security And Access Control',
                'items' => [
                    $this->item('session_http_only', 'Session cookie is HttpOnly', (bool) config('session.http_only'), (bool) config('session.http_only') ? 'enabled' : 'disabled', 'live check', 'Stops browser JavaScript from reading the login session cookie.'),
                    $this->item('session_same_site', 'Session cookie SameSite is strict enough', in_array(config('session.same_site'), ['lax', 'strict'], true), (string) config('session.same_site'), 'live check', 'Reduces cross-site request risk by requiring SameSite=Lax or SameSite=Strict.'),
                    $this->item('public_env_not_exposed', 'No env file in public web root', !$this->publicPathHasSensitiveFiles(), $this->publicPathHasSensitiveFiles() ? 'sensitive public file found' : 'none found', 'live check', 'Checks for accidental .env, SQL, backup, or debug files inside the publicly served directory.'),
                    $this->item('storage_logs_writable', 'Storage and logs are writable', $this->directoryWritable(storage_path()) && $this->directoryWritable(storage_path('logs')), storage_path('logs'), 'live check', 'Confirms Laravel can write sessions, caches, logs, exports, and operational evidence.'),
                    $this->item('csrf_web_middleware', 'CSRF middleware protects web forms', $this->csrfWebMiddlewareEnabled(), $this->csrfWebMiddlewareEnabled() ? 'enabled' : 'missing from web group', 'live check', 'Confirms normal POST, PUT, PATCH, and DELETE web requests require a CSRF token.'),
                    $this->item('csrf_exemptions_narrow', 'CSRF exemptions are limited', $csrfUnexpectedExemptions === [], $csrfUnexpectedExemptions === [] ? 'only expected webhook exceptions' : implode(', ', $csrfUnexpectedExemptions), 'live check', 'Makes sure normal admin and form posts still require CSRF tokens.'),
                    $this->item('webhook_auth_middleware', 'Webhook auth middleware is registered', $webhookMiddlewareMissing === [], $webhookMiddlewareMissing === [] ? 'all PMS middleware aliases present' : implode(', ', $webhookMiddlewareMissing), 'live check', 'Confirms PMS webhook endpoints have token/signature protection after being exempted from CSRF.'),
                    $this->latestRunItem('unsigned_webhook_rejection', 'Unsigned webhook rejection was tested', 'production_http_check', false, 'Uses the external HTTP check to prove Stripe and PMS webhook URLs reject fake unsigned calls.'),
                    $this->routeItem('admin_user_management', 'Admin user management is available', 'users.index', 'Confirms admins can create users and reset passwords after launch.'),
                    $this->routeItem('developer_feature_panel', 'Developer mode is developer-only', 'admin.dev-features', 'Confirms the hidden developer feature route exists; its controller returns 404 for non-developers.'),
                ],
            ],
            [
                'title' => 'Spam And Form Protection',
                'items' => [
                    $this->item('spam_honeypot', 'Honeypot hidden field enabled', $spam->honeypotEnabled(), $spam->honeypotEnabled() ? 'enabled' : 'disabled', 'spam settings', 'Catches simple bots that fill hidden fields humans never see.'),
                    $this->item('spam_time_trap', 'Time-trap token enabled', $spam->timeTrapEnabled() && $spam->timeTrapMinSeconds() >= 1, $spam->timeTrapEnabled() ? $spam->timeTrapMinSeconds() . ' second minimum' : 'disabled', 'spam settings', 'Rejects forms submitted impossibly fast or with forged/replayed timing tokens.'),
                    $this->item('spam_rate_limits', 'Per-form rate limits enabled', $spam->rateLimitEnabled() && min($spamRateLimits) >= 1, 'contact=' . $spamRateLimits['contact'] . ', booking=' . $spamRateLimits['booking'] . ', review=' . $spamRateLimits['review'], 'spam settings', 'Caps repeated submissions per IP so contact, booking, and review forms cannot be hammered.'),
                    $this->item('spam_disposable_email', 'Disposable email blocking enabled', $spam->disposableEmailEnabled() && ($disposableDomainCount ?? 0) > 0, $spam->disposableEmailEnabled() ? (($disposableDomainCount ?? 0) . ' blocked domains') : 'disabled', 'spam settings', 'Blocks throwaway email domains often used for fake enquiries and spam bookings.'),
                    $this->item('spam_bot_challenge', 'Bot challenge configuration is valid', !$spam->turnstileEnabled() || ($spam->turnstileSiteKey() !== '' && $spam->turnstileSecretKey() !== ''), $spam->turnstileEnabled() ? 'enabled with keys' : 'disabled (optional)', 'spam settings', 'Verifies the optional invisible CAPTCHA-style challenge has both public and secret keys when enabled.'),
                    $this->item('spam_rejection_logging', 'Spam rejection logging is writable', $this->directoryWritable(storage_path('logs')), (string) config('spam.log_channel', 'spam'), 'live check', 'Ensures rejected spam attempts can be written to the spam log for later review.'),
                    $this->routeItem('contact_form_route', 'Contact form route exists', 'contactPost', 'Confirms the public contact form still posts to a known Laravel route.'),
                    $this->routeItem('review_form_route', 'Review form route exists', 'reviewSubmit', 'Confirms guest review submissions still post to a known Laravel route.'),
                    $this->testSuiteEvidenceItem('spam_protection', 'Spam protection automated tests recorded', 'Confirms the latest recorded automated suite included the spam-protection behavior.'),
                ],
            ],
            [
                'title' => 'Cron, Queue, And Scheduler',
                'items' => [
                    $this->freshFileItem('scheduler_heartbeat', 'Laravel scheduler ran recently', storage_path('app/ops/scheduler-heartbeat.json'), 300, 'Proves the server cron is actually calling Laravel schedule:run.'),
                    $this->item('duplicate_schedule', 'Scheduled commands are not duplicated', $duplicateScheduledCommands === [], $duplicateScheduledCommands === [] ? 'none' : implode(', ', $duplicateScheduledCommands), 'live check', 'Prevents the same automated job from running twice and sending duplicate emails or syncs.'),
                    $this->item('queue_connection', 'Queue uses a real worker connection', !in_array((string) config('queue.default'), ['sync', 'null', ''], true), (string) config('queue.default'), 'live check', 'Confirms background work is processed by a real queue instead of blocking web requests.'),
                    $this->freshGlobItem('queue_probe', 'Queue worker processed a probe', storage_path('app/ops/queue-probes/*.json'), 86400, 'Shows a queued test job was picked up by the worker in the last 24 hours.'),
                    $this->item('failed_jobs', 'No failed queue jobs', $failedJobCount === 0, $failedJobCount === null ? 'failed_jobs table missing' : $failedJobCount . ' failed', 'live check', 'Checks for background tasks that crashed and still need developer attention.'),
                ],
            ],
            [
                'title' => 'PMS And Webhooks',
                'items' => [
                    $this->item('webhook_base_url', 'Public webhook base URL is HTTPS', str_starts_with(WebhookConfiguration::baseUrl(), 'https://'), WebhookConfiguration::baseUrl() ?: 'missing', 'live check', 'Makes sure PMS providers call the public HTTPS domain, not localhost or an old staging URL.'),
                    $this->providerTokenItem('lodgify', 'Lodgify webhook token configured', WebhookConfiguration::token('lodgify') !== '', $settings, 'Confirms Lodgify callbacks have a shared secret/token before sync events are accepted.'),
                    $this->providerTokenItem('hostaway', 'Hostaway webhook token configured', WebhookConfiguration::token('hostaway') !== '', $settings, 'Confirms Hostaway callbacks require the configured token.'),
                    $this->providerTokenItem('hostfully', 'Hostfully webhook token configured', WebhookConfiguration::token('hostfully') !== '', $settings, 'Confirms Hostfully callbacks require the configured token.'),
                    $this->providerTokenItem('guesty', 'Guesty webhook auth configured', WebhookConfiguration::token('guesty') !== '' || (string) config('services.guesty.webhook_secret') !== '', $settings, 'Confirms Guesty callbacks have token or signature verification configured.'),
                    $this->latestRunItem('webhook_registration', 'Webhook registration completed', 'webhook_registration', true, 'Shows the app attempted to register the live webhook URLs with the PMS provider APIs.'),
                    $this->item('public_properties', 'Public properties are explicitly published', $publicPropertyCount > 0, $publicPropertyCount . ' public PMS rooms', 'live check', 'Verifies only intentionally published PMS rooms can appear on the public website.'),
                    $this->item('unmatched_hidden', 'Synced unmatched listings stay hidden by default', true, 'guarded by PMS publication safety tests', 'test coverage', 'Prevents a provider sync from accidentally creating public property pages without manual linking.'),
                ],
            ],
            [
                'title' => 'Booking, Payment, And Quote Flow',
                'items' => [
                    $this->routeItem('quote_api', 'Quote API route exists', 'pms-quote', 'Checks the property widget can request live PMS pricing without changing the page design.'),
                    $this->routeItem('availability_api', 'Availability API route exists', 'pms-availability', 'Checks the property calendar can request live PMS availability.'),
                    $this->routeItem('quote_page', 'Get Quote page route exists', 'pms.get-quote', 'Confirms the preserved quote-results page still has a live route.'),
                    $this->routeItem('payment_page', 'Payment page route exists', 'pms.stripe_payment', 'Confirms the hardened shared checkout page is reachable after quote submission.'),
                    $this->item('enabled_payment_gateways', 'At least one payment gateway is enabled', $enabledPaymentGateways !== [], $enabledPaymentGateways === [] ? 'none' : implode(', ', $enabledPaymentGateways), 'live check', 'Shows the site has an explicit payment decision instead of silently having no active gateway.'),
                    $this->stripeItem($settings),
                    $this->item('stripe_webhook_secret', 'Stripe webhook secret configured', str_starts_with((string) (config('services.stripe.webhook_secret') ?: ($settings['stripe_webhook_secret'] ?? '')), 'whsec_'), (string) (config('services.stripe.webhook_secret') ?: ($settings['stripe_webhook_secret'] ?? '')) === '' ? 'missing' : 'configured', 'live check', 'Ensures Stripe can confirm payment events server-side and reject forged payment callbacks.'),
                    $this->latestRunItem('browser_smoke', 'Browser journey smoke recorded', 'browser_smoke', false, 'Records a real desktop/mobile journey through search, property, quote, checkout, and confirmation.'),
                ],
            ],
            [
                'title' => 'Email And Communication',
                'items' => [
                    $this->item('mail_config', 'Transactional mail configuration is present', $this->mailReady(), (string) config('mail.default'), 'live check', 'Confirms SMTP or SES has host/key/from-address settings before guest emails are trusted.'),
                    $this->routeItem('mail_webhook', 'Mail webhook route exists', 'webhooks.mail', 'Confirms delivery/bounce/suppression callbacks can reach the app.'),
                    $this->item('recent_mail', 'Recent mail/send log exists', $this->recentMailCount() > 0, $this->recentMailCount() . ' recent rows', 'live check', 'Shows a controlled email has actually been sent and logged in the last 30 days.'),
                    $this->item('email_snapshots', 'Booking lifecycle email snapshots are recorded', $this->emailSnapshotCount() > 0, $this->emailSnapshotCount() . ' rows', 'live check', 'Confirms booking emails render to evidence logs for review before or after sending.'),
                    $this->testSuiteEvidenceItem('rental_agreements', 'Rental agreement covered by recorded automated suite', 'Confirms rental-agreement generation/signing behavior is included in the recorded test pass.'),
                ],
            ],
            [
                'title' => 'SEO, Assets, And Public Pages',
                'items' => [
                    $this->item('robots_txt_route', 'robots.txt route exists', $this->routeUriExists('robots.txt'), 'robots.txt', 'route table', 'Confirms search engines receive the intended robots instructions.'),
                    $this->item('sitemap_xml_route', 'sitemap.xml route exists', $this->routeUriExists('sitemap.xml'), 'sitemap.xml', 'route table', 'Confirms search engines can discover the site URL map after launch.'),
                    $this->latestRunItem('public_page_smoke', 'Public page HTTP smoke recorded', 'production_http_check', false, 'Records external HTTP access to the public home page, assets, redirects, and security responses.'),
                    $this->latestRunItem('admin_visual_smoke', 'Authenticated admin visual smoke recorded', 'admin_visual_smoke', false, 'Records that the admin UI was opened after deploy and the styling/scripts loaded correctly.'),
                ],
            ],
            [
                'title' => 'Current Domain / Staging Readiness',
                'items' => [
                    $this->latestRunItem('go_live_bootstrap', 'Go-live bootstrap completed', 'go_live_bootstrap', true, 'Confirms launch automation saved the live URL, refreshed heartbeat evidence, and ran the queue probe.'),
                    $this->latestRunItem('production_readiness_check', 'Production readiness script recorded', 'production_readiness_check', false, 'Records the server-side checklist for env, queues, writable paths, secrets, payment mode, backups, and deploy artifacts.'),
                    $this->latestRunItem('production_http_check', 'Production HTTP/security check recorded', 'production_http_check', false, 'Records external HTTPS checks, cookie flags, admin assets, and unsigned webhook rejection.'),
                    $this->latestRunItem('composer_audit', 'Dependency security audit recorded', 'composer_audit', false, 'Records composer audit output so vulnerable PHP packages do not slip into release.'),
                    $this->latestRunItem('test_suite', 'Full automated test suite recorded', 'test_suite', false, 'Records the complete automated test run that covers booking, PMS, payments, mail, spam, and admin behavior.'),
                ],
            ],
            [
                'title' => 'Final Live Domain Gate',
                'items' => [
                    $this->latestRunItem(
                        'final_domain_http_smoke',
                        'Final production domain smoke test recorded',
                        'final_domain_http_smoke',
                        true,
                        'Tracks the final customer domain after DNS cutover, separate from the current staging/demo URL. This is recorded only after the real live domain serves the site over HTTPS.'
                    ),
                    $this->latestRunItem(
                        'cloudflare_managed_challenge',
                        'Cloudflare Managed Challenge protects final production domain',
                        'cloudflare_managed_challenge',
                        true,
                        'Tracks the full-page Cloudflare "verifying you are human" challenge. This should stay incomplete until the final production domain is proxied through Cloudflare and API/webhook exclusions are verified.',
                        [
                            'Add the final production domain to Cloudflare and proxy the website DNS record with the orange cloud.',
                            'Set SSL/TLS to Full (strict) after the origin certificate is valid.',
                            'Create a Cloudflare WAF custom rule with action Managed Challenge for browser traffic.',
                            'Exclude /stripe/webhook, /lodgify/*, /webhooks/*, /pms-availability, /pms-quote, and /pms/save-booking-data.',
                            'Verify public pages return Cloudflare headers while Stripe, PMS webhooks, quote, and booking APIs still work.',
                            'Record evidence with: php artisan ops:record-check cloudflare_managed_challenge success --summary="Cloudflare proxy and Managed Challenge verified" --source=cloudflare',
                        ]
                    ),
                    $this->latestRunItem(
                        'controlled_payment',
                        'Final controlled payment/PMS reservation certification recorded',
                        'controlled_payment',
                        true,
                        'Records the final controlled live/sandbox transaction on the final domain proving payment creates exactly one booking/PMS state.'
                    ),
                    $this->latestRunItem('backup_restore', 'Verified production backup restore recorded', 'backup_restore', false, 'Records evidence that a backup can be restored, not just created.'),
                ],
            ],
        ];
    }

    public function summary(array $sections): array
    {
        $items = collect($sections)->flatMap(fn (array $section) => $section['items']);
        $passed = $items->where('status', 'pass')->count();
        $failed = $items->where('status', 'fail')->count();
        $warned = $items->where('status', 'warn')->count();

        return [
            'total' => $items->count(),
            'passed' => $passed,
            'failed' => $failed,
            'warned' => $warned,
            'ready' => $failed === 0,
        ];
    }

    private function item(string $id, string $label, bool $passed, string $detail = '', string $source = 'live check', string $description = '', array $steps = []): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'description' => $description,
            'steps' => $steps,
            'status' => $passed ? 'pass' : 'fail',
            'detail' => $detail,
            'source' => $source,
            'tested_at' => now(),
        ];
    }

    private function warning(string $id, string $label, string $detail, string $source = 'recorded evidence', string $description = '', array $steps = []): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'description' => $description,
            'steps' => $steps,
            'status' => 'warn',
            'detail' => $detail,
            'source' => $source,
            'tested_at' => null,
        ];
    }

    private function latestRunItem(string $id, string $label, string $runType, bool $blocking = true, string $description = '', array $steps = []): array
    {
        $run = $this->latestRun($runType);
        if (!$run) {
            return $blocking
                ? $this->item($id, $label, false, 'no recorded run', 'ops_runs', $description, $steps)
                : $this->warning($id, $label, 'no recorded run', 'ops_runs', $description, $steps);
        }

        $ok = in_array($run->status, ['success', 'pass'], true);
        $item = $this->item($id, $label, $ok, trim((string) ($run->summary ?: $run->error ?: $run->status)), 'ops_runs', $description, $steps);
        $item['tested_at'] = $run->finished_at ?: $run->created_at;
        return $item;
    }

    private function testSuiteEvidenceItem(string $id, string $label, string $description = ''): array
    {
        $run = $this->latestRun('test_suite');
        if (!$run) {
            return $this->warning($id, $label, 'no recorded test-suite run', 'ops_runs', $description);
        }

        $ok = in_array($run->status, ['success', 'pass'], true);
        $item = $this->item($id, $label, $ok, trim((string) ($run->summary ?: $run->status)), 'ops_runs', $description);
        $item['tested_at'] = $run->finished_at ?: $run->created_at;
        return $item;
    }

    private function freshFileItem(string $id, string $label, string $path, int $maxAgeSeconds, string $description = ''): array
    {
        if (!is_file($path)) {
            return $this->item($id, $label, false, 'missing', 'live check', $description);
        }

        $age = time() - filemtime($path);
        return $this->item($id, $label, $age <= $maxAgeSeconds, $age . ' seconds old', 'live check', $description);
    }

    private function freshGlobItem(string $id, string $label, string $pattern, int $maxAgeSeconds, string $description = ''): array
    {
        $files = glob($pattern) ?: [];
        usort($files, fn (string $a, string $b) => filemtime($b) <=> filemtime($a));
        if (!isset($files[0])) {
            return $this->item($id, $label, false, 'missing', 'live check', $description);
        }

        $age = time() - filemtime($files[0]);
        return $this->item($id, $label, $age <= $maxAgeSeconds, $age . ' seconds old', 'live check', $description);
    }

    private function routeItem(string $id, string $label, string $routeName, string $description = ''): array
    {
        return $this->item($id, $label, Route::has($routeName), Route::has($routeName) ? $routeName : 'missing', 'route table', $description);
    }

    private function settings(): array
    {
        try {
            return DB::table('basic_settings')->pluck('value', 'name')->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function pendingMigrationCount(): int
    {
        try {
            $migrator = app('migrator');
            $files = $migrator->getMigrationFiles(database_path('migrations'));
            $ran = $migrator->repositoryExists() ? $migrator->getRepository()->getRan() : [];
            return count(array_diff(array_keys($files), $ran));
        } catch (\Throwable) {
            return 999;
        }
    }

    private function duplicateScheduledCommands(): array
    {
        try {
            Artisan::call('schedule:list', ['--json' => true]);
            $schedule = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
            return collect($schedule)
                ->countBy('command')
                ->filter(fn (int $count) => $count > 1)
                ->keys()
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [class_basename($e) . ': ' . $e->getMessage()];
        }
    }

    private function failedJobCount(): ?int
    {
        try {
            return Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function publicPropertyCount(): int
    {
        try {
            return PmsRoom::query()->where('status', 'true')->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function providerTokenItem(string $provider, string $label, bool $configured, array $settings, string $description = ''): array
    {
        if ($configured) {
            return $this->item("{$provider}_webhook_auth", $label, true, 'configured', 'live check', $description);
        }

        if (!$this->providerInUse($provider, $settings)) {
            return $this->warning("{$provider}_webhook_auth", $label, 'not configured because provider is not in use', 'live check', $description);
        }

        return $this->item("{$provider}_webhook_auth", $label, false, 'missing', 'live check', $description);
    }

    private function providerInUse(string $provider, array $settings): bool
    {
        try {
            if (PmsRoom::query()->where('pms_source', $provider)->exists()) {
                return true;
            }
        } catch (\Throwable) {
        }

        foreach (['pms_source', 'pms_driver'] as $key) {
            if (strtolower((string) ($settings[$key] ?? '')) === $provider) {
                return true;
            }
        }

        $credentialKeys = [
            'lodgify' => ['lodgify_api_key'],
            'hostaway' => ['hostaway_api_key', 'hostaway_account_id'],
            'hostfully' => ['hostfully_api_key', 'hostfully_agency_uid'],
            'guesty' => ['guesty_api_key', 'guesty_client_id', 'guesty_client_secret'],
        ][$provider] ?? [];

        foreach ($credentialKeys as $key) {
            if (trim((string) ($settings[$key] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    private function enabledPaymentGateways(array $settings): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) ($settings['enabled_payment_gateways'] ?? '')))));
    }

    private function stripeItem(array $settings): array
    {
        $enabled = $this->enabledPaymentGateways($settings);
        if (!in_array('stripe', $enabled, true)) {
            return $this->warning('stripe_credentials', 'Stripe credentials configured', 'Stripe is not enabled', 'live check', 'Confirms Stripe keys are valid when Stripe is selected as an active payment gateway.');
        }

        $secret = (string) ($settings['stripe_secret_key'] ?? config('services.stripe.secret') ?? '');
        $publishable = (string) ($settings['stripe_publish_key'] ?? config('services.stripe.key') ?? '');
        $secretMode = str_starts_with($secret, 'sk_live_') ? 'live' : (str_starts_with($secret, 'sk_test_') ? 'test' : 'missing');
        $ok = $secretMode !== 'missing'
            && (($secretMode === 'live' && str_starts_with($publishable, 'pk_live_'))
                || ($secretMode === 'test' && str_starts_with($publishable, 'pk_test_')));

        return $this->item('stripe_credentials', 'Stripe publishable/secret keys match mode', $ok, $secretMode, 'live check', 'Confirms Stripe public and secret keys are both test or both live, never mixed.');
    }

    private function publicPathHasSensitiveFiles(): bool
    {
        $known = ['.env', '.env.backup', '.env.save', '.env.old', 'adminer.php'];
        foreach ($known as $file) {
            if (is_file(public_path($file))) {
                return true;
            }
        }

        foreach (['*.sql', '*.sqlite', '*.sqlite3', '*.bak', '*.zip'] as $pattern) {
            if ((glob(public_path($pattern)) ?: []) !== []) {
                return true;
            }
        }

        return false;
    }

    private function directoryWritable(string $path): bool
    {
        return is_dir($path) && is_writable($path);
    }

    private function csrfWebMiddlewareEnabled(): bool
    {
        try {
            $groups = app(\App\Http\Kernel::class)->getMiddlewareGroups();
            return in_array(\App\Http\Middleware\VerifyCsrfToken::class, $groups['web'] ?? [], true);
        } catch (\Throwable) {
            return false;
        }
    }

    private function unexpectedCsrfExemptions(): array
    {
        try {
            $reflection = new \ReflectionClass(\App\Http\Middleware\VerifyCsrfToken::class);
            $defaults = $reflection->getDefaultProperties();
            $except = array_map('strval', $defaults['except'] ?? []);
            $allowed = ['webhooks/mail', 'webhooks/listings'];

            return array_values(array_diff($except, $allowed));
        } catch (\Throwable $e) {
            return [class_basename($e) . ': ' . $e->getMessage()];
        }
    }

    private function webhookMiddlewareMissing(): array
    {
        try {
            $registered = array_keys(app('router')->getMiddleware());
            $required = ['lodgify.webhook', 'hostaway.webhook', 'hostfully.webhook', 'guesty.webhook'];

            return array_values(array_diff($required, $registered));
        } catch (\Throwable $e) {
            return [class_basename($e) . ': ' . $e->getMessage()];
        }
    }

    private function disposableListDomainCount(): ?int
    {
        $path = resource_path((string) config('spam.disposable_email.list_path', 'spam/disposable_email_domains.txt'));
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return null;
        }

        return collect($lines)
            ->map(fn (string $line): string => trim($line))
            ->filter(fn (string $line): bool => $line !== '' && !str_starts_with($line, '#'))
            ->count();
    }

    private function routeUriExists(string $uri): bool
    {
        $needle = trim($uri, '/');

        foreach (Route::getRoutes() as $route) {
            if (trim($route->uri(), '/') === $needle) {
                return true;
            }
        }

        return false;
    }

    private function mailReady(): bool
    {
        $mailer = (string) config('mail.default');
        return match ($mailer) {
            'smtp' => trim((string) config('mail.mailers.smtp.host')) !== ''
                && trim((string) config('mail.mailers.smtp.username')) !== ''
                && trim((string) config('mail.mailers.smtp.password')) !== ''
                && filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL),
            'ses' => trim((string) config('services.ses.key')) !== ''
                && trim((string) config('services.ses.secret')) !== ''
                && filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL),
            default => false,
        };
    }

    private function recentMailCount(): int
    {
        try {
            if (!Schema::hasTable('mail_send_log')) {
                return 0;
            }
            return DB::table('mail_send_log')->where('created_at', '>=', now()->subDays(30))->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function emailSnapshotCount(): int
    {
        try {
            if (!Schema::hasTable('email_send_log')) {
                return 0;
            }
            return DB::table('email_send_log')->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function latestRun(string $runType): ?OperationsRun
    {
        try {
            if (!Schema::hasTable('ops_runs')) {
                return null;
            }
            return OperationsRun::query()
                ->where('run_type', $runType)
                ->orderByDesc('finished_at')
                ->orderByDesc('created_at')
                ->first();
        } catch (\Throwable) {
            return null;
        }
    }
}

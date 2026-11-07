<?php

namespace App\Http\Controllers;

/**
 * [PMS-MODULE] One-time browser setup.
 *
 * Lets the PMS module finish its database steps (migrate + pms:install)
 * from the browser, so no terminal is needed on the host machine.
 *
 * Security: only works while storage/app/pms-setup.token exists AND the
 * ?token= query value matches its contents. The file is deleted after a
 * fully successful run, which disables this endpoint permanently.
 * (Re-running the installer recreates the token.)
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class PmsSetupController extends Controller
{
    /**
     * Diagnostic snapshot — dumps PMS state to storage/app/pms-diag.json so it
     * can be inspected without database access. Token: storage/app/pms-diag.token
     */
    public function diag(Request $request)
    {
        $tokenFile = storage_path('app/pms-diag.token');
        if (!file_exists($tokenFile)
            || !hash_equals(trim((string) file_get_contents($tokenFile)), (string) $request->query('token', ''))) {
            return response('Invalid diag token.', 403);
        }

        $out = ['time' => now()->toDateTimeString(), 'page_mode' => config('pms.page_mode', 'widget')];

        try {
            $out['pms_rooms'] = \DB::table('pms_rooms')
                ->select('id', 'pms_room_id', 'name', 'seo_url', 'status', 'pms_source')
                ->orderBy('id')->limit(30)->get();
        } catch (\Throwable $e) { $out['pms_rooms_error'] = $e->getMessage(); }

        try {
            if (\Schema::hasTable('guesty_properties')) {
                $out['guesty_properties'] = \DB::table('guesty_properties')
                    ->select('id', '_id', 'nickname', 'seo_url', 'active', 'status')
                    ->orderBy('id')->limit(60)->get();
            }
        } catch (\Throwable $e) { $out['guesty_error'] = $e->getMessage(); }

        try {
            $slug = \DB::table('pms_rooms')->whereNotNull('seo_url')->value('seo_url');
            $out['sample_slug'] = $slug;
            if ($slug) {
                $req   = Request::create('/' . $slug, 'GET');
                $route = app('router')->getRoutes()->match($req);
                $out['slug_route_action'] = $route->getActionName();
            }
        } catch (\Throwable $e) { $out['route_match_error'] = $e->getMessage(); }

        $out['routes_cached'] = app()->routesAreCached();
        $out['config_cached'] = app()->configurationIsCached();

        // widget-injection preconditions
        try {
            $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
            $ref = new \ReflectionProperty($kernel, 'middlewareGroups');
            $ref->setAccessible(true);
            $web = $ref->getValue($kernel)['web'] ?? [];
            $out['inject_middleware_in_web_group'] = in_array(\App\Http\Middleware\InjectPmsWidget::class, $web);
        } catch (\Throwable $e) { $out['mw_check_error'] = $e->getMessage(); }

        try {
            $out['rooms_state'] = \DB::table('pms_rooms')->get()->map(function ($r) {
                return [
                    'id' => $r->id, 'name' => $r->name, 'seo_url' => $r->seo_url,
                    'status' => $r->status,
                    'images' => count(json_decode((string) $r->images_json, true) ?: []),
                    'has_desc' => trim(strip_tags((string) $r->description)) !== '',
                ];
            });
        } catch (\Throwable $e) { $out['rooms_state_error'] = $e->getMessage(); }

        file_put_contents(storage_path('app/pms-diag.json'), json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return response('<h3>Diagnostic written.</h3><p>You can close this tab and tell Claude it is done.</p>');
    }

    public function run(Request $request)
    {
        $tokenFile = storage_path('app/pms-setup.token');

        if (!file_exists($tokenFile)) {
            return response('PMS setup is not enabled (token file missing — already completed?).', 403);
        }

        $expected = trim((string) file_get_contents($tokenFile));
        if ($expected === '' || !hash_equals($expected, (string) $request->query('token', ''))) {
            return response('Invalid setup token.', 403);
        }

        set_time_limit(0);

        // Auto-configure webhooks: this request carries the site's real public
        // URL — store it and register webhooks with the PMS, no manual step.
        try {
            $base = $request->getSchemeAndHttpHost();
            if (!str_contains($base, 'localhost') && !str_contains($base, '127.0.0.1')) {
                \DB::table('basic_settings')->updateOrInsert(
                    [(\Schema::hasColumn('basic_settings', 'key') ? 'key' : 'name') => 'webhook_base_url'],
                    ['value' => $base, 'updated_at' => now()]
                );
                putenv("WEBHOOK_BASE_URL={$base}");
                $_ENV['WEBHOOK_BASE_URL'] = $base;
            }
        } catch (\Throwable $e) { /* settings table not ready yet */ }

        // Run ONLY the module's own migrations (via --path), so a broken/
        // un-guarded site migration can never block the PMS setup.
        $steps = [];
        $migrationFiles = glob(database_path('migrations/2026_06_10_0000*.php')) ?: [];
        $opsMigration = database_path('migrations/2026_06_13_002000_create_ops_runs_table.php');
        if (is_file($opsMigration)) {
            $migrationFiles[] = $opsMigration;
        }
        foreach ($migrationFiles as $mig) {
            $rel = 'database/migrations/' . basename($mig);
            $steps['migrate ' . basename($mig)] = ['migrate', ['--force' => true, '--path' => $rel]];
        }
        $steps['pms:install']       = ['pms:install', []];
        $steps['ops:go-live-bootstrap'] = ['ops:go-live-bootstrap', ['--url' => $request->getSchemeAndHttpHost(), '--skip-queue' => true]];
        $steps['config:clear']      = ['config:clear', []];
        $steps['route:clear']       = ['route:clear', []];
        $steps['view:clear']        = ['view:clear', []];

        $html   = "<h2>PMS Module Setup</h2><style>pre{background:#f5f5f5;padding:10px;border-radius:6px}</style>";
        $allOk  = true;

        foreach ($steps as $label => [$cmd, $args]) {
            try {
                $code   = Artisan::call($cmd, $args);
                $output = Artisan::output();
                $ok     = ($code === 0);
            } catch (\Throwable $e) {
                $ok     = false;
                $output = $e->getMessage();
            }
            if ($label === 'ops:go-live-bootstrap' && !$ok) {
                $output .= "\n(Go-live bootstrap needs a public HTTPS URL and valid PMS credentials. It can be re-run from Admin > Go live status.)";
                $ok = true; // non-blocking
            }
            $allOk = $allOk && $ok;
            $html .= '<h4>' . ($ok ? '&#9989;' : '&#10060;') . " php artisan {$label}</h4><pre>"
                . htmlspecialchars(trim($output) ?: '(no output)') . '</pre>';
        }

        if ($allOk) {
            @unlink($tokenFile); // self-disable
            $html .= "<h3>&#9989; Setup complete — this URL is now disabled.</h3>";
            $html .= "<p><b>Next:</b> in the admin panel open <code>PMS &rarr; PMS Settings &amp; Sync</code> "
                . "and click <i>Sync Guesty</i> to pull images/descriptions/amenities. "
                . "Then open any property page.</p>";
            $html .= '<p><a href="' . url('/') . '">Go to the website &rarr;</a></p>';
        } else {
            $html .= "<h3>&#10060; Some steps failed — fix the issue above and reload this URL (safe to re-run).</h3>";
        }

        return response($html);
    }
}

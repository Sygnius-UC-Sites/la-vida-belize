<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [PMS-MODULE] pms:check — pre-flight report.
 *
 * Run BEFORE/AFTER install to surface the three things that bite legacy sites:
 *  1. which PMS provider this site is (so nobody assumes Guesty),
 *  2. site migrations that create tables WITHOUT a hasTable() guard
 *     (these break a global `migrate`),
 *  3. hardcoded legacy model references still left in views/controllers.
 */
class PmsCheck extends Command
{
    protected $signature = 'pms:check';
    protected $description = 'Pre-flight checks for installing/operating the PMS module on this site';

    public function handle(): int
    {
        $this->info('PMS module pre-flight check');

        // 1) provider
        $provider = '';
        foreach (['pms_source', 'pms_driver'] as $k) {
            $v = strtolower((string) optional(DB::table('basic_settings')
                ->where(Schema::hasColumn('basic_settings', 'key') ? 'key' : 'name', $k)->first())->value);
            if (in_array($v, ['guesty', 'lodgify', 'hostaway', 'hostfully'], true)) { $provider = $v; break; }
        }
        if (!$provider && Schema::hasTable('pms_rooms')) {
            $row = DB::table('pms_rooms')->whereNotNull('pms_source')->where('pms_source', '!=', '')
                ->groupBy('pms_source')->orderByRaw('COUNT(*) DESC')->first();
            if ($row) { $provider = $row->pms_source; }
        }
        $this->line('  Provider : ' . ($provider ?: 'undetermined (set credentials, then pms:sync-all)'));

        // 2) unguarded site migrations
        $bad = [];
        $dir = database_path('migrations');
        if (is_dir($dir)) {
            foreach (glob("$dir/*.php") as $m) {
                if (str_contains(basename($m), '2026_06_10_0000')) continue; // ours are guarded
                $src = file_get_contents($m);
                if (preg_match('/Schema::create\(/', $src) && !preg_match('/hasTable\(/', $src)) {
                    $bad[] = basename($m);
                }
            }
        }
        if ($bad) {
            $this->warn('  Unguarded create-table migrations (may clash on global migrate):');
            foreach ($bad as $b) $this->warn('    - ' . $b);
            $this->line('    The installer runs ONLY module migrations via --path, so these will not block it,');
            $this->line('    but consider adding Schema::hasTable() guards to them.');
        } else {
            $this->line('  Migrations: no unguarded create-table site migrations found.');
        }

        // 3) hardcoded legacy models in views/controllers
        $legacy = ['App\\Models\\Lodgify\\', 'App\\Models\\Hostaway\\', 'App\\Models\\Hostfully\\'];
        $hitFiles = [];
        foreach (['resources/views', 'app/Http/Controllers'] as $d) {
            $abs = base_path($d);
            if (!is_dir($abs)) continue;
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($abs, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                $p = $f->getPathname();
                if (!preg_match('/\.php$/', $p) || str_contains($p, 'pms-') || str_contains($p, '/Pms/')) continue;
                $src = file_get_contents($p);
                foreach ($legacy as $ns) {
                    if (strpos($src, $ns) !== false) { $hitFiles[] = str_replace(base_path() . '/', '', $p); break; }
                }
            }
        }
        if ($hitFiles) {
            $this->warn('  Hardcoded legacy model references found in:');
            foreach (array_unique($hitFiles) as $h) $this->warn('    - ' . $h);
            $this->line('    Fix automatically with:  php artisan pms:upgrade-views');
        } else {
            $this->line('  Views/controllers: no hardcoded legacy model references.');
        }

        $this->info('Pre-flight complete.');
        return 0;
    }
}

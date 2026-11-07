<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * [PMS-MODULE] pms:upgrade-views
 *
 * Legacy sites hardcode the OLD property models directly in blade views and
 * controllers, e.g.:
 *     App\Models\Lodgify\LodgifyRoom::where('status','true')->first();
 * After the module takes over, those old tables are no longer the source of
 * truth and such queries return null → "Attempt to read property on null".
 *
 * This command rewrites those references to the module's unified models so a
 * legacy site becomes plug-and-play without manual refactoring.
 *
 *   php artisan pms:upgrade-views            # apply
 *   php artisan pms:upgrade-views --dry-run  # show what would change
 *
 * Safe: only rewrites known legacy class references; writes a .pms-bak backup
 * of every file it changes.
 */
class PmsUpgradeViews extends Command
{
    protected $signature = 'pms:upgrade-views {--dry-run} {--path=* : extra dirs to scan, relative to base_path}';
    protected $description = 'Rewrite hardcoded legacy PMS model references to App\\Models\\Pms equivalents';

    /** old FQCN (with/without leading slash) => new FQCN */
    private array $map = [
        'App\\Models\\Lodgify\\LodgifyRoom'     => 'App\\Models\\Pms\\PmsRoom',
        'App\\Models\\Lodgify\\LodgifyProperty' => 'App\\Models\\Pms\\PmsProperty',
        'App\\Models\\Lodgify\\LodgifyBooking'  => 'App\\Models\\Pms\\PmsBooking',
        'App\\Models\\Hostaway\\HostawayRoom'   => 'App\\Models\\Pms\\PmsRoom',
        'App\\Models\\Hostfully\\HostfullyRoom' => 'App\\Models\\Pms\\PmsRoom',
    ];

    public function handle(): int
    {
        $dry  = $this->option('dry-run');
        $dirs = array_merge(['resources/views', 'app/Http/Controllers'], (array) $this->option('path'));

        $changed = 0;
        $hits    = 0;
        foreach ($dirs as $dir) {
            $abs = base_path($dir);
            if (!is_dir($abs)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($abs, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                $path = $file->getPathname();
                if (!preg_match('/\.(php|blade\.php)$/', $path)) {
                    continue;
                }
                // never touch module-owned files
                if (str_contains($path, '/Pms/') || str_contains($path, 'pms-') || str_contains($path, 'single-pms')) {
                    continue;
                }
                $src = file_get_contents($path);
                $new = $src;
                foreach ($this->map as $old => $newClass) {
                    foreach (["\\{$old}", $old] as $needle) {
                        if (strpos($new, $needle) !== false) {
                            $new = str_replace($needle, ($needle[0] === '\\' ? '\\' : '') . $newClass, $new);
                        }
                    }
                }
                if ($new !== $src) {
                    $hits++;
                    $rel = str_replace(base_path() . '/', '', $path);
                    $this->line(($dry ? '[would patch] ' : '[patched] ') . $rel);
                    if (!$dry) {
                        @copy($path, $path . '.pms-bak');
                        file_put_contents($path, $new);
                    }
                    $changed++;
                }
            }
        }

        $this->info(($dry ? '[DRY-RUN] ' : '') . "Legacy model references rewritten in {$changed} file(s).");
        if ($changed === 0) {
            $this->info('  Nothing to change — no legacy model references found.');
        }
        return 0;
    }
}

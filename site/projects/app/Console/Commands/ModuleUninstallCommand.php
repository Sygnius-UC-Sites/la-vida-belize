<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Uninstall a module: rollback its migrations, remove its folder, remove
 * its provider from config/app.php, update modules.lock.
 *
 *   php artisan module:uninstall <name>
 *
 * ⚠ Destructive. Migrations are rolled back which drops the module's tables.
 */
class ModuleUninstallCommand extends Command
{
    protected $signature = 'module:uninstall
        {name : Module name}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Uninstall a module — drops its tables and removes its code.';

    public function handle(): int
    {
        $name = $this->argument('name');
        $lockPath = base_path('modules.lock');
        if (!file_exists($lockPath)) {
            $this->error('No modules.lock found. Nothing installed.');
            return self::FAILURE;
        }
        $lock = json_decode(file_get_contents($lockPath), true);
        if (empty($lock['installed'][$name])) {
            $this->error("Module '{$name}' is not installed.");
            return self::FAILURE;
        }
        $entry = $lock['installed'][$name];
        $namespace = $entry['namespace'] ?? null;
        $providerClass = $entry['provider'] ?? null;

        if (!$this->option('force')) {
            if (!$this->confirm("This will drop all of {$name}'s tables and delete its code. Continue?")) {
                return self::FAILURE;
            }
        }

        // Rollback migrations from the module folder.
        $folder = $namespace ? str_replace('App\\Modules\\', '', $namespace) : null;
        $folder = $folder ? str_replace('\\', DIRECTORY_SEPARATOR, $folder) : null;
        $modulePath = $folder ? app_path('Modules' . DIRECTORY_SEPARATOR . $folder) : null;
        $migrationsPath = $modulePath ? $modulePath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations' : null;
        if ($migrationsPath && is_dir($migrationsPath)) {
            $this->info("Rolling back migrations from {$migrationsPath}...");
            $this->call('migrate:rollback', [
                '--path' => str_replace(base_path() . DIRECTORY_SEPARATOR, '', $migrationsPath),
                '--force' => true,
            ]);
        }

        // Remove folder.
        if ($modulePath && is_dir($modulePath)) {
            $this->info("Removing {$modulePath}...");
            $this->rmDir($modulePath);
        }

        // Unregister provider from config/app.php.
        if ($providerClass) {
            $configFile = config_path('app.php');
            $contents = file_get_contents($configFile);
            $pattern = '/^\s*' . preg_quote($providerClass, '/') . '::class,[^\n]*\n/m';
            $contents = preg_replace($pattern, '', $contents);
            file_put_contents($configFile, $contents);
        }

        // Update lockfile.
        unset($lock['installed'][$name]);
        $lock['updatedAt'] = now()->toIso8601String();
        file_put_contents($lockPath, json_encode($lock, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

        $this->info("✓ {$name} uninstalled.");
        return self::SUCCESS;
    }

    private function rmDir(string $path): void
    {
        if (!is_dir($path)) return;
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
        }
        rmdir($path);
    }
}

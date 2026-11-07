<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Install a module from the pms-modules repo into this PMS instance.
 *
 *   php artisan module:install <name>
 *   php artisan module:install <name> --version=1.2.0
 *   php artisan module:install <name> --upgrade           (re-copy + run new migrations)
 *   php artisan module:install <name> --source=/abs/path  (install from a local path
 *                                                          instead of fetching from
 *                                                          the pms-modules repo)
 *
 * The default source is the path or URL configured in config/modules.php (key:
 * `modules.source`). If that points at a git URL (https://github.com/...),
 * the command shallow-clones the repo into storage/app/modules-cache first
 * and copies from there.
 *
 * After copying the module folder into app/Modules/<Name>/, the command:
 *   1. Appends the module's ServiceProvider to config/app.php under 'providers'.
 *   2. Runs `php artisan migrate` to apply the module's migrations.
 *   3. Updates modules.lock at the project root.
 */
class ModuleInstallCommand extends Command
{
    protected $signature = 'module:install
        {name : Module name (folder name in pms-modules)}
        {--version= : Specific version to install. Defaults to the latest in pms-modules.}
        {--upgrade : Re-copy the module overwriting existing files. Existing data preserved.}
        {--source= : Local path or git URL to install from. Overrides config(modules.source).}';

    protected $description = 'Install a module from the pms-modules repo into this PMS instance.';

    public function handle(): int
    {
        $name = $this->argument('name');
        if (!preg_match('/^[a-z][a-z0-9-]*$/', $name)) {
            $this->error("Invalid module name '{$name}'. Use lowercase-dashed.");
            return self::INVALID;
        }

        // Resolve source.
        $source = $this->option('source') ?: config('modules.source', getenv('PMS_MODULES_SOURCE'));
        if (!$source) {
            $this->error('No module source configured. Set config/modules.php → source, or pass --source.');
            return self::FAILURE;
        }

        // Get a local path to the module folder (cloning if source is a git URL).
        $modulePath = $this->resolveModuleSource($source, $name);
        if (!$modulePath || !is_dir($modulePath)) {
            $this->error("Module '{$name}' not found at source '{$source}'.");
            return self::FAILURE;
        }

        // Read module.json.
        $manifestFile = $modulePath . DIRECTORY_SEPARATOR . 'module.json';
        if (!file_exists($manifestFile)) {
            $this->error("Module '{$name}' is missing module.json.");
            return self::FAILURE;
        }
        $manifest = json_decode(file_get_contents($manifestFile), true);
        if (!$manifest) {
            $this->error("module.json for '{$name}' is invalid JSON.");
            return self::FAILURE;
        }

        $version = $this->option('version') ?: ($manifest['version'] ?? '0.0.0');
        $namespace = $manifest['namespace'] ?? null;
        $providerClass = $manifest['provider'] ?? null;
        if (!$namespace || !$providerClass) {
            $this->error("module.json for '{$name}' is missing required fields 'namespace' or 'provider'.");
            return self::FAILURE;
        }

        // Compute destination.
        $folderName = $this->namespaceToFolder($namespace);
        $destPath = app_path('Modules' . DIRECTORY_SEPARATOR . $folderName);
        $alreadyInstalled = is_dir($destPath);
        if ($alreadyInstalled && !$this->option('upgrade')) {
            $this->error("Module '{$name}' is already installed at app/Modules/{$folderName}. Use --upgrade to update.");
            return self::FAILURE;
        }

        // Copy.
        $this->info(($alreadyInstalled ? 'Upgrading' : 'Installing') . " {$name} v{$version}...");
        $this->copyDir($modulePath, $destPath);

        // Register provider in config/app.php.
        $this->registerProvider($providerClass);

        // Migrate.
        $this->info('Running migrations...');
        $exit = $this->call('migrate', ['--force' => true]);
        if ($exit !== 0) {
            $this->error('Migration failed. Module is installed but migrations did not run cleanly.');
        }

        // Update modules.lock.
        $this->updateLockfile($name, [
            'version' => $version,
            'namespace' => $namespace,
            'provider' => $providerClass,
            'installedAt' => now()->toIso8601String(),
            'installedBy' => getenv('USER') ?: 'unknown',
            'upgrade' => $alreadyInstalled,
        ]);

        $this->info("✓ {$name} v{$version} installed.");
        $this->line('');
        $this->line('Next steps:');
        $this->line("  - Visit /admin/{$name} to confirm the admin UI loads.");
        $this->line("  - Run `php artisan module:list` to see all installed modules.");
        return self::SUCCESS;
    }

    /**
     * If source looks like a git URL, shallow-clone it to a cache dir.
     * Otherwise treat as a local path. Return the absolute path to the
     * module's folder (which is <source>/<name>).
     */
    private function resolveModuleSource(string $source, string $name): ?string
    {
        if (preg_match('#^https?://#', $source) || str_ends_with($source, '.git')) {
            $cache = storage_path('app/modules-cache');
            if (!is_dir($cache)) mkdir($cache, 0755, true);
            $repoDir = $cache . DIRECTORY_SEPARATOR . 'pms-modules';
            if (!is_dir($repoDir)) {
                $this->info("Cloning {$source}...");
                $cmd = sprintf('git clone --depth 1 %s %s 2>&1', escapeshellarg($source), escapeshellarg($repoDir));
                exec($cmd, $out, $code);
                if ($code !== 0) {
                    $this->error('git clone failed: ' . implode("\n", $out));
                    return null;
                }
            } else {
                exec(sprintf('cd %s && git pull --quiet 2>&1', escapeshellarg($repoDir)), $_, $_);
            }
            return $repoDir . DIRECTORY_SEPARATOR . $name;
        }
        // Local path.
        return rtrim($source, '/\\') . DIRECTORY_SEPARATOR . $name;
    }

    private function namespaceToFolder(string $namespace): string
    {
        $relative = str_replace('App\\Modules\\', '', $namespace);
        return str_replace('\\', DIRECTORY_SEPARATOR, $relative);
    }

    private function copyDir(string $from, string $to): void
    {
        if (!is_dir($to)) mkdir($to, 0755, true);
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($from, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($items as $item) {
            $target = $to . DIRECTORY_SEPARATOR . $items->getSubPathname();
            if ($item->isDir()) {
                if (!is_dir($target)) mkdir($target, 0755, true);
            } else {
                copy($item->getRealPath(), $target);
            }
        }
    }

    /**
     * Append the module's ServiceProvider FQCN to config/app.php's providers
     * array if not already present. Idempotent.
     */
    private function registerProvider(string $fqcn): void
    {
        $configFile = config_path('app.php');
        $contents = file_get_contents($configFile);
        if (str_contains($contents, $fqcn . '::class')) {
            return;
        }
        $marker = '/* ModuleAutoRegister */';
        $entry = "        {$fqcn}::class, {$marker}\n";
        if (str_contains($contents, $marker)) {
            // Insert before the marker
            $contents = preg_replace(
                '/(\s*)' . preg_quote($marker, '/') . '/',
                "\n{$entry}$1{$marker}",
                $contents,
                1
            );
        } else {
            // Insert before the closing of the providers array.
            $contents = preg_replace(
                "/('providers'\s*=>\s*ServiceProvider::defaultProviders\(\)->merge\(\[)/",
                "$1\n{$entry}",
                $contents,
                1
            );
            // Fallback for non-Laravel-11 style providers array
            if (strpos($contents, $fqcn) === false) {
                $contents = preg_replace(
                    "/('providers'\s*=>\s*\[)/",
                    "$1\n{$entry}",
                    $contents,
                    1
                );
            }
        }
        file_put_contents($configFile, $contents);
    }

    /**
     * Maintain modules.lock at the project root.
     */
    private function updateLockfile(string $name, array $entry): void
    {
        $path = base_path('modules.lock');
        $lock = file_exists($path) ? json_decode(file_get_contents($path), true) : null;
        if (!is_array($lock)) {
            $lock = ['lockfileVersion' => 1, 'installed' => []];
        }
        $lock['installed'][$name] = $entry;
        $lock['updatedAt'] = now()->toIso8601String();
        file_put_contents($path, json_encode($lock, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }
}

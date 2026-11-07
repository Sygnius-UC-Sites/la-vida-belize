<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * List modules installed in this PMS instance.
 *
 *   php artisan module:list
 */
class ModuleListCommand extends Command
{
    protected $signature = 'module:list {--json : Output as JSON for scripting}';
    protected $description = 'List modules installed in this PMS instance.';

    public function handle(): int
    {
        $path = base_path('modules.lock');
        if (!file_exists($path)) {
            if ($this->option('json')) $this->line('{"installed": {}}');
            else $this->info('No modules installed yet.');
            return self::SUCCESS;
        }
        $lock = json_decode(file_get_contents($path), true);
        if (!is_array($lock) || empty($lock['installed'])) {
            if ($this->option('json')) $this->line(json_encode(['installed' => (object)[]]));
            else $this->info('No modules installed yet.');
            return self::SUCCESS;
        }
        if ($this->option('json')) {
            $this->line(json_encode($lock, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }
        $rows = [];
        foreach ($lock['installed'] as $name => $info) {
            $rows[] = [
                $name,
                $info['version'] ?? '?',
                $info['installedAt'] ?? '?',
                $info['installedBy'] ?? '?',
            ];
        }
        $this->table(['Module', 'Version', 'Installed', 'By'], $rows);
        return self::SUCCESS;
    }
}

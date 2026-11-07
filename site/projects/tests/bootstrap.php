<?php

require __DIR__ . '/../vendor/autoload.php';

$database = sys_get_temp_dir() . '/base-crm-phpunit-' . getmypid() . '.sqlite';
if (file_exists($database)) {
    unlink($database);
}
if (touch($database) === false) {
    throw new RuntimeException("Unable to create isolated test database: {$database}");
}

foreach ([
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => $database,
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'false',
    'DEBUGBAR_ENABLED' => 'false',
] as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

register_shutdown_function(static function () use ($database): void {
    if (file_exists($database)) {
        @unlink($database);
    }
});

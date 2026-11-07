<?php
/**
 * Module system configuration.
 *
 * `source` is where `php artisan module:install <name>` fetches modules from.
 *
 * Three valid forms:
 *   - HTTPS git URL: `https://github.com/sygnius-digital/pms-modules.git` (default for prod)
 *   - SSH git URL:   `git@github.com:sygnius-digital/pms-modules.git` (if your environment has the right SSH key)
 *   - Local path:    `/Users/you/code/pms-modules` (useful during development)
 *
 * Override with the PMS_MODULES_SOURCE env var.
 */

return [
    'source' => env('PMS_MODULES_SOURCE', 'https://github.com/sygnius-digital/pms-modules.git'),
];

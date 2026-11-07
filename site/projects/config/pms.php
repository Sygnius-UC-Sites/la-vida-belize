<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default PMS Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default PMS (Property Management System) driver
    | that will be used by the application.
    |
    | Supported: "lodgify", "hostaway", "guesty", "hostfully", "none" (or "crm")
    |
    */
    'driver' => env('PMS_DRIVER', 'lodgify'),

    /*
    |--------------------------------------------------------------------------
    | Property Page Mode
    |--------------------------------------------------------------------------
    |
    | 'widget' (default): the site's own property page is served untouched;
    |          the module only swaps the date picker's availability data,
    |          shows a live PMS quote below the booking widget, and routes
    |          the booking through the PMS funnel. Zero CSS changes.
    |
    | 'full':  PMS rooms render the unified single-pms page.
    |
    */
    'page_mode' => env('PMS_PAGE_MODE', 'widget'),

    // bump to force browsers to re-fetch the module's JS assets
    'widget_version' => env('PMS_WIDGET_VERSION', '7'),

    // Module registry (telemetry + version tracking + remote feature state).
    // Defaults are also hardcoded in PmsServiceProvider so the module stays in
    // sync even if these are absent. Override only for staging.
    'registry_url'          => env('PMS_REGISTRY_URL', 'https://connect.sygniusdigital.com/api/mod-sync/ping'),
    'registry_check_url'    => env('PMS_REGISTRY_STATE', 'https://connect.sygniusdigital.com/api/mod-sync/state'),
    'registry_recheck_hours'=> (int) env('PMS_REGISTRY_RECHECK_HOURS', 12),
    'version'               => '2.0.0',


    /*
    |--------------------------------------------------------------------------
    | Default Pet Limit
    |--------------------------------------------------------------------------
    |
    | Used only when the PMS explicitly allows pets but does not provide a
    | property-level maximum. A real PMS maximum always takes precedence.
    |
    */
    'default_max_pets' => (int) env('PMS_DEFAULT_MAX_PETS', 2),
];

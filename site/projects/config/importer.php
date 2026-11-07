<?php

/**
 * Defaults for the Apify scraping module. Admin-overridable values live in
 * basic_settings (keys prefixed `apify_*`); env values seed fresh installs.
 *
 * One Apify account is shared across all client installs (same API token).
 * Each install has its own list of source URLs and its own properties table.
 */
return [
    // Generic env names take precedence; legacy APIFY_* names read as fallback.
    // Actual vendor: Apify (see docs/INTERNAL-VENDORS.md).
    'enabled'   => env('IMPORTER_ENABLED', env('APIFY_ENABLED', true)),
    'api_token' => env('IMPORTER_TOKEN', env('APIFY_API_TOKEN')),
    'api_base'  => env('IMPORTER_API_BASE', env('APIFY_API_BASE', 'https://api.apify.com/v2')),

    // Default Apify Actor IDs per platform. Each Actor returns a different
    // JSON schema; the matching mapper class below handles its shape.
    'actors' => [
        // Each platform uses two upstream Actors: one for listing data, one for reviews.
        'airbnb'         => env('IMPORTER_ACTOR_AIRBNB',         env('APIFY_ACTOR_AIRBNB',         'tri_angle~airbnb-rooms-urls-scraper')),
        'airbnb_reviews' => env('IMPORTER_ACTOR_AIRBNB_REVIEWS', env('APIFY_ACTOR_AIRBNB_REVIEWS', 'tri_angle~airbnb-reviews-scraper')),
    ],

    // Map platform → Mapper FQCN. Keep this list and `actors` in lockstep.
    'mappers' => [
        'airbnb' => \App\Services\Scraping\Mappers\AirbnbMapper::class,
    ],

    // Per-install rate cap so a runaway loop can't drain monthly Apify credit.
    'rate_limit' => [
        'max_runs_per_minute' => env('IMPORTER_MAX_RUNS_PER_MINUTE', env('APIFY_MAX_RUNS_PER_MINUTE', 10)),
    ],

    'max_reviews' => env('IMPORTER_MAX_REVIEWS', env('APIFY_MAX_REVIEWS', 0)),

    // Webhook signature verification (Apify signs payloads with HMAC-SHA256
    // using a per-webhook secret you set when registering the webhook).
    'webhook_secret' => env('IMPORTER_WEBHOOK_SECRET', env('APIFY_WEBHOOK_SECRET')),

    // Where to store downloaded listing images. Resolved relative to the
    // public path (htdocs root on this hoisted layout — see CLAUDE.md).
    'image_storage_path' => 'uploads/listings',

    'log_channel' => 'importer',
];

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // ── PMS Adapters ────────────────────────────────────────────────────────
    'guesty' => [
        'client_id'     => env('GUESTY_CLIENT_ID'),
        'api_key'       => env('GUESTY_API_KEY'),   // used as client_secret
        'webhook_token' => env('GUESTY_WEBHOOK_TOKEN'),
        'webhook_secret' => env('GUESTY_WEBHOOK_SECRET'),
    ],

    'hostaway' => [
        'account_id'    => env('HOSTAWAY_ACCOUNT_ID'),
        'api_key'       => env('HOSTAWAY_API_KEY'),
        'webhook_token' => env('HOSTAWAY_WEBHOOK_TOKEN'),
    ],

    'hostfully' => [
        'api_key'       => env('HOSTFULLY_API_KEY'),
        'agency_uid'    => env('HOSTFULLY_AGENCY_UID'),
        'webhook_token' => env('HOSTFULLY_WEBHOOK_TOKEN'),
    ],

    'lodgify' => [
        'api_key'       => env('LODGIFY_API_KEY'),
        'webhook_token' => env('LODGIFY_WEBHOOK_TOKEN'),
    ],

    // ── Stripe ───────────────────────────────────────────────────────────────
    'stripe' => [
        'key'            => env('STRIPE_KEY'),
        'secret'         => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'webhooks' => [
        'base_url' => env('WEBHOOK_BASE_URL'),
    ],

];

<?php

return [

    // Bot-challenge widget (vendor: Cloudflare Turnstile — see docs/INTERNAL-VENDORS.md).
    // Generic env names preferred; legacy TURNSTILE_* names read as fallback.
    'turnstile' => [
        'enabled'    => env('SPAM_BOTGUARD_ENABLED', env('SPAM_TURNSTILE_ENABLED', false)),
        'site_key'   => env('BOTGUARD_SITE_KEY',   env('TURNSTILE_SITE_KEY')),
        'secret_key' => env('BOTGUARD_SECRET_KEY', env('TURNSTILE_SECRET_KEY')),
        'verify_url' => env('BOTGUARD_VERIFY_URL', env('TURNSTILE_VERIFY_URL', 'https://challenges.cloudflare.com/turnstile/v0/siteverify')),
        'timeout'    => 5,
    ],

    'honeypot' => [
        'enabled'    => env('SPAM_HONEYPOT_ENABLED', true),
        'field_name' => 'website',
    ],

    'time_trap' => [
        'enabled'      => env('SPAM_TIME_TRAP_ENABLED', true),
        'min_seconds'  => env('SPAM_TIME_TRAP_MIN_SECONDS', 2),
        'max_seconds'  => env('SPAM_TIME_TRAP_MAX_SECONDS', 21600),
        'field_name'   => '_form_ts',
    ],

    'disposable_email' => [
        'enabled'   => env('SPAM_DISPOSABLE_EMAIL_ENABLED', true),
        'list_path' => 'spam/disposable_email_domains.txt',
    ],

    'rate_limit' => [
        'enabled' => env('SPAM_RATE_LIMIT_ENABLED', true),
    ],

    'forms' => [
        'contact' => [
            'rate_limit_key' => 'spam:contact',
            'rate_limit_max' => 5,
            'email_field'    => 'email',
        ],
        'booking' => [
            'rate_limit_key' => 'spam:booking',
            'rate_limit_max' => 5,
            'email_field'    => 'email',
        ],
        'review' => [
            'rate_limit_key' => 'spam:review',
            'rate_limit_max' => 3,
            'email_field'    => null,
        ],
    ],

    'log_channel' => 'spam',
];

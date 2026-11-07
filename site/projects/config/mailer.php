<?php

/**
 * Defaults for the SES mail module. All values can be overridden by admin in
 * basic_settings (keys prefixed `mail_ses_*`); env vars are read as the seed
 * values for fresh installs.
 */
return [
    'enabled' => env('MAIL_SES_ENABLED', true),
    'validate_recipient_dns' => env('MAIL_VALIDATE_RECIPIENT_DNS', true),

    // Per-install identity. The admin sets these on /admin/settings/mail.
    'from_email'        => env('MAIL_SES_FROM_EMAIL'),
    'from_name'         => env('MAIL_SES_FROM_NAME'),
    'reply_to'          => env('MAIL_SES_REPLY_TO'),
    'domain'            => env('MAIL_SES_DOMAIN'),
    'configuration_set' => env('MAIL_SES_CONFIGURATION_SET'),

    // Shared AWS credentials (single account across all installs). Admin can
    // also enter these in the UI; UI values take precedence over env.
    // Generic env names take precedence; legacy AWS_* names are read as a
    // fallback so existing installs don't break when upgrading. See
    // docs/INTERNAL-VENDORS.md for the actual vendor (Amazon SES).
    'aws' => [
        'access_key_id'     => env('MAILER_KEY_ID')     ?: env('AWS_ACCESS_KEY_ID'),
        'secret_access_key' => env('MAILER_SECRET')     ?: env('AWS_SECRET_ACCESS_KEY'),
        'region'            => env('MAILER_REGION')     ?: env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Outbound rate limit per install. Keyed in RateLimiter as `mail:send`.
    'rate_limit' => [
        'max_per_minute' => env('MAIL_SES_MAX_PER_MINUTE', 30),
    ],

    // Auto-disable thresholds — tighter than SES's own (5% / 0.1%) so this
    // install pauses before AWS notices.
    'health' => [
        'window_minutes'         => 1440, // 24h
        'min_sample_size'        => 50,   // need this many sends before thresholds kick in
        'bounce_rate_threshold'  => 0.03, // 3%
        'complaint_rate_threshold' => 0.0005, // 0.05%
    ],

    // SNS endpoint signature verification. AWS rotates the cert; we cache it.
    'sns' => [
        'verify_signatures' => env('MAIL_SES_SNS_VERIFY', true),
        'allowed_topic_arn_prefix' => env('MAIL_SES_SNS_TOPIC_PREFIX'), // e.g. arn:aws:sns:us-east-1:1234:yourbrand-
    ],

    'log_channel' => 'mail',
];

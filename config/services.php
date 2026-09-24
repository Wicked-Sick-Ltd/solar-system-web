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

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Solar System DB backend
    |--------------------------------------------------------------------------
    |
    | The read-only REST API this front-end consumes. Nothing about the backend
    | is hard-coded anywhere else — everything keys off these values. Cache TTLs
    | (seconds) reflect that the backend refreshes nightly: long-lived reference
    | data can be cached for a day, catalogue listings for hours, and live
    | positional data for only a couple of minutes.
    |
    */

    'solar' => [
        'base_url' => rtrim((string) env('API_BASE_URL', 'http://127.0.0.1:8003/api/v1'), '/'),
        'timeout' => (int) env('SOLAR_API_TIMEOUT', 8),

        'cache' => [
            // Reference data that barely changes between nightly builds.
            'reference' => (int) env('SOLAR_CACHE_REFERENCE', 86400),   // 24h
            // Catalogue listings and object detail.
            'catalog' => (int) env('SOLAR_CACHE_CATALOG', 21600),       // 6h
            // Computed ephemeris — date-sensitive, keep it short.
            'positions' => (int) env('SOLAR_CACHE_POSITIONS', 300),     // 5m
            // Cheap reachability probe used for graceful degradation.
            'health' => (int) env('SOLAR_CACHE_HEALTH', 30),            // 30s
        ],
    ],

    // Mailchimp Marketing API — newsletter signups (double opt-in). The data
    // centre prefix is derived from the key's "-usNN" suffix. Leave the key
    // unset to hide the signup form entirely.
    'mailchimp' => [
        'api_key' => env('MAILCHIMP_API_KEY'),
        'audience_id' => env('MAILCHIMP_AUDIENCE_ID'),
    ],

    // what3words v3 API — converts "///filled.count.soap" to coordinates for the
    // observer panel. Leave unset to hide the what3words option entirely.
    'what3words' => [
        'key' => env('W3W_API_KEY'),
    ],

    // Open-Meteo forecast API — observer panel weather outlook. No API key.
    'open_meteo' => [
        'base_url' => env('OPEN_METEO_BASE_URL', 'https://api.open-meteo.com'),
        'timeout' => (int) env('OPEN_METEO_TIMEOUT', 6),
        'cache_seconds' => (int) env('OPEN_METEO_CACHE_SECONDS', 1800),
    ],

];

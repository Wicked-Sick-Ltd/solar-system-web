<?php

declare(strict_types=1);

/**
 * Open Graph share-image generation. Per-object cards are rendered once with
 * Imagick and cached on a Storage disk — `local` in dev/CI, `s3` (Ceph RGW) in
 * production. The app serves the bytes itself, so the bucket can stay private.
 */
return [
    // Storage disk used to cache rendered cards. Set OG_DISK=s3 in production.
    'disk' => env('OG_DISK', 'local'),

    // Bump after a design change; name/tagline changes also invalidate automatically.
    // Hashed with public branding for both disk paths and public URL versions.
    'version' => env('OG_VERSION', 'v1'),

    // How long browsers/CDNs may cache a served card, in seconds.
    'ttl' => (int) env('OG_TTL', 2592000), // 30 days

    'fonts' => [
        'serif' => resource_path('fonts/Newsreader-Variable.ttf'),
        'sans' => resource_path('fonts/Inter-Variable.ttf'),
    ],
];

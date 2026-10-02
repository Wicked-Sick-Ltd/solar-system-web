<?php

declare(strict_types=1);

/**
 * Branding and editorial constants for the public site. Kept out of code so the
 * wordmark, palette and external links can move without touching Blade.
 */
return [
    // Public branding is independent of APP_NAME, which also namespaces sessions/cache.
    'name' => env('SITE_NAME', 'Public Universe'),
    'tagline' => 'Astronomy for everyone',
    'description' => 'Explore our solar system, exoplanets and their place in the galaxy. '
        .'Free astronomical data, interactive maps and observing tools for curious '
        .'minds, classrooms and researchers.',

    // Accent palette (also mirrored in resources/css/app.css @theme).
    'accent' => '#E0B872', // amber — highlights
    'link' => '#7AB8FF',   // cool blue — links / active states

    // External references surfaced in the footer and the /about + /api pages.
    'backend_repo' => 'https://github.com/Wicked-Sick-Ltd/solar-system-db',
    'api_docs_url' => env('API_DOCS_URL'), // backend /docs; falls back to base_url host
    // Manifest of the nightly published database (whole catalogue, one SQLite file).
    'download_url' => env('SOLAR_DOWNLOAD_URL', 'https://s3.wickedsick.com/solar-system-db/latest.json'),
    'contact_email' => env('CONTACT_EMAIL', 'hello@wickedsick.com'),

    // Legal entity named in the privacy policy.
    'operator' => env('SITE_OPERATOR', 'Wicked Sick Ltd'),

    // Google Analytics 4. Leave unset to ship no analytics at all. When set,
    // gtag is only loaded client-side after the visitor accepts analytics
    // cookies (see resources/views/components/cookie-banner.blade.php).
    'analytics' => [
        'ga_measurement_id' => env('GA_MEASUREMENT_ID'),
    ],
];

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
    // <title> of the homepage, after the brand. The tagline stays on share cards and in the footer.
    'home_title' => 'Interactive Solar System, Exoplanet & Night Sky Catalogue',
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
    'download_url' => env('SOLAR_DOWNLOAD_URL', 'https://download.sol.wickedsick.com/latest.json'),
    'contact_email' => env('CONTACT_EMAIL', 'hello@wickedsick.com'),

    // Official social profiles. Only non-empty entries render (footer, /about,
    // Organization JSON-LD sameAs). Reserved handles: Bluesky publicuniverse.net;
    // Instagram/Threads/TikTok/YouTube/Facebook publicuniversenet.
    'social' => [
        'x' => env('SOCIAL_X_URL', 'https://x.com/PublicUnivNet'),
        'bluesky' => env('SOCIAL_BLUESKY_URL'),     // https://bsky.app/profile/publicuniverse.net
        'instagram' => env('SOCIAL_INSTAGRAM_URL'), // https://www.instagram.com/publicuniversenet
        'threads' => env('SOCIAL_THREADS_URL'),     // https://www.threads.net/@publicuniversenet
        'tiktok' => env('SOCIAL_TIKTOK_URL'),       // https://www.tiktok.com/@publicuniversenet
        'youtube' => env('SOCIAL_YOUTUBE_URL'),     // https://www.youtube.com/@publicuniversenet
        'facebook' => env('SOCIAL_FACEBOOK_URL'),   // https://www.facebook.com/publicuniversenet
    ],

    // Legal entity named in the privacy policy.
    'operator' => env('SITE_OPERATOR', 'Wicked Sick Ltd'),

    // Google Analytics 4. Leave GA4_MEASUREMENT_ID unset to ship no analytics
    // and no cookie banner. When set, Consent Mode v2 keeps analytics_storage
    // denied and gtag.js is injected only after the visitor opts in
    // (resources/js/analytics-consent.js).
    'analytics' => [
        'ga_measurement_id' => env('GA4_MEASUREMENT_ID'),
    ],
];

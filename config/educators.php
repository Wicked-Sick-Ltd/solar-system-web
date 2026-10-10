<?php

declare(strict_types=1);

/**
 * Classroom handouts offered for download on /educators.
 *
 * Adding a handout = drop the PDF and its page images under public/handouts/
 * and add an entry here. Paths are relative to public/ so the page works on
 * every hostname the site is served from. (The files live in public/handouts/
 * rather than public/educators/ because a public directory with the same
 * name as the /educators route would shadow it under nginx's
 * `try_files $uri $uri/` and the PHP dev server.) Page count and paper size are
 * declared rather than read from the PDF so the page costs nothing to render;
 * the file size is read from disk at render time.
 *
 * Keys: `id` (stable slug, used for anchors), `audience` (Primary, Secondary),
 * `stages` (Key Stage codes resolved by App\Support\KeyStage — the only place
 * US grades and ages are defined), `title`, `description` (`:ks1` … `:ks5`
 * and `:eyfs` are replaced with the compact label), `pdf`, `thumbnail`,
 * `previews` (optional further page images), `pages`, `paper`.
 */
return [
    // Add only approved, locally published vector PDFs and raster previews.
    // Each poster: title, description, credit, licence, pdf, preview, alt, width, height.
    // Store assets in public/handouts/posters/ so /educators remains an application route.
    'posters' => [],

    'handouts' => [
        [
            'id' => 'primary',
            'audience' => 'Primary',
            'stages' => ['KS1', 'KS2'],
            'title' => 'Where are the planets today?',
            'description' => 'A friendly introduction to the real Solar System for primary classes: a '
                .'map of where the planets are, the planets drawn to size, and short hands-on '
                .'activities for :ks1 and :ks2 (Lower and Upper KS2) linked to the National Curriculum.',
            'pdf' => 'handouts/solar-handout-primary-y1-6.pdf',
            'thumbnail' => 'handouts/primary-1.webp',
            'previews' => [
                'handouts/primary-2.webp',
                'handouts/primary-3.webp',
                'handouts/primary-4.webp',
            ],
            'pages' => 4,
            'paper' => 'A4',
        ],
        [
            'id' => 'secondary',
            'audience' => 'Secondary',
            'stages' => ['KS3', 'KS4', 'KS5'],
            'title' => 'The Solar System, as live data',
            'description' => 'For secondary schools and colleges: the Solar System as a live dataset, '
                .'with Kepler\'s third law straight from the catalogue, suggested uses for :ks3, GCSE '
                .'(:ks4) and A-level (:ks5), STEM club projects, and a first look at the open REST API.',
            'pdf' => 'handouts/solar-handout-secondary-ks3-ks5.pdf',
            'thumbnail' => 'handouts/secondary-1.webp',
            'previews' => [
                'handouts/secondary-2.webp',
                'handouts/secondary-3.webp',
                'handouts/secondary-4.webp',
            ],
            'pages' => 4,
            'paper' => 'A4',
        ],
    ],
];

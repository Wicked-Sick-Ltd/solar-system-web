<?php

declare(strict_types=1);

/**
 * Classroom handouts offered for download on /educators.
 *
 * Adding a handout = drop the PDF and its page images under public/educators/
 * and add an entry here. Paths are relative to public/ so the page works on
 * every hostname the site is served from. Page count and paper size are
 * declared rather than read from the PDF so the page costs nothing to render;
 * the file size is read from disk at render time.
 *
 * Keys: `id` (stable slug, used for anchors), `key_stage` (badge label),
 * `title`, `description`, `pdf`, `thumbnail`, `previews` (optional further
 * page images), `pages`, `paper`, `educational_level` (schema.org
 * educationalLevel strings for JSON-LD).
 */
return [
    'handouts' => [
        [
            'id' => 'primary',
            'key_stage' => 'Primary · Years 1–6',
            'title' => 'Where are the planets today?',
            'description' => 'A friendly introduction to the real Solar System for primary classes: a '
                .'map of where the planets are, the planets drawn to size, and short hands-on '
                .'activities for Key Stage 1, Lower KS2 and Upper KS2 linked to the National Curriculum.',
            'pdf' => 'educators/solar-handout-primary-y1-6.pdf',
            'thumbnail' => 'educators/primary-1.webp',
            'previews' => [
                'educators/primary-2.webp',
                'educators/primary-3.webp',
                'educators/primary-4.webp',
            ],
            'pages' => 4,
            'paper' => 'A4',
            'educational_level' => ['Key Stage 1', 'Key Stage 2'],
        ],
        [
            'id' => 'secondary',
            'key_stage' => 'Secondary · KS3–KS5',
            'title' => 'The Solar System, as live data',
            'description' => 'For secondary schools and colleges: the Solar System as a live dataset, '
                .'with Kepler\'s third law straight from the catalogue, suggested uses for KS3, GCSE '
                .'and A-level, STEM club projects, and a first look at the open REST API.',
            'pdf' => 'educators/solar-handout-secondary-ks3-ks5.pdf',
            'thumbnail' => 'educators/secondary-1.webp',
            'previews' => [
                'educators/secondary-2.webp',
                'educators/secondary-3.webp',
                'educators/secondary-4.webp',
            ],
            'pages' => 4,
            'paper' => 'A4',
            'educational_level' => ['Key Stage 3', 'Key Stage 4', 'Key Stage 5'],
        ],
    ],
];

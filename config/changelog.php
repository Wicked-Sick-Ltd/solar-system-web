<?php

declare(strict_types=1);

return [
    // Public notes are generated from this repository's merged pull requests.
    // A token is read from the environment at collect time and is never stored here.
    'repository' => env('CHANGELOG_REPOSITORY', 'Wicked-Sick-Ltd/solar-system-web'),
    'path' => resource_path('changelog/notes.json'),
];

<?php

return [
    // Read only while building configuration; web requests do not invoke Git.
    'version' => is_file(base_path('version.txt')) ? trim(file_get_contents(base_path('version.txt')) ?: '') : '0.0.0',
    'commit' => is_file(base_path('bootstrap/build-commit.txt')) ? trim(file_get_contents(base_path('bootstrap/build-commit.txt')) ?: '') : '',
    'notes_path' => resource_path('releases'),
];

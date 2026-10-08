<?php

declare(strict_types=1);

return [
    // Set only after live client checks pass following the domain/WAF migration.
    'connection_ready' => (bool) env('PLUGIN_CONNECTION_READY', false),
    'repository' => 'https://github.com/Wicked-Sick-Ltd/solar-plugin',
];

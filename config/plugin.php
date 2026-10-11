<?php

declare(strict_types=1);

return [
    // Set only after live client checks pass following the domain/WAF migration.
    'connection_ready' => (bool) env('PLUGIN_CONNECTION_READY', false),
    // The plugin is published in the public ai-marketplace repository under plugins/publicuniverse.
    'repository' => 'https://github.com/Wicked-Sick-Ltd/ai-marketplace',
    'path' => 'plugins/publicuniverse',
    'url' => 'https://github.com/Wicked-Sick-Ltd/ai-marketplace/tree/main/plugins/publicuniverse',
    'docs_url' => 'https://github.com/Wicked-Sick-Ltd/ai-marketplace/blob/main/plugins/publicuniverse',
];

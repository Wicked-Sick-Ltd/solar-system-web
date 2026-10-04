<?php

return [
    'to' => env('FEEDBACK_TO_ADDRESS', 'hello@publicuniverse.net'),
    'mailer' => env('FEEDBACK_MAILER', env('MAIL_MAILER', 'log')),
    'categories' => [
        'feature' => 'Feature request',
        'bug' => 'Report a bug',
        'contact' => 'Get in contact',
    ],
];

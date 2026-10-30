<?php

declare(strict_types=1);

return [

    /*
    | Slack incoming webhook for #sites-publicuniverse. Unset means no Slack
    | message is sent. The value is an operator secret and is not committed.
    */
    'slack_webhook_url' => env('PU_SITES_SLACK_WEBHOOK_URL'),

    /*
    | Asana personal access token. Unset means no task is created, even when
    | a project id is present.
    */
    'asana_access_token' => env('PU_ASANA_ACCESS_TOKEN'),

    /*
    | Asana project that should receive API access requests. The Public
    | Universe project is 1220017034322863. Leave this unset to skip Asana.
    */
    'asana_project_id' => env('PU_ASANA_PROJECT_ID'),

    'volumes' => [
        'under-1000' => 'Under 1,000 calls/day',
        '1000-10000' => '1,000–10,000 calls/day',
        '10000-100000' => '10,000–100,000 calls/day',
        'over-100000' => 'More than 100,000 calls/day',
    ],

];

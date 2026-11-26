<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| "When and where to see it from the UK": editable copy slots
|--------------------------------------------------------------------------
|
| The dates, times, directions and Moon figures on every meteor-shower page
| are calculated from the catalogue record (App\Support\Sky\UkMeteorViewing).
| These slots hold hand-written copy only, keyed by uppercase IAU code, so a
| copy brief can be dropped in without touching the calculation or the view.
|
| - intro: one paragraph shown under the section heading. Null hides it.
| - notes: short extra lines shown after the calculated list.
| - faq:   extra question and answer pairs shown after the calculated FAQ
|          and added to the page's FAQPage structured data.
|
| Do not put dates or times in these slots: they would go stale every year.
| The calculated section already carries them.
|
*/

return [
    'showers' => [
        'GEM' => [
            'intro' => null,
            'notes' => [],
            'faq' => [],
        ],
        'QUA' => [
            'intro' => null,
            // International Meteor Organization, Meteor Shower Calendar (J. Rendtel):
            // the Quadrantid maximum is short, a few hours at full strength.
            'notes' => [
                'The Quadrantid peak is short, only a few hours at full strength, so it matters more than for most showers whether the peak falls in UK darkness.',
            ],
            'faq' => [],
        ],
    ],
];

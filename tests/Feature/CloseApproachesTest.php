<?php

use Illuminate\Support\Facades\Http;

beforeEach(fn () => fakeSolar());

it('lists upcoming Earth approaches soonest first, linked to each object', function () {
    $this->travelTo('2026-09-29 12:00:00');

    $this->get('/close-approaches')
        ->assertOk()
        ->assertSeeInOrder(['2022 UP6', '2019 XF2'])
        ->assertSee(route('objects.show', 'ast-sooner'), escape: false)
        ->assertSee('2.6 LD');

    Http::assertSent(fn ($r) => str_ends_with(parse_url($r->url(), PHP_URL_PATH), '/close-approaches')
        && $r['from'] === '2026-09-29' && $r['to'] === '2026-11-28' && $r['body'] === 'Earth');
});

it('degrades when the API is down', function () {
    fakeSolarDown();

    $this->get('/close-approaches')->assertOk()->assertSee('unavailable');
});

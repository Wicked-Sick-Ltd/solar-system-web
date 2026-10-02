<?php

declare(strict_types=1);

use App\Support\Links;

/*
 * The site answers on more than one hostname (publicuniverse.net is canonical;
 * sol.wickedsick.com stays live as an alias because printed handouts and QR
 * codes point at it). Whichever host serves a request, everything search
 * engines and link previews read must name the canonical one — without any
 * redirect, so the alias keeps working.
 */

beforeEach(function () {
    fakeSolar();
    config(['app.url' => 'https://publicuniverse.net']);
});

it('emits the canonical host in <link rel=canonical> and OG tags when served from the alias', function () {
    $this->get('https://sol.wickedsick.com/educators')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="https://publicuniverse.net/educators">', escape: false)
        ->assertSee('<meta property="og:url" content="https://publicuniverse.net/educators">', escape: false)
        ->assertSee('<meta property="og:image" content="https://publicuniverse.net/images/og-default.png">', escape: false);
});

it('keeps internal navigation on the host that served the request', function () {
    // No host redirect, and nothing on the page pushes an alias visitor across
    // to the canonical host mid-session.
    $this->get('https://sol.wickedsick.com/educators')
        ->assertOk()
        ->assertSee('href="https://sol.wickedsick.com/about"', escape: false)
        ->assertSee('href="/educators/solar-handout-primary-y1-6.pdf"', escape: false);
});

it('puts the canonical host in a detail page canonical, share card and JSON-LD', function () {
    $this->get('https://sol.wickedsick.com/objects/planet-saturn')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="https://publicuniverse.net/objects/planet-saturn">', escape: false)
        ->assertSee('content="https://publicuniverse.net/og/objects/planet-saturn.png"', escape: false)
        ->assertSee('"url":"https://publicuniverse.net/objects/planet-saturn"', escape: false);
});

it('leaves external URLs in JSON-LD alone', function () {
    $this->get('https://sol.wickedsick.com/objects/planet-saturn')
        ->assertOk()
        ->assertSee('"sameAs":"https://en.wikipedia.org/wiki/Saturn"', escape: false);
});

it('writes every sitemap <loc> on the canonical host whichever alias asked', function () {
    $this->get('https://sol.wickedsick.com/sitemap.xml')
        ->assertOk()
        ->assertSee('<loc>https://publicuniverse.net</loc>', escape: false)
        ->assertSee('<loc>https://publicuniverse.net/educators</loc>', escape: false)
        ->assertSee('<loc>https://publicuniverse.net/objects/', escape: false)
        ->assertDontSee('sol.wickedsick.com');
});

it('points robots.txt at the canonical sitemap', function () {
    $this->get('https://sol.wickedsick.com/robots.txt')
        ->assertOk()
        ->assertSee('Sitemap: https://publicuniverse.net/sitemap.xml')
        ->assertDontSee('sol.wickedsick.com');
});

it('rewrites only URLs on the current request root', function () {
    $this->get('https://sol.wickedsick.com/about'); // establish a request root

    expect(Links::canonical('https://sol.wickedsick.com/orrery?date=2026-10-01'))->toBe('https://publicuniverse.net/orrery?date=2026-10-01')
        ->and(Links::canonical('https://sol.wickedsick.com'))->toBe('https://publicuniverse.net')
        ->and(Links::canonical('/educators'))->toBe('https://publicuniverse.net/educators')
        ->and(Links::canonical('https://en.wikipedia.org/wiki/Saturn'))->toBe('https://en.wikipedia.org/wiki/Saturn')
        ->and(Links::canonical('https://cdn.example.net/images/og.png'))->toBe('https://cdn.example.net/images/og.png');
});

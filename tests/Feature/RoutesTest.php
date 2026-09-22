<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;

beforeEach(fn () => fakeSolar());

it('renders every public P0 route', function (string $uri) {
    $this->get($uri)
        ->assertOk()
        ->assertSee(config('site.name'), escape: false);
})->with([
    'home' => '/',
    'objects index' => '/objects',
    'objects filtered' => '/objects?type=asteroid&named=1&page=1',
    'object detail' => '/objects/planet-saturn',
    'planet detail' => '/planets/planet-saturn',
    'planets landing' => '/planets',
    'dwarf planets' => '/dwarf-planets',
    'asteroids' => '/asteroids',
    'comets' => '/comets',
    'tnos' => '/tnos',
    'search' => '/search?q=ceres',
    'orrery' => '/orrery',
    'exoplanets' => '/exoplanets',
    'exoplanet detail' => '/exoplanets/exo-proxima-b',
    'exoplanet system' => '/systems/host-proxima',
    'galaxy' => '/galaxy',
    'about' => '/about',
    'api' => '/api',
    'privacy' => '/privacy',
]);

it('links the homepage moons card to the filtered catalogue', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee(route('objects.index', ['type' => 'moon']), escape: false);
});

it('plots bodies on the orrery for a given date', function () {
    $this->get('/orrery?date=2026-06-01')
        ->assertOk()
        ->assertSee('Orrery')
        ->assertSee('<svg', escape: false)
        ->assertSee('Saturn');
});

it('keeps the orrery up when positions fail after the health probe passed', function () {
    fakeSolarDown();
    // The probe is cached for a health window, so the backend can fall over
    // between it and the position batch. The page degrades; it does not 500.
    Cache::put('solar:health', true, 60);

    $this->get('/orrery?date=2026-06-01')
        ->assertOk()
        ->assertSee('No positions for that date');
});

it('puts the object name and structured data on a detail page', function () {
    $this->get('/objects/planet-saturn')
        ->assertOk()
        ->assertSee('Saturn')
        ->assertSee('Where is it now')
        ->assertSee('"@type":"Thing"', escape: false)
        ->assertSee('canonical', escape: false);
});

it('advertises a favicon and a default share image', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('favicon.svg', escape: false)
        ->assertSee('og:image', escape: false)
        ->assertSee('images/og-default.png', escape: false)
        ->assertSee('twitter:card', escape: false);
});

it('returns a branded 404 for an unknown object when the backend is healthy', function () {
    $this->get('/objects/missing-object')
        ->assertNotFound()
        ->assertSee('404')
        ->assertSee('Lost in space')
        ->assertSee(config('site.name'), escape: false);
});

it('redirects /random to an object detail page', function () {
    $this->get('/random')->assertRedirectContains('/objects/');
});

it('serves a robots.txt that points at the sitemap', function () {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertSee('Sitemap:')
        ->assertSee('Disallow: /search');
});

it('serves an XML sitemap including object URLs', function () {
    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<urlset', escape: false)
        ->assertSee('/objects/', escape: false);
});

it('stays up and shows a degradation panel when the backend is down', function () {
    fakeSolarDown();

    $this->get('/')
        ->assertOk()
        ->assertSee('Browse by kind')           // the rest of the page still works
        ->assertSee('reach the catalogue');     // the calm degradation panel
});

it('does not 404 a detail page when the backend is down', function () {
    fakeSolarDown();

    $this->get('/objects/planet-saturn')
        ->assertOk()
        ->assertSee('unavailable');
});

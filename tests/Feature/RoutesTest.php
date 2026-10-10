<?php

declare(strict_types=1);

use App\Services\SolarApi\CatalogueContext;
use App\Support\Format;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => fakeSolar());

it('renders every public P0 route', function (string $uri) {
    $this->get($uri)
        ->assertOk()
        ->assertSee(config('site.name'), escape: false);
})->with([
    'home' => '/',
    'explore' => '/explore',
    'observe' => '/observe',
    'observing shortlist' => '/observe/shortlist',
    'learn' => '/learn',
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
    'systems directory' => '/systems',
    'meteor showers' => '/meteor-showers',
    'close approaches' => '/close-approaches',
    'about' => '/about',
    'educators' => '/educators',
    'higher education' => '/higher-education',
    'astronomy plugin' => '/plugin',
    'feedback' => '/feedback',
    'api' => '/api',
    'privacy' => '/privacy',
    'releases' => '/releases',
    'whats new' => '/whats-new',
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
    Cache::put(CatalogueContext::storageKey().':health', true, 60);

    $this->get('/orrery?date=2026-06-01')
        ->assertOk()
        ->assertSee('Positions unavailable for this date');
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
        ->assertSee('apple-touch-icon.png', escape: false)
        ->assertSee('site.webmanifest', escape: false)
        ->assertSee('og:image', escape: false)
        ->assertSee('images/og-public-universe.png', escape: false)
        ->assertSee('twitter:card', escape: false);

    $manifest = json_decode(file_get_contents(public_path('site.webmanifest')), true, flags: JSON_THROW_ON_ERROR);
    expect(is_file(public_path('apple-touch-icon.png')))->toBeTrue()
        ->and($manifest['name'])->toBe('Public Universe')
        ->and($manifest['icons'])->not->toBeEmpty();
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

it('ignores array search parameters in the shared header', function () {
    $this->get('/learn?q%5B%5D=Proxima')->assertOk();
});

it('picks the featured object from the frozen UTC day', function () {
    $this->travelTo(new DateTimeImmutable('2026-10-01T12:00:00Z'));
    $this->get('/')
        ->assertOk()
        ->assertSee('href="'.route('objects.show', 'moon-titan').'"', false);

    $this->travelTo(new DateTimeImmutable('2026-10-09T12:00:00Z'));
    $this->get('/')
        ->assertOk()
        ->assertSee('href="'.route('objects.show', 'dwarf-pluto').'"', false);
});

it('labels a featured moon by the radius of its orbit in kilometres', function () {
    $this->travelTo(new DateTimeImmutable('2026-10-01T12:00:00Z'));

    $this->get('/')
        ->assertOk()
        ->assertSee('Orbit radius')
        ->assertSee(Format::orbitRadiusKm(0.01))
        ->assertDontSee('0.01 AU');
});

it('keeps a featured heliocentric distance in astronomical units', function () {
    $this->travelTo(new DateTimeImmutable('2026-02-06T12:00:00Z'));

    $this->get('/')
        ->assertOk()
        ->assertSee('Distance')
        ->assertSee('9.537 AU')
        ->assertDontSee('Orbit radius');
});

it('renders the observing starter catalogue and exact source detail routes', function (string $uri) {
    $fixture = json_decode(file_get_contents(base_path('tests/fixtures/starter-catalogue.json')), true, flags: JSON_THROW_ON_ERROR);
    Http::swap(new Factory);
    Http::preventStrayRequests();
    $target = $fixture['results'][0];
    Http::fake([
        '*starter-targets/*' => Http::response($target + ['provenance' => $fixture['sources'][$target['source']]]),
        '*starter-targets*' => Http::response($fixture),
        '*' => Http::response([], 503),
    ]);
    $this->get($uri)->assertOk()->assertSee(config('site.name'))->assertSee('Source and reuse');
})->with(['/observing-targets', '/observing-targets/bsc5p:hr1708']);

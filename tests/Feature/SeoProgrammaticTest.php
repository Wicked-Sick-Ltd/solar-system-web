<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Sitemap\SitemapBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(fn () => fakeSolar());

it('keeps auth and account pages out of the index under their own titles', function () {
    $brand = config('site.name');

    $this->get('/register')->assertOk()
        ->assertSee("<title>Create account · {$brand}</title>", false)
        ->assertSee('<meta name="robots" content="noindex', false);
    $this->get('/login')->assertOk()
        ->assertSee("<title>Sign in · {$brand}</title>", false)
        ->assertSee('<meta name="robots" content="noindex', false);
    $this->actingAs(User::factory()->create())->get('/alerts')->assertOk()
        ->assertSee("<title>Your visibility alerts · {$brand}</title>", false)
        ->assertSee('<meta name="robots" content="noindex', false);
});

it('gives every hub a descriptive title with the brand as the delimiter', function (string $path, string $title) {
    $this->get($path)->assertOk()->assertSee("<title>{$title}</title>", false);
})->with([
    ['/', 'Public Universe · Interactive Solar System, Exoplanet &amp; Night Sky Catalogue'],
    ['/explore', 'Explore the Solar System, Exoplanets &amp; 3D Galaxy Map · Public Universe'],
    ['/observe', 'Night Sky Planner: Planet Visibility, Altitude Charts &amp; Close Approaches · Public Universe'],
    ['/learn', 'Learn Astronomy with Real NASA &amp; JPL Data · Public Universe'],
    ['/educators', 'Free Astronomy Worksheets &amp; UK Key Stage Space Lesson Resources · Public Universe'],
    ['/exoplanets', 'Confirmed Exoplanets Catalogue &amp; Host Star Explorer · Public Universe'],
    ['/planets', 'The Eight Planets of the Solar System: Data, Orbits &amp; Moons · Public Universe'],
    ['/asteroids', 'Asteroid Catalogue: Main Belt, Near-Earth &amp; Potentially Hazardous Objects · Public Universe'],
]);

it('builds an object title from the facets the record has and a prose summary when it has no notes', function () {
    // The generic fixture is a moon with orbital elements and a radius but no notes.
    $this->get('/objects/obj-7')->assertOk()
        ->assertSee('<title>Obj 7 (Moon): Diameter, Orbit &amp; Live Sky Position · Public Universe</title>', false)
        ->assertSee('<meta name="description" content="Obj 7 is a moon. One orbit takes 36 hours; it is about 400 km across."', false)
        ->assertSee('Obj 7 is a moon. One orbit takes 36 hours; it is about 400 km across.');
});

it('emits Dataset, WebAPI, SoftwareApplication and LearningResource structured data', function () {
    $api = $this->get('/api')->assertOk();
    $api->assertSee('"@type":"Dataset"', false)
        ->assertSee('"@type":"WebAPI"', false)
        ->assertSee('"license":"https://github.com/Wicked-Sick-Ltd/solar-system-db/blob/main/LICENSE"', false)
        ->assertSee('"@type":"DataDownload"', false)
        ->assertSee('NASA Exoplanet Archive', false);

    foreach (['/asteroids', '/planets', '/exoplanets', '/objects'] as $path) {
        $this->get($path)->assertOk()->assertSee('"@type":"Dataset"', false);
    }

    $this->get('/plugin')->assertOk()
        ->assertSee('"@type":"SoftwareApplication"', false)
        ->assertSee('Claude Code, Cursor, Codex, ChatGPT, GitHub Copilot', false);

    $this->get('/higher-education/keplers-law/handout')->assertOk()
        ->assertSee('"@type":"LearningResource"', false)
        ->assertSee('"isAccessibleForFree":true', false);
});

it('serves the sitemap index under both names and lists notable asteroids among the objects', function () {
    $index = $this->get('/sitemap.xml')->assertOk()->getContent();
    expect($this->get('/sitemap_index.xml')->assertOk()->getContent())->toBe($index)
        ->and($index)->toContain('/sitemaps/objects.xml');

    // The asteroid walk asks the catalogue for large bodies only.
    $this->get('/sitemaps/objects.xml')->assertOk()->assertSee('/objects/obj-0', false);
    Http::assertSent(fn ($request) => str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/objects')
        && ($request['type'] ?? null) === 'asteroid'
        && (string) ($request['min_diameter_km'] ?? '') === (string) SitemapBuilder::NOTABLE_ASTEROID_KM);
});

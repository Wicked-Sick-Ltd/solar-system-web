<?php

declare(strict_types=1);

use App\Support\ObjectOfTheDay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\SitemapSchema;

beforeEach(function () {
    fakeSolar();
});

it('publishes a cached sitemap index split by page type', function () {
    $index = $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->getContent();

    SitemapSchema::assertValid($index, 'siteindex.xsd');
    expect($index)->toContain('<sitemapindex')
        ->and($index)->not->toContain('<urlset')
        ->and($index)->toContain('<lastmod>2026-10-01T12:00:00Z</lastmod>');

    preg_match_all('#<loc>([^<]+)</loc>#', $index, $children);
    $names = [];
    foreach ($children[1] as $loc) {
        $path = parse_url(html_entity_decode($loc), PHP_URL_PATH);
        expect($path)->toStartWith('/sitemaps/')->toEndWith('.xml');
        $names[] = basename((string) $path, '.xml');
        $xml = $this->get($path)->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
        SitemapSchema::assertValid($xml, 'sitemap.xsd');
        expect(substr_count($xml, '<loc>'))->toBeGreaterThan(0)->toBeLessThan(50000)
            ->and(strlen($xml))->toBeLessThan(50 * 1024 * 1024)
            ->and($xml)->not->toContain('<lastmod>'.now()->toAtomString().'</lastmod>');
    }

    expect($names)->toContain('pages', 'objects', 'exoplanets', 'systems', 'today')
        ->and($names)->not->toContain('releases');

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/exoplanets')
        && (int) $request['limit'] === 1000
        && (int) $request['offset'] === 0);

    $sent = count(Http::recorded());
    $this->get('/sitemap.xml')->assertOk();
    $this->get('/sitemaps/exoplanets.xml')->assertOk();
    expect(count(Http::recorded()))->toBe($sent);
});

it('uses the catalogue build date and record timestamps, not the request clock', function () {
    $this->travelTo(now()->addMinutes(30));
    $clock = now()->utc()->format('Y-m-d\TH:i:s\Z');

    $pages = $this->get('/sitemaps/pages.xml')->assertOk()->getContent();
    $objects = $this->get('/sitemaps/objects.xml')->assertOk()->getContent();
    $planets = $this->get('/sitemaps/exoplanets.xml')->assertOk()->getContent();
    $systems = $this->get('/sitemaps/systems.xml')->assertOk()->getContent();

    expect($pages)->toContain('<lastmod>2026-10-01T12:00:00Z</lastmod>')
        ->and($pages)->not->toContain($clock)
        ->and($objects)->toContain('<lastmod>2026-10-01T12:00:00Z</lastmod>')
        ->and($objects)->not->toContain($clock)
        ->and($planets)->toContain('<loc>'.url('/exoplanets/exo-proxima-b').'</loc>')
        ->and($planets)->toContain('<lastmod>2026-09-22T12:00:00Z</lastmod>')
        ->and($systems)->toContain('<loc>'.url('/systems/host-proxima').'</loc>')
        ->and($systems)->toContain('<lastmod>2026-09-22T12:00:00Z</lastmod>');
});

it('samples every child sitemap and resolves every moon and exoplanet url', function () {
    $index = $this->get('/sitemap.xml')->assertOk()->getContent();
    preg_match_all('#<loc>([^<]+)</loc>#', $index, $children);

    $byChild = [];
    foreach ($children[1] as $loc) {
        $path = (string) parse_url(html_entity_decode($loc), PHP_URL_PATH);
        $xml = $this->get($path)->assertOk()->getContent();
        preg_match_all('#<loc>([^<]+)</loc>#', $xml, $urls);
        $paths = [];
        foreach ($urls[1] as $url) {
            $page = parse_url(html_entity_decode($url), PHP_URL_PATH);
            $page = is_string($page) && $page !== '' ? $page : '/';
            $matched = Route::getRoutes()->match(Request::create($page, 'GET'));
            expect($matched->getName())->not->toBeNull();
            $paths[] = $page;
        }
        $byChild[basename($path, '.xml')] = $paths;
    }

    $moons = array_values(array_filter(
        $byChild['objects'],
        fn (string $path): bool => str_starts_with($path, '/objects/moon'),
    ));
    expect($moons)->not->toBeEmpty()
        ->and($moons)->toContain('/objects/moon-s/2019-s-1')
        ->and($byChild['exoplanets'])->not->toBeEmpty()
        ->and($byChild['systems'])->not->toBeEmpty()
        ->and($byChild['pages'])->toContain('/educators')
        ->and($byChild['today'])->toContain('/today/'.ObjectOfTheDay::FIRST_DATE)
        ->and($byChild['today'])->toContain('/today/'.ObjectOfTheDay::today()->format('Y-m-d'))
        ->and($byChild['today'])->not->toContain('/today/2025-12-31');

    foreach ([...$moons, ...$byChild['exoplanets'], ...$byChild['systems'], ...$byChild['pages']] as $path) {
        $this->get($path)->assertOk();
    }

    $today = $byChild['today'];
    $sample = [$today[0], $today[(int) floor(count($today) / 2)], $today[array_key_last($today)]];
    foreach ($sample as $path) {
        $this->get($path)->assertOk();
    }
});

it('still serves the static pages when the catalogue cannot be read', function () {
    fakeSolarDown();

    $index = $this->get('/sitemap.xml')->assertOk()->getContent();
    SitemapSchema::assertValid($index, 'siteindex.xsd');
    expect($index)->toContain('/sitemaps/pages.xml')
        ->and($index)->not->toContain('/sitemaps/exoplanets.xml')
        ->and($index)->not->toContain('/sitemaps/objects.xml');

    $pages = $this->get('/sitemaps/pages.xml')->assertOk()->getContent();
    SitemapSchema::assertValid($pages, 'sitemap.xsd');
    expect($pages)->toContain('<loc>'.url('/').'</loc>')
        ->and($pages)->not->toContain('<lastmod>');
});

it('keeps robots.txt pointed at the sitemap index', function () {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertSee('Sitemap: '.url('/sitemap.xml'), false);
});

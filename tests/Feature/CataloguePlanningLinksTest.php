<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function fakePlanningCatalogue(array $fixture): void
{
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(function ($request) use ($fixture) {
        if ($request->method() !== 'GET' || ! str_contains($request->url(), '/starter-targets')) {
            throw new RuntimeException('Catalogue links must not calculate a night.');
        }
        $id = basename(rawurldecode(parse_url($request->url(), PHP_URL_PATH)));
        foreach ($fixture['results'] as $row) {
            if ($row['id'] === $id) {
                return Http::response($row + ['provenance' => $fixture['sources'][$row['source']]]);
            }
        }

        return Http::response(['available' => true, 'results' => $fixture['results'], 'sources' => $fixture['sources'], 'total' => count($fixture['results']), 'limit' => 24, 'offset' => 0]);
    });
}

beforeEach(function () {
    $this->withoutVite();
    config(['cache.default' => 'array']);
    Cache::flush();
});

it('offers native exact target hints on verified catalogue cards and details without any calculation', function () {
    $fixture = json_decode(file_get_contents(base_path('tests/fixtures/starter-astrometry.json')), true, flags: JSON_THROW_ON_ERROR);
    fakePlanningCatalogue($fixture);
    $page = $this->get('/observing-targets')->assertOk();
    foreach (['bsc5p:hr2491', 'openngc:NGC0224'] as $id) {
        $href = route('observe.night', ['targets' => [$id]]);
        $page->assertSee('href="'.e($href).'"', false);
        $this->get(route('observing-targets.show', $id))->assertOk()->assertSee('href="'.e($href).'"', false)
            ->assertSee('Nothing is calculated or saved by following the link.');
        parse_str(parse_url($href, PHP_URL_QUERY), $query);
        expect($query)->toBe(['targets' => [$id]]);
    }
    $unsupported = route('observe.night', ['targets' => ['openngc:Mel022']]);
    $page->assertDontSee($unsupported, false);
    $this->get('/observing-targets/openngc:Mel022')->assertOk()->assertDontSee($unsupported, false);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/observing/night'));
});

it('keeps legacy and incomplete-motion rows browsable without promising a supported calculation', function () {
    $fixture = json_decode(file_get_contents(base_path('tests/fixtures/starter-astrometry.json')), true, flags: JSON_THROW_ON_ERROR);
    $fixture['results'][0]['source_data']['pmra'] = '';
    $fixture['results'][0]['astrometry']['pm_ra_cosdec_arcsec_per_year'] = null;
    $fixture['results'][0]['astrometry']['motion_model'] = 'static_catalogue_direction';
    fakePlanningCatalogue($fixture);
    $href = route('observe.night', ['targets' => ['bsc5p:hr2491']]);
    $this->get('/observing-targets/bsc5p:hr2491')->assertOk()->assertDontSee($href, false);
    Cache::flush();
    foreach ($fixture['results'] as &$row) {
        unset($row['astrometry']);
    }
    unset($row);
    foreach ($fixture['sources'] as &$source) {
        unset($source['astrometry_evidence']);
    }
    unset($source);
    fakePlanningCatalogue($fixture);
    $this->get('/observing-targets/bsc5p:hr2491')->assertOk()->assertDontSee($href, false);
});

it('escapes source labels in link accessible names', function () {
    $fixture = json_decode(file_get_contents(base_path('tests/fixtures/starter-astrometry.json')), true, flags: JSON_THROW_ON_ERROR);
    $fixture['results'][0]['name'] = '<img src=x onerror=alert(1)>';
    fakePlanningCatalogue($fixture);
    $this->get('/observing-targets/bsc5p:hr2491')->assertOk()
        ->assertSee(e('Prepare a night plan for <img src=x onerror=alert(1)>'), false)
        ->assertDontSee('<img src=x onerror=alert(1)>', false);
});

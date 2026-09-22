<?php

use App\Livewire\Exoplanets;
use App\Services\SolarApi\SolarApiClient;
use App\Support\Format;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(fn () => fakeSolar());

it('resets pagination when filtering and forwards bounded filters', function () {
    Livewire::test(Exoplanets::class)->set('page', 2)->set('q', 'Proxima')->assertSet('page', 1)
        ->set('method', 'Radial Velocity')->set('distance', '10')->assertSee('Proxima Cen b');
    Http::assertSent(fn ($r) => str_contains($r->url(), '/exoplanets') && ($r['q'] ?? null) === 'Proxima'
        && ($r['discovery_method'] ?? null) === 'Radial Velocity' && ($r['max_distance_pc'] ?? null) === '10');
    Livewire::test(Exoplanets::class)->set('q', 'foo')->call('clearFilters')->assertSet('q', '');
});

it('paginates an extra row and caches the map', function () {
    Http::swap(new Factory);
    Http::fake(['*/exoplanets*' => Http::response(['available' => true, 'results' => array_fill(0, 25, exoplanetPayload())]),
        '*/galaxy' => Http::response(['available' => true, 'results' => [exoplanetHostPayload()]])]);
    $api = app(SolarApiClient::class);
    expect($api->exoplanets()->hasMore)->toBeTrue()->and($api->exoplanets()->count())->toBe(24);
    $api->galaxyMap();
    $api->galaxyMap();
    Http::assertSentCount(2);
});

it('distinguishes missing records from unavailable services', function () {
    $this->get('/exoplanets/missing')->assertNotFound();
    $this->get('/systems/missing')->assertNotFound();
    fakeSolarDown();
    foreach (['/exoplanets', '/exoplanets/test', '/systems/test', '/galaxy'] as $url) {
        $this->get($url)->assertOk()->assertSee('unavailable');
    }
});

it('handles an older backend and empty filters honestly', function () {
    Http::swap(new Factory);
    Http::fake(['*/exoplanets*' => Http::response(['available' => false, 'results' => []])]);
    $this->get('/exoplanets')->assertOk()->assertSee('unavailable');
    Cache::flush();
    Http::swap(new Factory);
    Http::fake(['*/exoplanets*' => Http::response(['available' => true, 'results' => []])]);
    $this->get('/exoplanets?q=nothing')->assertOk()->assertSee('No exoplanets match');
});

it('shows scientific caveats and never executes archive HTML', function () {
    $planet = exoplanetPayload();
    $planet['name'] = '<script>alert(1)</script>';
    $planet['source_data']['pl_rade_reflink'] = '<img src=x onerror=alert(1)>';
    Http::swap(new Factory);
    Http::fake(['*/exoplanets/*' => Http::response($planet)]);
    $this->get('/exoplanets/test')->assertOk()->assertSee($planet['name'])
        ->assertDontSee('<script>alert(1)</script>', false)->assertSee('minimum mass');
});

it('formats upper limits, lower limits, unknowns and errors', function () {
    expect(Format::exoplanetMeasurement(['v' => 2, 'vlim' => 1], 'v', 'K'))->toBe('< 2 K');
    expect(Format::exoplanetMeasurement(['v' => 2, 'vlim' => -1], 'v', 'K'))->toBe('> 2 K');
    expect(Format::exoplanetMeasurement([], 'v', 'K'))->toBe('Unknown');
    expect(Format::exoplanetMeasurement(['v' => 2, 'verr1' => 0.2, 'verr2' => -0.1], 'v', 'K'))->toBe('2 (+0.2 / −0.1) K');
});

it('provides map controls, source caveats and a linked accessible list', function () {
    $this->get('/galaxy?host=host-proxima')->assertOk()->assertSee('Nearby systems')
        ->assertSee('Milky Way overview')->assertSee('Choose a system')->assertSee('accessible list')
        ->assertSee('host-proxima')->assertSee('not the distribution of all planets');
});

it('loads map data separately from Livewire markup for a full catalogue', function () {
    Http::swap(new Factory);
    $rows = array_fill(0, 5000, exoplanetHostPayload());
    Http::fake(['*/galaxy' => Http::response(['available' => true, 'results' => $rows])]);
    $this->get('/galaxy')->assertOk()->assertDontSee('data-galaxy-hosts', false);
    $this->get('/galaxy/data')->assertOk()->assertJsonCount(5000, 'hosts');
});

<?php

use App\Livewire\SearchPage;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    config(['cache.default' => 'array']);
    Cache::flush();
    Http::swap(new Factory);
    Http::preventStrayRequests();
});

it('searches both catalogues from a shared URL and links objects planets and host systems', function () {
    fakeSolar();
    $this->get('/search?q=Proxima')->assertOk()
        ->assertSee('Ceres')->assertSee('Proxima Cen b')
        ->assertSee(route('objects.show', 'dwarf-ceres'), false)
        ->assertSee(route('exoplanets.show', 'exo-proxima-b'), false)
        ->assertSee(route('systems.show', 'host-proxima'), false)
        ->assertSee('value="Proxima"', false)->assertSee('role="search"', false)
        ->assertSee('name="q"', false)->assertSee('aria-live="polite"', false);

    foreach (['/search', '/exoplanets'] as $path) {
        Http::assertSent(fn ($r) => parse_url($r->url(), PHP_URL_PATH) === '/api/v1'.$path && $r['q'] === 'Proxima');
    }
});

it('keeps live queries and their results in sync', function () {
    fakeSolar();
    Livewire::withQueryParams(['q' => 'Proxima'])->test(SearchPage::class)
        ->assertSet('q', 'Proxima')->assertSee('Proxima Cen b')
        ->set('q', 'Ceres')->assertSet('q', 'Ceres')->assertSee('Ceres')
        ->set('q', '')->assertSee('Start typing')->assertDontSee('Host system:');
});

it('retains available results when the other catalogue fails', function (string $unavailable) {
    Http::fake([
        '*/search*' => $unavailable === 'solar' ? Http::response([], 503) : Http::response(['results' => [['id' => 'planet-saturn', 'name' => 'Saturn']]]),
        '*/exoplanets*' => $unavailable === 'exoplanets' ? Http::response([], 503) : Http::response(['available' => true, 'results' => [exoplanetPayload()]]),
    ]);
    $this->get('/search?q=planet')->assertOk()
        ->assertSee($unavailable === 'solar' ? 'Proxima Cen b' : 'Saturn')
        ->assertSee('Showing results from one catalogue')
        ->assertSee($unavailable === 'solar' ? 'Solar-system search is temporarily unavailable' : 'Exoplanet search is temporarily unavailable')
        ->assertDontSee('No matches for');
})->with(['solar', 'exoplanets']);

it('does not treat missing catalogues as successful empty searches', function () {
    Http::fake(['*/search*' => Http::response([], 404), '*/exoplanets*' => Http::response(['available' => false, 'results' => []])]);
    $this->get('/search?q=nothing')->assertOk()->assertSee('Search is temporarily unavailable')
        ->assertDontSee('No matches for')->assertDontSee('No solar-system objects match')->assertDontSee('No exoplanets or host systems match');
});

it('reports a genuine empty search only when both catalogues respond', function () {
    Http::fake(['*/search*' => Http::response(['results' => []]), '*/exoplanets*' => Http::response(['available' => true, 'results' => []])]);
    $this->get('/search?q=nothing')->assertOk()->assertSee('No matches for')->assertDontSee('temporarily unavailable');
});

it('skips empty and overlong queries without silently searching different text', function () {
    $this->get('/search?q=%20%20')->assertOk()->assertSee('Start typing');
    $this->get('/search?q='.str_repeat('é', 201))->assertOk()->assertSee('Please use a search of 200 characters or fewer.');
    Livewire::test(SearchPage::class)->set('q', str_repeat('x', 201))->assertSee('Please use a search of 200 characters or fewer.');
    Http::assertNothingSent();
});

it('trims whitespace and accepts the maximum Unicode query length', function () {
    fakeSolar();
    $query = str_repeat('é', 200);
    $this->get('/search?'.http_build_query(['q' => ' '.$query.' ']))->assertOk()->assertDontSee('Please use a search');
    Http::assertSent(fn ($r) => str_contains($r->url(), '/exoplanets') && $r['q'] === $query);
    Http::assertSent(fn ($r) => str_contains($r->url(), '/search') && $r['q'] === $query);
});

it('labels limited results and carries the query into the full exoplanet catalogue', function () {
    $solar = array_map(fn ($i) => ['id' => 'object-'.$i, 'name' => 'Solar match '.$i], range(0, 20));
    $planets = array_map(fn ($i) => array_replace(exoplanetPayload(), ['id' => 'exo-'.$i, 'name' => 'Planet match '.$i]), range(0, 20));
    Http::fake(['*/search*' => Http::response(['results' => $solar]), '*/exoplanets*' => Http::response(['available' => true, 'results' => $planets])]);
    $this->get('/search?q=TRAPPIST-1')->assertOk()->assertSee('40 results shown')
        ->assertSee('Showing the first 20 solar-system matches')->assertSee('Browse all matching exoplanets')
        ->assertSee(route('exoplanets.index', ['q' => 'TRAPPIST-1']), false)
        ->assertDontSee('Solar match 20')->assertDontSee('Planet match 20');
});

it('escapes query and catalogue text', function () {
    $unsafe = '<script>alert(1)</script>';
    Http::fake([
        '*/search*' => Http::response(['results' => [['id' => 'object-safe', 'name' => $unsafe]]]),
        '*/exoplanets*' => Http::response(['available' => true, 'results' => [array_replace(exoplanetPayload(), ['name' => $unsafe, 'host_name' => $unsafe])]]),
    ]);
    $this->get('/search?'.http_build_query(['q' => $unsafe]))->assertOk()->assertSee($unsafe)->assertDontSee($unsafe, false);
});

it('does not claim a complete empty result when only one empty catalogue is available', function () {
    Http::fake(['*/search*' => Http::response(['results' => []]), '*/exoplanets*' => Http::response([], 503)]);
    $this->get('/search?q=nothing')->assertOk()->assertSee('Showing results from one catalogue')
        ->assertSee('No solar-system objects match')->assertDontSee('No matches for');
});

it('treats an invalid search envelope as unavailable while preserving exoplanets', function () {
    Http::fake(['*/search*' => Http::response(['error' => 'Catalogue unavailable']), '*/exoplanets*' => Http::response(['available' => true, 'results' => [exoplanetPayload()]])]);
    $this->get('/search?q=Proxima')->assertOk()->assertSee('Solar-system search is temporarily unavailable')
        ->assertSee('Proxima Cen b')->assertDontSee('No solar-system objects match');
});

it('preserves solar results when the exoplanet envelope or a record is malformed', function (array $payload) {
    Http::fake([
        '*/search*' => Http::response(['results' => [['id' => 'planet-saturn', 'name' => 'Saturn']]]),
        '*/exoplanets*' => Http::response($payload),
    ]);

    $this->get('/search?q=Saturn')->assertOk()->assertSee('Saturn')
        ->assertSee('Exoplanet search is temporarily unavailable')
        ->assertSee('Showing results from one catalogue')
        ->assertDontSee('No exoplanets or host systems match');
})->with([
    'missing results' => [['available' => true]],
    'string results' => [['available' => true, 'results' => 'unavailable']],
    'non-list results' => [['available' => true, 'results' => ['error' => 'unavailable']]],
    'non-record item' => [['available' => true, 'results' => [null]]],
    'missing host identity' => [['available' => true, 'results' => [['id' => 'exo-example', 'name' => 'Example b']]]],
    'empty identity' => [['available' => true, 'results' => [['id' => '', 'name' => 'Example b', 'host_id' => 'host-example']]]],
    'non-text identity' => [['available' => true, 'results' => [['id' => ['bad'], 'name' => 'Example b', 'host_id' => 'host-example']]]],
    'non-text optional field' => [['available' => true, 'results' => [['id' => 'exo-example', 'name' => 'Example b', 'host_id' => 'host-example', 'discovery_method' => ['bad']]]]],
    'non-array measurements' => [['available' => true, 'results' => [['id' => 'exo-example', 'name' => 'Example b', 'host_id' => 'host-example', 'source_data' => 'bad']]]],
    'non-boolean availability' => [['available' => 'false', 'results' => []]],
]);

it('accepts exoplanet records without optional scientific metadata', function () {
    Http::fake([
        '*/search*' => Http::response(['results' => []]),
        '*/exoplanets*' => Http::response(['available' => true, 'results' => [[
            'id' => 'exo-example', 'name' => 'Example b', 'host_id' => 'host-example',
            'host_name' => null, 'source_data' => null, 'distance_pc' => null,
        ]]]),
    ]);

    $this->get('/search?q=example')->assertOk()->assertSee('Example b')
        ->assertDontSee('temporarily unavailable')->assertDontSee('Host system:');
});

it('preserves literal search URL strings instead of JSON coercing them', function (string $query) {
    fakeSolar();
    Livewire::withQueryParams(['q' => $query])->test(SearchPage::class)
        ->assertSet('q', $query)->assertSee('value="'.$query.'"', false);
    Http::assertSent(fn ($r) => str_contains($r->url(), '/search') && $r['q'] === $query);
})->with(['true', 'false', 'null', '123', '0']);

it('rejects malformed raw search URLs without searching another value', function () {
    $this->get('/search?'.http_build_query(['q' => ['Saturn']]))->assertOk()
        ->assertSee('Enter a single search term.')->assertSee('aria-invalid="true"', false);
    Http::assertNothingSent();
});

it('handles malformed search updates and recovers when corrected', function (mixed $value) {
    $component = Livewire::test(SearchPage::class)->set('q', $value)
        ->assertSee('Enter a single search term.')->assertDontSee('No matches for');
    Http::assertNothingSent();
    fakeSolar();
    $component->set('q', 'Ceres')->assertSee('Ceres')->assertDontSee('Enter a single search term.');
})->with([[['Saturn']], [true], [false], [42], [1.5]]);

it('treats a null search update as clearing the search', function () {
    Livewire::test(SearchPage::class)->set('q', null)->assertSet('q', '')->assertSee('Start typing');
    Http::assertNothingSent();
});

it('publishes the validated native title for each live search update', function () {
    fakeSolar();
    $brand = config('site.name');
    Livewire::withQueryParams(['q' => 'Saturn'])->test(SearchPage::class)
        ->assertDispatched('search-title-updated', title: "Search: Saturn · {$brand}")
        ->set('q', ' TRAPPIST-1 ')
        ->assertDispatched('search-title-updated', title: "Search: TRAPPIST-1 · {$brand}")
        ->set('q', '')
        ->assertDispatched('search-title-updated', title: "Search · {$brand}")
        ->set('q', str_repeat('x', 201))
        ->assertDispatched('search-title-updated', title: "Search · {$brand}")
        ->set('q', ['Saturn'])
        ->assertDispatched('search-title-updated', title: "Search · {$brand}");
    $unsafe = '<script>alert(1)</script>';
    Livewire::test(SearchPage::class)->set('q', $unsafe)
        ->assertDispatched('search-title-updated', title: "Search: {$unsafe} · {$brand}");
    $this->get('/search?q=Saturn')->assertSee("<title>Search: Saturn · {$brand}</title>", false);
});

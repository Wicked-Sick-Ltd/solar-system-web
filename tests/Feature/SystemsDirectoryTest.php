<?php

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['cache.default' => 'array']);
    Cache::flush();
    Http::swap(new Factory);
    Http::preventStrayRequests();
});

/** @return array<string,mixed> */
function directoryHost(int $id, ?string $name = null, ?float $distance = null): array
{
    return array_replace(exoplanetHostPayload(), ['id' => sprintf('host-%03d', $id),
        'name' => $name ?? sprintf('System %03d', $id), 'distance_pc' => $distance ?? (float) $id,
        'planet_count' => 2]);
}

/** @param list<array<string,mixed>> $rows @param array<string,mixed> $metadata */
function fakeDirectory(array $rows, array $metadata = []): void
{
    Http::fake(['*/galaxy' => Http::response(['available' => true, 'results' => $rows] + $metadata)]);
}

it('offers guest native GET browsing beyond the nearest twenty with deterministic distance pages', function () {
    $hosts = array_map(fn ($i) => directoryHost($i), range(1, 50));
    fakeDirectory(array_reverse($hosts), ['unmapped_hosts' => 7, 'truncated' => false]);
    $this->get('/systems')->assertOk()->assertSee('method="get"', false)->assertSee('action="'.route('systems.index').'"', false)
        ->assertSeeInOrder(['System 001', 'System 002', 'System 024'])->assertDontSee('System 025')
        ->assertSee('50 matching hosts')->assertSee('page 1 of 3')
        ->assertSee(route('systems.show', 'host-024'))->assertSee(route('galaxy', ['host' => 'host-024']))
        ->assertSee(route('systems.index', ['radius' => 'all', 'order' => 'distance', 'page' => 2]))
        ->assertSee('7 catalogue hosts lack')->assertSee('https://exoplanetarchive.ipac.caltech.edu/docs/PSCompPars.html', false)->assertDontSee('wire:model', false)->assertDontSee('wire:click', false);
    $this->get('/systems?page=2')->assertOk()->assertSee('System 025')->assertSee('System 048')->assertDontSee('System 024')->assertDontSee('System 049');
    $this->get('/systems?page=3')->assertOk()->assertSee('System 049')->assertSee('System 050')->assertDontSee('rel="next"', false);
    Http::assertSentCount(1); // Local selection shares the cached map dataset.
});

it('filters literal case-insensitive names and inclusive distance boundaries together', function () {
    fakeDirectory([directoryHost(1, 'Alpha %_"', 25), directoryHost(2, 'ALPHA %_" nearby', 24.999), directoryHost(3, 'Alpha %_" far', 25.001), directoryHost(4, 'Alpha plain', 5)]);
    $this->get('/systems?'.http_build_query(['q' => ' %_" ', 'radius' => '25', 'order' => 'name']))->assertOk()
        ->assertSee('2 matching hosts')->assertSee('Alpha %_"')->assertSee('ALPHA %_" nearby')
        ->assertDontSee('Alpha %_" far')->assertDontSee('Alpha plain');
});

it('uses stable ID tie breakers for both distance and case-folded name ordering', function () {
    fakeDirectory([directoryHost(3, 'alpha', 10), directoryHost(2, 'Beta', 10), directoryHost(1, 'ALPHA', 10)]);
    $this->get('/systems')->assertOk()->assertSeeInOrder([route('systems.show', 'host-001'), route('systems.show', 'host-002'), route('systems.show', 'host-003')]);
    $this->get('/systems?order=name')->assertOk()->assertSeeInOrder([route('systems.show', 'host-001'), route('systems.show', 'host-003'), route('systems.show', 'host-002')]);
});

it('retains all validated filters in previous and next page links', function () {
    fakeDirectory(array_map(fn ($i) => directoryHost($i, 'Alpha '.$i), range(1, 60)));
    $this->get('/systems?q=Alpha&radius=100&order=name&page=2')->assertOk()
        ->assertSee(route('systems.index', ['q' => 'Alpha', 'radius' => '100', 'order' => 'name', 'page' => 1]))
        ->assertSee(route('systems.index', ['q' => 'Alpha', 'radius' => '100', 'order' => 'name', 'page' => 3]))
        ->assertSee('value="Alpha"', false)->assertSee('value="100" selected', false)->assertSee('value="name" selected', false);
});

it('shows an explicit page recovery instead of an empty out-of-range result', function () {
    fakeDirectory([directoryHost(1, 'Alpha')]);
    $this->get('/systems?q=Alpha&radius=25&order=name&page=2')->assertStatus(422)
        ->assertSee('Page 2 is outside these results')->assertSee('First page with these filters')
        ->assertSee(route('systems.index', ['q' => 'Alpha', 'radius' => '25', 'order' => 'name', 'page' => 1]))
        ->assertDontSee('No measured hosts match');
});

it('distinguishes empty returned-sample searches from an outage and reports truncation', function () {
    fakeDirectory([directoryHost(1)], ['truncated' => true, 'unmapped_hosts' => 4]);
    $this->get('/systems?q=missing')->assertOk()->assertSee('No measured hosts match these filters in the returned sample')
        ->assertSee('map response is truncated')->assertSee('4 catalogue hosts lack')->assertSee('not to all stars or all exoplanet hosts');
    Cache::flush();
    Http::swap(new Factory);
    Http::fake(['*/galaxy' => Http::response([], 503)]);
    $this->get('/systems')->assertStatus(503)->assertSee('temporarily unavailable')->assertDontSee('No measured hosts match');
});

it('reports omitted unmapped counts as unknown rather than zero', function () {
    fakeDirectory([directoryHost(1)]);
    $this->get('/systems')->assertOk()->assertSee('without a usable measured position is unknown')
        ->assertDontSee('0 catalogue hosts lack');
});

it('preserves known one-sided tiny uncertainties and treats missing sides as unknown', function () {
    $host = directoryHost(1);
    $host['distance_error_plus_pc'] = 3.5e-9;
    $host['distance_error_minus_pc'] = null;
    fakeDirectory([$host]);
    $this->get('/systems')->assertOk()->assertSee('+3.5 × 10⁻⁹ pc')->assertSee('lower:')->assertSee('unknown')
        ->assertSee('Recorded planets')->assertSee('light-years');
});

it('preserves a measured zero uncertainty without inventing an unknown upper error', function () {
    $host = directoryHost(1);
    $host['distance_error_plus_pc'] = null;
    $host['distance_error_minus_pc'] = 0;
    fakeDirectory([$host]);
    $this->get('/systems')->assertOk()->assertSee('Upper:')->assertSee('unknown')->assertSee('−0 pc');
});

it('accepts the maximum query length and treats boolean-looking text literally', function () {
    fakeDirectory([directoryHost(1, 'true'), directoryHost(2, str_repeat('é', 200))]);
    $this->get('/systems?q=true')->assertOk()->assertSee('1 matching host')->assertSee('true')->assertDontSee('host-002');
    $this->get('/systems?'.http_build_query(['q' => str_repeat('é', 200)]))->assertOk()->assertSee('1 matching host')->assertSee('host-002');
});

it('rejects malformed raw filters before making any API request', function (array $query) {
    $this->get('/systems?'.http_build_query($query))->assertStatus(422)->assertSee('Correct the filters above')
        ->assertDontSee('No measured hosts match');
    Http::assertNothingSent();
})->with([
    [['q' => ['x']]], [['q' => str_repeat('é', 201)]], [['radius' => ['25']]], [['radius' => '50']],
    [['order' => ['name']]], [['order' => 'random']], [['page' => ['2']]], [['page' => 'abc']],
    [['page' => '1.5']], [['page' => 'true']], [['page' => '0']], [['page' => '10001']], [['page' => '']],
]);

it('escapes names and query text without relying on JavaScript', function () {
    $unsafe = '<script>alert("x")</script>';
    fakeDirectory([directoryHost(1, $unsafe)]);
    $this->get('/systems?'.http_build_query(['q' => $unsafe]))->assertOk()->assertSee($unsafe)->assertDontSee($unsafe, false);
});

it('handles an empty measured sample honestly and preserves detail routing', function () {
    fakeDirectory([]);
    $this->get('/systems')->assertOk()->assertSee('0 hosts returned by the map')->assertSee('No measured hosts match');
    $this->get('/systems?page=2')->assertStatus(422)->assertSee('outside these results');
    Http::swap(new Factory);
    fakeSolar();
    $this->get('/systems/host-proxima')->assertOk()->assertSee('Proxima Cen');
});

it('distinguishes unknown sample limits from an explicitly complete returned map', function () {
    fakeDirectory([directoryHost(1)]);
    $this->get('/systems')->assertOk()->assertSee('does not say whether the map sample was limited');
    Cache::flush();
    Http::swap(new Factory);
    fakeDirectory([directoryHost(1)], ['truncated' => false]);
    $this->get('/systems')->assertOk()->assertDontSee('does not say whether the map sample was limited')->assertDontSee('map response is truncated');
});

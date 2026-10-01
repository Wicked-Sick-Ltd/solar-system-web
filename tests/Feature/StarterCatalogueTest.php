<?php

declare(strict_types=1);

use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Services\SolarApi\StarterCatalogueClient;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function starterFixture(): array
{
    return json_decode(file_get_contents(base_path('tests/fixtures/starter-catalogue.json')), true, flags: JSON_THROW_ON_ERROR);
}

function resetStarterHttp(): void
{
    Http::swap(new Factory);
    Http::preventStrayRequests();
}

function fakeStarter(?array $fixture = null): void
{
    resetStarterHttp();
    $fixture ??= starterFixture();
    Http::fake(function ($request) use ($fixture) {
        $path = rawurldecode(parse_url($request->url(), PHP_URL_PATH));
        if (str_contains($path, '/starter-targets/')) {
            $id = basename($path);
            foreach ($fixture['results'] as $row) {
                if ($row['id'] === $id) {
                    return Http::response($row + ['provenance' => $fixture['sources'][$row['source']]]);
                }
            }

            return Http::response([], 404);
        }
        if (str_ends_with($path, '/starter-targets')) {
            $rows = array_values(array_filter($fixture['results'], fn ($row) => (! ($request['family'] ?? '') || in_array($request['family'], $row['families'], true))
                && (! $request['q'] || collect([$row['id'], $row['name'], ...$row['aliases']])->contains(fn ($text) => str_contains(mb_strtolower($text), mb_strtolower($request['q']))))));

            return Http::response(array_replace($fixture, ['results' => array_slice($rows, (int) $request['offset'], (int) $request['limit']), 'total' => count($rows), 'offset' => (int) $request['offset'], 'limit' => (int) $request['limit']]));
        }

        return Http::response([], 503);
    });
}

beforeEach(function () {
    config(['cache.default' => 'array']);
    Cache::flush();
    Http::preventStrayRequests();
    fakeStarter();
});

it('renders sourced guest catalogue and exact detail routes without JavaScript', function () {
    $this->get('/observing-targets')->assertOk()->assertSee('50 bright-star records')->assertSee('CC BY-SA 4.0')
        ->assertSee('method="get"', false)->assertSee('name="family"', false)->assertSee('HR 2491')
        ->assertSee(route('observing-targets.show', 'bsc5p:hr2491'), false);
    $this->get('/observing-targets/bsc5p:hr2491')->assertOk()->assertSee('11.2 arcsec')->assertSee('Not reported')
        ->assertSee('Separation measurement epoch')->assertSee('no motion correction applied')->assertSee('Original source fields');
    $this->get('/observing-targets/openngc:NGC0224')->assertOk()->assertSee('M 31')->assertSee('share adaptations under the same licence')->assertSee('Reviewed subset SHA256');
});

it('keeps filtering native, scoped and selected', function () {
    $this->get('/observing-targets?q=HR%202491&family=double_star')->assertOk()->assertSee('HR 2491')->assertDontSee('NGC0224')
        ->assertSee('value="double_star" selected', false);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/starter-targets') && $request['q'] === 'HR 2491' && $request['family'] === 'double_star' && $request['limit'] === 24);
});

it('rejects raw malformed filters before any catalogue request', function (string $query) {
    $this->get('/observing-targets?'.$query)->assertStatus(422)->assertSee('Correct the filters');
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/starter-targets'));
})->with(['q[]=bad', 'family[]=bad', 'family=true', 'page[]=1', 'page=true', 'page=2.0', 'page=-1', 'page=43', 'page=1e1']);

it('preserves literal query strings and reports genuine empty sample matches', function () {
    $this->get('/observing-targets?q=true')->assertOk()->assertSee('No records match in this starter sample')->assertSee('value="true"', false);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/starter-targets') && $request['q'] === 'true');
});

it('provides first-page recovery without silently changing the page', function () {
    $this->get('/observing-targets?q=HR&family=bright_star&page=2')->assertStatus(422)
        ->assertSee('outside the current results')->assertSee('First page with these filters')
        ->assertSee(e(route('observing-targets.index', ['q' => 'HR', 'family' => 'bright_star', 'page' => 1])), false);
});

it('has deterministic native pagination links preserving the query and family', function () {
    $fixture = starterFixture();
    $template = $fixture['results'][0];
    $fixture['results'] = [];
    for ($i = 1; $i <= 25; $i++) {
        $fixture['results'][] = array_replace($template, ['id' => 'bsc5p:hr'.$i, 'name' => 'HR test '.$i]);
    }
    fakeStarter($fixture);
    $first = $this->get('/observing-targets?q=HR&family=bright_star')->assertOk();
    $next = route('observing-targets.index', ['q' => 'HR', 'family' => 'bright_star', 'page' => 2]);
    $first->assertSee(e($next), false)->assertSee('rel="next"', false);
    $this->get($next)->assertOk()->assertSee('page 2 of 2')->assertSee('HR test 25')->assertDontSee('HR test 1</a>', false);
});

it('distinguishes unknown records from an unavailable backend', function () {
    $this->get('/observing-targets/bsc5p:hr9999')->assertNotFound()->assertSee('Record not found');
    Cache::flush();
    resetStarterHttp();
    Http::fake(['*' => Http::response([], 404)]);
    $this->get('/observing-targets/bsc5p:hr9999')->assertStatus(503)->assertDontSee('Record not found');
});

it('rejects malformed source data as unavailable and does not cache failures', function (Closure $alter) {
    $fixture = $alter(starterFixture());
    resetStarterHttp();
    Http::fake(['*starter-targets*' => Http::response($fixture)]);
    $this->get('/observing-targets')->assertStatus(503)->assertDontSee('No records match');
    fakeStarter();
    $this->get('/observing-targets')->assertOk();
})->with([
    'missing results' => fn ($f) => tap($f, function (&$v) {
        unset($v['results']);
    }),
    'unavailable' => fn ($f) => array_replace($f, ['available' => false]),
    'scalar results' => fn ($f) => array_replace($f, ['results' => 'bad']),
    'unreported truncated page' => fn ($f) => array_replace($f, ['total' => 7]),
    'out-of-range position angle' => fn ($f) => tap($f, function (&$v) {
        $v['results'][0]['position_angle_deg'] = 450;
    }),
    'bad coordinate' => fn ($f) => tap($f, function (&$v) {
        $v['results'][0]['ra_deg'] = 'NaN';
    }),
    'wrong source' => fn ($f) => tap($f, function (&$v) {
        $v['results'][0]['source'] = 'other';
    }),
    'bad raw field' => fn ($f) => tap($f, function (&$v) {
        $v['results'][0]['source_data']['hr'] = [];
    }),
    'source link script' => fn ($f) => tap($f, function (&$v) {
        $v['sources']['openngc']['source_url'] = 'javascript:alert(1)';
    }),
    'missing source' => fn ($f) => tap($f, function (&$v) {
        unset($v['sources']['openngc']);
    }),
    'missing licence' => fn ($f) => tap($f, function (&$v) {
        unset($v['sources']['bsc5p']['license']);
    }),
]);

it('escapes source strings and preserves tiny positive numbers and missing values', function () {
    $fixture = starterFixture();
    $row = &$fixture['results'][0];
    $row['name'] = '<script>alert(1)</script>';
    $row['magnitude'] = null;
    $row['major_axis_arcmin'] = 0.00000001;
    fakeStarter($fixture);
    $this->get('/observing-targets/'.$row['id'])->assertOk()->assertSee('&lt;script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false)->assertSee('10⁻⁸')->assertSee('Not reported');
});

it('caches only validated public records and scopes keys to the configured backend', function () {
    $api = app(StarterCatalogueClient::class);
    $api->catalogue();
    $api->catalogue();
    Http::assertSentCount(1);
    config(['services.solar.base_url' => 'https://other.test/api/v1']);
    $api->catalogue();
    Http::assertSentCount(2);
});

it('does not follow upstream redirects', function () {
    resetStarterHttp();
    Http::fake(['*' => Http::response('', 302, ['Location' => 'https://other.test'])]);
    expect(fn () => app(StarterCatalogueClient::class)->catalogue())->toThrow(SolarApiException::class);
    Http::assertSentCount(1);
});

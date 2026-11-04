<?php

declare(strict_types=1);

use App\Livewire\Orrery;
use App\Support\OrreryQuery;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(fn () => fakeSolar());

it('clamps the orrery query to one real date and drops everything else', function () {
    expect(OrreryQuery::clamp([
        'date' => '2026-06-01',
        'zoom' => '40',
        'utm_source' => 'screaming-frog',
    ]))->toBe(['date' => '2026-06-01'])
        ->and(OrreryQuery::clamp(['zoom' => '999', 'fbclid' => 'abc']))->toBe([])
        ->and(OrreryQuery::clamp(['date' => '2026-02-30']))->toBe([])
        ->and(OrreryQuery::clamp(['date' => '2026-02-29']))->toBe([])
        ->and(OrreryQuery::clamp(['date' => '0000-01-01']))->toBe([])
        ->and(OrreryQuery::clamp(['date' => ['2026-06-01']]))->toBe([])
        ->and(OrreryQuery::clamp(['date' => '2028-02-29']))->toBe(['date' => '2028-02-29']);
});

it('redirects unknown or impossible orrery queries to the clamped url', function (string $query, string $target) {
    $this->get('/orrery?'.$query)
        ->assertStatus(301)
        ->assertRedirect($target);
    Http::assertNothingSent();
})->with([
    'unknown key' => ['date=2026-06-01&zoom=9&utm_source=screaming-frog', '/orrery?date=2026-06-01'],
    'zoom only' => ['zoom=4', '/orrery'],
    'duplicate date' => ['date=2026-01-01&date=2026-06-01', '/orrery?date=2026-06-01'],
    'encoded hyphen' => ['date=2026%2D06%2D01', '/orrery?date=2026-06-01'],
    'impossible day' => ['date=2026-02-30', '/orrery'],
    'non-leap day' => ['date=2026-02-29', '/orrery'],
    'relative date' => ['date=tomorrow', '/orrery'],
    'unpadded date' => ['date=2026-2-03', '/orrery'],
    'year zero' => ['date=0000-01-01', '/orrery'],
    'five-digit year' => ['date=10000-01-01', '/orrery'],
    'timestamp' => ['date=2026-01-01T10%3A00%3A00Z', '/orrery'],
    'empty date' => ['date=', '/orrery'],
    'array date' => ['date%5B%5D=2026-01-01', '/orrery'],
    'nested date' => ['date%5Bfoo%5D=2026-01-01', '/orrery'],
    'boolean date' => ['date=true', '/orrery'],
]);

it('keeps a bare orrery indexable and a dated one canonical but noindex', function () {
    $canonical = '<link rel="canonical" href="'.url('/orrery').'">';

    $bare = $this->get('/orrery')->assertOk();
    $bare->assertSee($canonical, escape: false)
        ->assertSee('<meta name="robots" content="index, follow, max-image-preview:large">', escape: false)
        ->assertDontSee('noindex', false);
    expect(orreryQueryHrefs($bare->getContent()))->toBe([]);

    $dated = $this->get('/orrery?date=2026-06-01')->assertOk();
    $dated->assertSee($canonical, escape: false)
        ->assertSee('<meta name="robots" content="noindex, follow">', escape: false)
        ->assertSee('wire:click="step(-1)"', escape: false)
        ->assertSee('wire:click="step(1)"', escape: false)
        ->assertDontSee('/orrery?date=2026-05-31', false)
        ->assertDontSee('/orrery?date=2026-06-02', false);
    expect(orreryQueryHrefs($dated->getContent()))->toBe([]);
});

it('uses the exact leap date and supplies native date and stepping controls', function () {
    $this->get('/orrery?date=2028-02-29')->assertOk()
        ->assertSee('name="date" type="date" value="2028-02-29"', escape: false)
        ->assertSee('method="get"', escape: false)
        ->assertSee('Apply date')
        ->assertSee('wire:click="step(-1)"', escape: false)
        ->assertSee('wire:click="step(1)"', escape: false)
        ->assertSee('wire:click="step(-30)"', escape: false)
        ->assertSee('wire:click="step(30)"', escape: false)
        ->assertDontSee(route('orrery', ['date' => '2028-02-28']), escape: false)
        ->assertDontSee(route('orrery', ['date' => '2028-03-01']), escape: false)
        ->assertSee('Back 30 days')
        ->assertSee('Plotted bodies and distances from the Sun');
    Http::assertSent(fn ($request) => str_contains($request->url(), '/positions/') && $request['date'] === '2028-02-29');
});

it('does not overflow the supported year range when stepping', function () {
    Livewire::withQueryParams(['date' => '0001-01-01'])->test(Orrery::class)
        ->call('step', -1)->assertSet('date', '0001-01-01');
    Livewire::withQueryParams(['date' => '9999-12-31'])->test(Orrery::class)
        ->call('step', 1)->assertSet('date', '9999-12-31');
});

it('rejects invalid reactive values and recovers when a real date is selected', function () {
    Cache::put('solar:health', true, 60);
    $component = Livewire::test(Orrery::class);
    $component->set('date', ['2026-01-01'])->assertSee('Choose a valid date');
    $component->set('date', '2026-02-30')->call('step', 1)->assertSet('date', '2026-02-30')
        ->assertSee('Choose a valid date');
    $component->set('date', '2026-03-01')->assertDontSee('Choose a valid date')->assertSee('1 March 2026');
});

it('labels omitted positions and excludes nonphysical or missing distance data from the plot', function () {
    Cache::put('solar:health', true, 60);
    Http::swap(new Factory);
    Http::fake(function ($request) {
        $id = basename(parse_url($request->url(), PHP_URL_PATH));
        $value = ['x_au' => 1, 'y_au' => 0, 'distance_from_sun_au' => 1];

        return Http::response(match ($id) {
            'planet-earth' => $value,
            'planet-mars' => array_replace($value, ['distance_from_sun_au' => -1]),
            'planet-venus' => array_replace($value, ['distance_from_sun_au' => 0]),
            'planet-jupiter' => array_replace($value, ['distance_from_sun_au' => '1e309']),
            'planet-saturn' => array_replace($value, ['x_au' => null]),
            'planet-uranus' => array_replace($value, ['name' => []]),
            default => [],
        });
    });
    $this->get('/orrery?date=2026-03-01')->assertOk()
        ->assertSee('Showing 1 of 10 selected bodies.')
        ->assertSee('Positions unavailable for: Mercury, Venus, Mars, Jupiter, Saturn, Uranus, Neptune, Ceres, Pluto.')
        ->assertSee('orrery-planet-earth', escape: false)
        ->assertDontSee('orrery-planet-mars', escape: false)
        ->assertDontSee('r="NAN"', escape: false);
});

/** @return list<string> */
function orreryQueryHrefs(string $html): array
{
    preg_match_all('/<a\b[^>]*\bhref="([^"]*)"/i', $html, $matches);

    return array_values(array_filter(
        $matches[1],
        fn (string $href): bool => str_contains($href, '/orrery?'),
    ));
}

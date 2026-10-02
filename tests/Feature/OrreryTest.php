<?php

declare(strict_types=1);

use App\Livewire\Orrery;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(fn () => fakeSolar());

it('rejects malformed dates without requesting or plotting a different date', function (string $query) {
    $this->get('/orrery?'.$query)->assertOk()
        ->assertSee('Choose a valid date')
        ->assertSee('No positions were requested.')
        ->assertDontSee('aria-label="Plotted bodies', escape: false);
    Http::assertNothingSent();
})->with([
    'date=2026-02-30', 'date=tomorrow', 'date=2026-2-03', 'date=0000-01-01',
    'date=10000-01-01', 'date=2026-01-01T10%3A00%3A00Z', 'date=',
    'date%5B%5D=2026-01-01', 'date%5Bfoo%5D=2026-01-01', 'date=true',
]);

it('uses the exact leap date and supplies native date and stepping controls', function () {
    $this->get('/orrery?date=2028-02-29')->assertOk()
        ->assertSee('name="date" type="date" value="2028-02-29"', escape: false)
        ->assertSee('method="get"', escape: false)
        ->assertSee('Apply date')
        ->assertSee(route('orrery', ['date' => '2028-02-28']), escape: false)
        ->assertSee(route('orrery', ['date' => '2028-03-01']), escape: false)
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

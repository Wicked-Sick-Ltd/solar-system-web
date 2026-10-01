<?php

declare(strict_types=1);

use App\Livewire\SkyObserver;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo('2026-09-16 00:00:00');
    RateLimiter::clear('w3w:127.0.0.1');
    fakeSolar();
});

it('starts idle with a call to action', function () {
    Livewire::test(SkyObserver::class, ['objectId' => 'planet-saturn'])
        ->assertSee('Calculate sky positions for my location')
        ->assertDontSee('Altitude');
});

it('renders the observer view for a location', function () {
    Livewire::test(SkyObserver::class, ['objectId' => 'planet-saturn'])
        ->call('setLocation', 51.5, -0.12)
        ->assertSet('lat', 51.5)
        ->assertSee('Altitude')
        ->assertSee('Up now')
        ->assertSee('WNW')
        ->assertSee('Hourly weather forecast')
        ->assertSee('18%')
        ->assertSee('Clear')
        ->assertSee('High dew risk')
        ->assertDontSee('Get the kit out')->assertDontSee('Kit-ready nudge:')
        ->assertSee('Forget my location');
});

it('rejects impossible coordinates', function () {
    Livewire::test(SkyObserver::class, ['objectId' => 'planet-saturn'])
        ->call('setLocation', 123.0, 0.0)
        ->assertHasErrors(['lat']);
});

it('forgets the location', function () {
    Livewire::test(SkyObserver::class, ['objectId' => 'planet-saturn'])
        ->call('setLocation', 51.5, -0.12)
        ->set('text', '51.5, -0.12')->call('forget')
        ->assertSet('lat', null)->assertSet('text', '')
        ->assertSee('Calculate sky positions for my location');
});

it('accepts pasted coordinates in any common form', function () {
    Livewire::test(SkyObserver::class, ['objectId' => 'planet-saturn'])
        ->call('setFromText', 'https://www.google.com/maps/@51.5074,-0.1278,15z')
        ->assertSet('lat', 51.51)
        ->assertSet('lon', -0.13)
        ->assertSee('Altitude');
});

it('explains when pasted text is not a location', function () {
    Livewire::test(SkyObserver::class, ['objectId' => 'planet-saturn'])
        ->call('setFromText', 'London')
        ->assertHasErrors(['text'])
        ->assertSee('couldn\'t read that');
});

it('resolves a what3words address when a key is configured', function () {
    config(['services.what3words.key' => 'TESTKEY1']);
    Http::fake([
        'api.what3words.com/*' => Http::response([
            'words' => 'filled.count.soap', 'coordinates' => ['lng' => -0.195521, 'lat' => 51.520847],
        ]),
    ]);

    Livewire::test(SkyObserver::class, ['objectId' => 'planet-saturn'])
        ->call('setFromText', '///filled.count.soap')
        ->assertSet('lat', 51.52)
        ->assertSet('lon', -0.2);
});

it('caps what3words lookups from one address — nothing is cached to absorb repeats', function () {
    config(['services.what3words.key' => 'TESTKEY1']);
    Http::fake([
        'api.what3words.com/*' => Http::response([
            'words' => 'filled.count.soap', 'coordinates' => ['lng' => -0.195521, 'lat' => 51.520847],
        ]),
    ]);

    $component = Livewire::test(SkyObserver::class, ['objectId' => 'planet-saturn']);
    foreach (range(1, 20) as $ignored) {
        $component->call('setFromText', '///filled.count.soap');
    }

    $component->call('setFromText', '///filled.count.soap')
        ->assertHasErrors(['text'])
        ->assertSee('Too many what3words lookups');

    expect(Http::recorded(fn ($request) => str_contains($request->url(), 'what3words.com')))->toHaveCount(20);
});

it('hides the what3words hint and refuses the address when no key is configured', function () {
    config(['services.what3words.key' => null]);
    Livewire::test(SkyObserver::class, ['objectId' => 'planet-saturn'])
        ->assertDontSee('what3words')
        ->call('setFromText', '///filled.count.soap')
        ->assertHasErrors(['text']);
});

it('shows the Google Maps guide', function () {
    Livewire::test(SkyObserver::class, ['objectId' => 'planet-saturn'])
        ->assertSee('How do I find my coordinates?')
        ->assertSee('Right-click');
});

it('degrades gracefully when weather is unavailable', function () {
    // Replace the shared solar fake outright: stubs registered later never take
    // precedence, and that one already answers the forecast with good weather.
    Http::swap(new Factory);
    Http::fake(function ($request) {
        $url = $request->url();
        $path = parse_url($url, PHP_URL_PATH) ?? '';

        if (str_contains($path, '/v1/forecast')) {
            return Http::response(['error' => 'down'], 503);
        }

        return str_contains($path, '/sky/')
            ? Http::response(skyPayload(observer: isset($request['lat'])))
            : Http::response(['results' => []]);
    });

    Livewire::test(SkyObserver::class, ['objectId' => 'planet-saturn'])
        ->call('setLocation', 51.5, -0.12)
        ->assertSee('Altitude')
        ->assertSee('Hourly weather forecast unavailable');
});

it('does not query sky or weather for malformed public coordinate state', function (mixed $lat, mixed $lon) {
    Livewire::test(SkyObserver::class, ['objectId' => 'planet-saturn'])
        ->set(['lat' => $lat, 'lon' => $lon])
        ->assertSee('No sky or weather request was made')
        ->assertDontSee('Altitude');
    Http::assertNothingSent();
})->with([
    'latitude range' => [91, 0], 'longitude range' => [0, -181],
    'nonfinite' => ['1e309', 0], 'array' => [[51.5], 0],
    'boolean' => [true, 0], 'nonnumeric' => ['north', 0], 'partial' => [null, 0],
]);

it('recovers a failed calculation on refresh without changing location', function () {
    Http::swap(new Factory);
    Http::fake([
        '*/sky/*' => Http::sequence()->push([], 503)->push(skyPayload(observer: true)),
        '*' => Http::response(openMeteoPayload()),
    ]);
    Livewire::test(SkyObserver::class, ['objectId' => 'planet-saturn'])
        ->call('setLocation', 51.5, -0.12)->assertSet('failed', true)->assertDontSee('Altitude')
        ->call('$refresh')->assertSet('failed', false)->assertSee('Altitude')->assertSee('Hourly weather forecast');
});

it('never turns clear weather into a claim of observability or a best observing hour', function (array $observer) {
    Http::swap(new Factory);
    $sky = skyPayload(observer: true);
    $sky['observer'] = array_replace($sky['observer'], $observer);
    Http::fake(['*/sky/*' => Http::response($sky), '*' => Http::response(openMeteoPayload())]);
    Livewire::test(SkyObserver::class, ['objectId' => 'planet-saturn'])
        ->call('setLocation', 51.5, -0.12)->assertSee('Clear')->assertSee('Forecast time')
        ->assertSee('Clear skies alone do not establish')
        ->assertDontSee('Get the kit out')->assertDontSee('is observable')->assertDontSee('Best hour')->assertDontSee("Tonight's outlook");
})->with([
    'never rises' => [['never_rises' => true, 'is_up' => false, 'rise_utc' => null, 'transit_utc' => null, 'set_utc' => null]],
    'daylight' => [['is_dark' => false, 'sun_altitude_deg' => 20]],
    'civil twilight' => [['is_dark' => false, 'sun_altitude_deg' => -4.6]],
    'circumpolar' => [['circumpolar' => true, 'rise_utc' => null, 'set_utc' => null]],
]);

it('returns only accepted rounded coordinates and no stale location after a bad paste', function () {
    Livewire::test(SkyObserver::class, ['objectId' => 'planet-saturn'])
        ->call('setLocation', 51.514, -0.124)->assertReturned(['lat' => 51.51, 'lon' => -0.12])
        ->call('setFromText', 'not a location')->assertReturned(null)->assertHasErrors(['text'])
        ->assertSee('aria-describedby="observer-text-error', escape: false)
        ->call('setLocation', 51.5, -0.12)->assertHasNoErrors()->assertSee('Altitude')
        ->call('setFromText', '51.5, -0.12')->assertReturned(['lat' => 51.5, 'lon' => -0.12]);
});

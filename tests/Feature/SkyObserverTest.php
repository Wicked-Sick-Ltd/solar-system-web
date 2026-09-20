<?php

declare(strict_types=1);

use App\Livewire\SkyObserver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    RateLimiter::clear('w3w:127.0.0.1');
    fakeSolar();
});

it('starts idle with a call to action', function () {
    Livewire::test(SkyObserver::class, ['objectId' => 'planet-saturn'])
        ->assertSee('Get precise data for my location')
        ->assertDontSee('Altitude');
});

it('renders the observer view for a location', function () {
    Livewire::test(SkyObserver::class, ['objectId' => 'planet-saturn'])
        ->call('setLocation', 51.5, -0.12)
        ->assertSet('lat', 51.5)
        ->assertSee('Altitude')
        ->assertSee('Up now')
        ->assertSee('WNW')
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
        ->call('forget')
        ->assertSet('lat', null)
        ->assertSee('Get precise data for my location');
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

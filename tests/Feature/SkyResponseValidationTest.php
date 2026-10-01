<?php

declare(strict_types=1);

use App\Livewire\SkyObserver;
use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Services\SolarApi\SolarApiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    config(['cache.default' => 'array']);
    Cache::flush();
});

it('rejects malformed observer responses instead of inventing a visibility status', function (string $field, mixed $value) {
    $sky = skyPayload(observer: true);
    if ($field === 'observer') {
        $sky['observer'] = $value;
    } elseif ($field === 'name') {
        $sky['name'] = $value;
    } else {
        $sky['observer'][$field] = $value;
    }
    Http::fake(['*' => Http::response($sky)]);
    Livewire::test(SkyObserver::class, ['objectId' => 'planet-saturn'])
        ->call('setLocation', 51.5, -0.12)->assertSet('failed', true)
        ->assertSee('couldn\'t work that out')->assertDontSee('Below the horizon')->assertDontSee('Altitude');
    Http::assertSentCount(1);
})->with([
    ['observer', []], ['observer', null], ['observer', 'bad'],
    ['lat', null], ['lat', 91], ['lon', -181], ['lat', '1e309'],
    ['is_up', null], ['is_dark', 'false'], ['circumpolar', 0], ['never_rises', []],
    ['altitude_deg', null], ['azimuth_deg', 361], ['sun_altitude_deg', -91],
    ['rise_utc', "');alert(1);//"], ['transit_utc', '2026-02-30T10:00:00Z'], ['set_utc', []],
    ['name', []],
]);

it('requires valid observer coordinates before the API call', function () {
    Http::fake();
    expect(fn () => app(SolarApiClient::class)->sky('planet-saturn', null, 91, 0))->toThrow(SolarApiException::class);
    expect(fn () => app(SolarApiClient::class)->sky('planet-saturn', null, null, 0))->toThrow(SolarApiException::class);
    Http::assertNothingSent();
});

it('keeps unknown event times and geocentric responses without an observer valid', function () {
    $sky = skyPayload(observer: true);
    $sky['observer']['rise_utc'] = null;
    $sky['observer']['set_utc'] = null;
    $sky['observer']['circumpolar'] = true;
    Http::fake(['*' => Http::response($sky)]);
    $result = app(SolarApiClient::class)->sky('planet-saturn', null, 51.5, -0.12);
    expect($result->observer->riseUtc)->toBeNull()->and($result->observer->circumpolar)->toBeTrue();
});

it('accepts a normal geocentric sky response', function () {
    Http::fake(['*' => Http::response(skyPayload())]);
    expect(app(SolarApiClient::class)->sky('planet-saturn')->observer)->toBeNull();
});

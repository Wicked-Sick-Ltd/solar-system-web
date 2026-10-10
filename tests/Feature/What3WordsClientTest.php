<?php

declare(strict_types=1);

use App\Services\What3Words\What3WordsClient;
use App\Services\What3Words\What3WordsException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.what3words.key' => 'TESTKEY1', 'cache.default' => 'array']);
    Cache::flush();
});

it('is disabled without a key', function () {
    config(['services.what3words.key' => null]);
    expect(app(What3WordsClient::class)->enabled())->toBeFalse();
});

it('converts an address to rounded coordinates', function () {
    Http::fake(['api.what3words.com/*' => Http::response([
        'country' => 'GB', 'words' => 'filled.count.soap',
        'coordinates' => ['lng' => -0.195521, 'lat' => 51.520847], 'language' => 'en',
    ])]);

    expect(app(What3WordsClient::class)->toCoordinates('filled.count.soap'))
        ->toBe(['lat' => 51.52, 'lon' => -0.2]);

    Http::assertSent(fn ($r) => str_contains($r->url(), 'convert-to-coordinates')
        && $r['words'] === 'filled.count.soap'
        && $r->hasHeader('X-Api-Key', 'TESTKEY1'));
});

it('never caches a lookup — an address someone looked up is a record we promised not to keep', function () {
    Http::fake(['api.what3words.com/*' => Http::response([
        'words' => 'filled.count.soap', 'coordinates' => ['lng' => -0.195521, 'lat' => 51.520847],
    ])]);
    $client = app(What3WordsClient::class);
    $client->toCoordinates('filled.count.soap');
    $client->toCoordinates('filled.count.soap');
    Http::assertSentCount(2);
});

it('reports an unknown address as such', function () {
    Http::fake(['api.what3words.com/*' => Http::response([
        'error' => ['code' => 'BadWords', 'message' => 'words not recognised'],
    ], 400)]);
    expect(fn () => app(What3WordsClient::class)->toCoordinates('not.real.words'))
        ->toThrow(What3WordsException::class, 'recognised');
});

it('reports an outage distinctly from an unknown address', function () {
    Http::fake(['api.what3words.com/*' => Http::response(null, 503)]);
    expect(fn () => app(What3WordsClient::class)->toCoordinates('filled.count.soap'))
        ->toThrow(What3WordsException::class, 'unavailable');
});

it('caches an observatory locate and still leaves the sky-panel lookup uncached', function () {
    Http::fake(['api.what3words.com/*' => Http::response([
        'words' => 'filled.count.soap',
        'nearestPlace' => 'Bayswater, London',
        'coordinates' => ['lng' => -0.195521, 'lat' => 51.520847],
    ])]);
    $client = app(What3WordsClient::class);

    $place = $client->locate('///filled.count.soap');
    expect($client->locate('filled.count.soap'))->toBe($place)
        ->and($place['nearestPlace'])->toBe('Bayswater, London')
        ->and($place['roundedLatitude'])->toBe(51.52);

    $client->toCoordinates('filled.count.soap');
    $client->toCoordinates('filled.count.soap');

    Http::assertSentCount(3);
});

it('reads a cached reverse address without a second request', function () {
    Http::fake(['api.what3words.com/*' => Http::response([
        'words' => 'index.home.raft',
        'nearestPlace' => 'Bayswater, London',
        'coordinates' => ['lat' => 51.52, 'lng' => -0.2],
    ])]);
    $client = app(What3WordsClient::class);

    expect($client->wordsFor(51.521, -0.195, false))->toBeNull();
    expect($client->wordsFor(51.521, -0.195))->toMatchArray([
        'words' => 'index.home.raft',
        'latitude' => 51.52,
        'longitude' => -0.2,
    ]);
    expect($client->wordsFor(51.52, -0.2, false)['words'])->toBe('index.home.raft');

    Http::assertSentCount(1);
});

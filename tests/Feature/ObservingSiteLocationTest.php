<?php

declare(strict_types=1);

use App\Livewire\ObservingWorkspace;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    config([
        'services.what3words.key' => 'SECRET-W3W-KEY',
        'services.what3words.cache_seconds' => 604800,
        'services.what3words.reverse_budget' => 2,
        'cache.default' => 'array',
    ]);
    Cache::flush();
    Http::swap(new Factory);
    Http::preventStrayRequests();
});

function w3wCoordinates(): array
{
    return [
        'country' => 'GB',
        'words' => 'filled.count.soap',
        'nearestPlace' => 'Bayswater, London',
        'coordinates' => ['lng' => -0.195521, 'lat' => 51.520847],
    ];
}

it('hides the observatory what3words option when no key is configured', function () {
    config(['services.what3words.key' => null]);

    Livewire::test(ObservingWorkspace::class)
        ->assertSee('Latitude')
        ->assertDontSee('what3words')
        ->assertDontSee('site-what3words')
        ->assertDontSee('Locate address');
});

it('offers what3words on the observatory form without exposing the API key', function () {
    Livewire::test(ObservingWorkspace::class)
        ->assertSee('what3words address (optional)')
        ->assertSee('Locate address')
        ->assertSee('///filled.count.soap')
        ->assertSee('id="site-what3words"', false)
        ->assertSee('data-endpoint="'.route('observatory.what3words').'"', false)
        ->assertSee('data-reverse-endpoint="'.route('observatory.what3words.coordinates').'"', false)
        ->assertSee('aria-describedby="site-what3words-help site-what3words-result site-what3words-error"', false)
        ->assertDontSee('SECRET-W3W-KEY')
        ->assertDontSee('X-Api-Key');
    Http::assertNothingSent();
});

it('resolves a what3words address, confirms the nearest place, and caches the result', function () {
    Http::fake(['api.what3words.com/*' => Http::response(w3wCoordinates())]);

    $this->postJson(route('observatory.what3words'), ['words' => '///Filled.Count.Soap'])
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertJsonPath('words', 'filled.count.soap')
        ->assertJsonPath('latitude', 51.520847)
        ->assertJsonPath('longitude', -0.195521)
        ->assertJsonPath('roundedLatitude', 51.52)
        ->assertJsonPath('roundedLongitude', -0.2)
        ->assertJsonPath('nearestPlace', 'Bayswater, London')
        ->assertDontSee('SECRET-W3W-KEY');

    $this->postJson(route('observatory.what3words'), ['words' => 'filled.count.soap'])
        ->assertOk()
        ->assertJsonPath('nearestPlace', 'Bayswater, London');

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'convert-to-coordinates')
        && $request['words'] === 'filled.count.soap'
        && $request->hasHeader('X-Api-Key', 'SECRET-W3W-KEY'));
});

it('rejects a badly shaped address before calling what3words', function (string $words) {
    Http::fake(['api.what3words.com/*' => Http::response(w3wCoordinates())]);

    $this->postJson(route('observatory.what3words'), ['words' => $words])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Enter a what3words address as three words, such as ///filled.count.soap.')
        ->assertHeader('Referrer-Policy', 'no-referrer');

    Http::assertNothingSent();
})->with([
    'prose' => 'not a location',
    'hostname' => 'www.google.com',
    'too few words' => '///filled.count',
]);

it('reports an unknown address and does not cache the failure', function () {
    Http::fake(['api.what3words.com/*' => Http::response([
        'error' => ['code' => 'BadWords', 'message' => 'words not recognised'],
    ], 400)]);

    $this->postJson(route('observatory.what3words'), ['words' => '///not.real.words'])
        ->assertStatus(422)
        ->assertSee('recognised')
        ->assertDontSee('words not recognised');
    $this->postJson(route('observatory.what3words'), ['words' => '///not.real.words'])
        ->assertStatus(422);

    Http::assertSentCount(2);
});

it('reports an outage without caching it', function () {
    Http::fake(['api.what3words.com/*' => Http::response(null, 503)]);

    $this->postJson(route('observatory.what3words'), ['words' => '///filled.count.soap'])
        ->assertStatus(503)
        ->assertSee('unavailable');
    $this->postJson(route('observatory.what3words'), ['words' => '///filled.count.soap'])
        ->assertStatus(503);

    Http::assertSentCount(2);
});

it('reports a connection failure as unavailable', function () {
    Http::fake(function () {
        throw new ConnectionException('timed out');
    });

    $this->postJson(route('observatory.what3words'), ['words' => '///filled.count.soap'])
        ->assertStatus(503)
        ->assertSee('unavailable')
        ->assertDontSee('timed out');
});

it('hides the lookup entirely when the key is missing', function () {
    config(['services.what3words.key' => '']);

    $this->postJson(route('observatory.what3words'), ['words' => '///filled.count.soap'])
        ->assertNotFound()
        ->assertJsonPath('message', "what3words addresses aren't available here right now.");
    $this->postJson(route('observatory.what3words.coordinates'), [
        'coordinates' => [['latitude' => 51.52, 'longitude' => -0.2]],
    ])->assertNotFound();

    Http::assertNothingSent();
});

it('does not accept the address in the query string or echo it back', function () {
    Http::fake(['api.what3words.com/*' => Http::response(w3wCoordinates())]);

    $this->postJson(route('observatory.what3words').'?words=secret.leaf.path', ['words' => '///filled.count.soap'])
        ->assertStatus(422)
        ->assertDontSee('secret.leaf.path')
        ->assertDontSee('filled.count.soap')
        ->assertHeader('Referrer-Policy', 'no-referrer');

    Http::assertNothingSent();
});

it('shows an approximate what3words address for saved coordinates and limits fresh lookups', function () {
    Http::fake(function ($request) {
        expect($request->url())->toContain('convert-to-3wa?coordinates=')
            ->and($request->url())->not->toContain('%2C');

        return Http::response([
            'words' => 'index.home.raft',
            'nearestPlace' => 'Bayswater, London',
            'coordinates' => ['lat' => 51.52, 'lng' => -0.2],
        ]);
    });

    $this->postJson(route('observatory.what3words.coordinates'), [
        'coordinates' => [
            ['latitude' => 51.521, 'longitude' => -0.195],
            ['latitude' => 48.86, 'longitude' => 2.35],
            ['latitude' => -33.87, 'longitude' => 151.21],
            ['latitude' => 999, 'longitude' => 0],
        ],
    ])->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonCount(2, 'results')
        ->assertJsonPath('results.0.words', 'index.home.raft')
        ->assertJsonPath('results.0.latitude', 51.52)
        ->assertJsonPath('results.0.longitude', -0.2)
        ->assertJsonPath('results.0.nearestPlace', 'Bayswater, London')
        ->assertDontSee('SECRET-W3W-KEY');

    Http::assertSentCount(2);

    $this->postJson(route('observatory.what3words.coordinates'), [
        'coordinates' => [
            ['latitude' => 51.52, 'longitude' => -0.2],
            ['latitude' => 48.86, 'longitude' => 2.35],
            ['latitude' => -33.87, 'longitude' => 151.21],
        ],
    ])->assertOk()->assertJsonCount(3, 'results');

    Http::assertSentCount(3);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'coordinates=51.52,-0.20'));
});

it('keeps a failed reverse lookup from breaking the site list', function () {
    Http::fake(['api.what3words.com/*' => Http::response(null, 503)]);

    $this->postJson(route('observatory.what3words.coordinates'), [
        'coordinates' => [['latitude' => 51.52, 'longitude' => -0.2]],
    ])->assertOk()->assertExactJson(['results' => []]);
});

it('rejects the wrong method, a query, a huge body and bad JSON in private', function () {
    $this->get(route('observatory.what3words'))
        ->assertStatus(405)
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Referrer-Policy', 'no-referrer');
    $this->call('POST', route('observatory.what3words'), [], [], [], ['CONTENT_TYPE' => 'text/plain', 'HTTP_ACCEPT' => 'application/json'], 'words=filled.count.soap')
        ->assertStatus(415)
        ->assertDontSee('filled.count.soap');
    $this->call('POST', route('observatory.what3words'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], '{bad')
        ->assertStatus(422);
    $this->call('POST', route('observatory.what3words'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], str_repeat('a', 4097))
        ->assertStatus(413);
    Http::assertNothingSent();
});

it('requires a production CSRF token and caps locate requests', function () {
    $this->app->instance('env', 'production');
    $this->postJson(route('observatory.what3words'), ['words' => '///filled.count.soap'])
        ->assertStatus(419)
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Referrer-Policy', 'no-referrer');

    $this->app->instance('env', 'testing');
    Http::fake(['api.what3words.com/*' => Http::response(w3wCoordinates())]);
    foreach (range(1, 20) as $ignored) {
        $this->postJson(route('observatory.what3words'), ['words' => '///filled.count.soap'])->assertOk();
    }
    $this->postJson(route('observatory.what3words'), ['words' => '///filled.count.soap'])
        ->assertStatus(429)
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Referrer-Policy', 'no-referrer');

    Http::assertSentCount(1);
});

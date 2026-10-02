<?php

declare(strict_types=1);

use App\Jobs\RefreshSolarCache;
use App\Services\SolarApi\CatalogueContext;
use App\Services\SolarApi\Data\CatalogueIdentity;
use App\Services\SolarApi\Data\DownloadManifest;
use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Services\SolarApi\SolarApiClient;
use App\Services\SolarApi\StarterCatalogueClient;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\CatalogueObservation;

beforeEach(function () {
    config(['cache.default' => 'array']);
    Cache::flush();
    Http::preventStrayRequests();
});

it('shares one bounded identity probe and changes scientific cache generation on a new build', function () {
    $version = 'a';
    $calls = ['catalogue' => 0, 'stats' => 0];
    Http::fake(function ($request, $options) use (&$version, &$calls) {
        $path = basename(parse_url($request->url(), PHP_URL_PATH));
        $calls[$path]++;
        if ($path === 'catalogue') {
            expect($options['timeout'])->toBe(2)->and($options['allow_redirects'])->toBeFalse();
        }

        return Http::response($path === 'catalogue' ? CatalogueObservation::payload($version, $version)
            : ['total_objects' => $version === 'a' ? 10 : 20]);
    });
    $api = app(SolarApiClient::class);
    expect($api->stats()->totalObjects)->toBe(10);
    for ($i = 0; $i < 20; $i++) {
        expect($api->stats()->totalObjects)->toBe(10);
    }
    expect($calls)->toBe(['catalogue' => 1, 'stats' => 1]);
    $version = 'b';
    $this->travel(61)->seconds();
    expect($api->stats()->totalObjects)->toBe(20)->and($calls)->toBe(['catalogue' => 2, 'stats' => 2]);

});

it('keeps a known generation when the checked build is unchanged but never reuses it after rollback', function () {
    $version = 'a';
    Http::fake(function () use (&$version) {
        return Http::response(CatalogueObservation::payload($version, $version));
    });
    $context = app(CatalogueContext::class);
    $a = $context->current()['token'];
    $this->travel(61)->seconds();
    expect($context->current()['token'])->toBe($a);
    $version = 'b';
    $this->travel(61)->seconds();
    $b = $context->current()['token'];
    $version = 'a';
    $this->travel(61)->seconds();
    expect($context->current()['token'])->not->toBe($a)->not->toBe($b);
});

it('withdraws long stale data when identity becomes unavailable and recovers into a new generation', function () {
    $available = true;
    Http::fake(function ($request) use (&$available) {
        if (str_ends_with($request->url(), '/catalogue')) {
            return $available ? Http::response(CatalogueObservation::payload()) : Http::response([], 503);
        }

        return $available ? Http::response(['total_objects' => 12]) : Http::response([], 503);
    });
    $api = app(SolarApiClient::class);
    expect($api->stats()->totalObjects)->toBe(12);
    $old = app(CatalogueContext::class)->current()['token'];
    $available = false;
    $this->travel(61)->seconds();
    expect(fn () => $api->stats())->toThrow(SolarApiException::class);
    expect($api->catalogueIdentity()->status)->toBe('unavailable');
    $available = true;
    $this->travel(61)->seconds();
    expect($api->stats()->totalObjects)->toBe(12)
        ->and(app(CatalogueContext::class)->current()['token'])->not->toBe($old);
});

it('uses a short fresh-only cache for a legacy backend without probing each item', function () {
    $calls = ['catalogue' => 0, 'stats' => 0];
    Http::fake(function ($request) use (&$calls) {
        $path = basename(parse_url($request->url(), PHP_URL_PATH));
        $calls[$path]++;

        return $path === 'catalogue' ? Http::response([], 404) : Http::response(['total_objects' => $calls['stats']]);
    });
    $api = app(SolarApiClient::class);
    expect($api->stats()->totalObjects)->toBe(1)->and($api->stats()->totalObjects)->toBe(1);
    expect($calls)->toBe(['catalogue' => 1, 'stats' => 1]);
    $this->travel(61)->seconds();
    expect($api->stats()->totalObjects)->toBe(2)->and($calls)->toBe(['catalogue' => 2, 'stats' => 2]);
});

it('rejects delayed refresh jobs from an old generation without fetching data', function () {
    $version = 'a';
    Http::fake(function ($request) use (&$version) {
        expect(str_ends_with($request->url(), '/catalogue'))->toBeTrue();

        return Http::response(CatalogueObservation::payload($version, $version));
    });
    $key = app(CatalogueContext::class)->key('/stats', []);
    $job = new RefreshSolarCache('/stats', [], $key, 3600);
    $version = 'b';
    $this->travel(61)->seconds();
    $job->handle(app(SolarApiClient::class));
    expect(Cache::get($key))->toBeNull();
    Http::assertSentCount(2);
});

it('does not save an in-flight response after another worker changes generation', function () {
    Http::fake(function ($request) {
        if (str_ends_with($request->url(), '/catalogue')) {
            return Http::response(CatalogueObservation::payload());
        }
        Cache::put(CatalogueContext::storageKey(), ['identity' => CatalogueObservation::payload('c', 'd'),
            'token' => 'other-worker-generation', 'checked' => now()->timestamp], 86400);

        return Http::response(['total_objects' => 42]);
    });
    $key = app(CatalogueContext::class)->key('/stats', []);
    expect(app(SolarApiClient::class)->stats()->totalObjects)->toBe(42)
        ->and(Cache::get($key))->toBeNull();
});

it('does not serve prior scientific data while a different worker probes an expired observation', function () {
    Http::fake(fn ($request) => Http::response(str_ends_with($request->url(), '/catalogue')
        ? CatalogueObservation::payload() : ['total_objects' => 21]));
    $api = app(SolarApiClient::class);
    expect($api->stats()->totalObjects)->toBe(21);
    $this->travel(61)->seconds();
    $lock = Cache::lock(CatalogueContext::storageKey().':probe', 5);
    expect($lock->get())->toBeTrue();
    try {
        expect(app(CatalogueContext::class)->current()['identity']->status)->toBe('unavailable');
        $key = app(CatalogueContext::class)->key('/stats', []);
        expect($api->stats()->totalObjects)->toBe(21)->and(Cache::get($key))->toBeNull();
    } finally {
        $lock->release();
    }
    Http::assertSentCount(3); // one probe, initial data, uncached data while probing
});

it('scopes data and probes to the configured backend even with an existing client instance', function () {
    Http::fake(fn ($request) => Http::response(str_ends_with($request->url(), '/catalogue')
        ? CatalogueObservation::payload() : ['total_objects' => str_contains($request->url(), 'second.test') ? 2 : 1]));
    $api = app(SolarApiClient::class);
    expect($api->stats()->totalObjects)->toBe(1);
    config(['services.solar.base_url' => 'https://second.test/api/v1']);
    expect($api->stats()->totalObjects)->toBe(2);
    Http::assertSentCount(4);
});

it('fails malformed or unsupported identity metadata closed without exposing it', function ($patch) {
    $value = CatalogueIdentity::fromArray(array_replace(CatalogueObservation::payload(), $patch));
    expect($value->status)->toBe('unavailable')->and($value->catalogueId)->toBeNull();
})->with([
    [['catalogue_id' => ['bad']]], [['build_identifier' => 'sha256:no']], [['schema_version' => 2]],
    [['hash_policy' => 'future-policy']], [['built_at' => '2026-02-30T12:00:00Z']], [['built_at' => '2026-10-01T12:00:00Z<script>']],
]);

it('caches oversized metadata failure briefly instead of retrying it for every read', function () {
    Http::fake(['*' => Http::response(str_repeat('x', 262145), 200)]);
    for ($i = 0; $i < 3; $i++) {
        expect(app(SolarApiClient::class)->catalogueIdentity()->status)->toBe('unavailable');
    }
    Http::assertSentCount(1);
});

it('round trips identity starter DTOs and download metadata through a class-disabled file cache', function () {
    $directory = sys_get_temp_dir().'/catalogue-cache-'.bin2hex(random_bytes(8));
    config(['cache.default' => 'file', 'cache.stores.file.path' => $directory, 'cache.stores.file.lock_path' => $directory]);
    $fixture = json_decode(file_get_contents(base_path('tests/fixtures/starter-catalogue.json')), true);
    Http::fake(function ($request) use ($fixture) {
        $path = parse_url($request->url(), PHP_URL_PATH);
        if (str_ends_with($path, '/catalogue')) {
            return Http::response(CatalogueObservation::payload());
        }
        if (str_ends_with($path, '/download')) {
            return Http::response(['artefact' => 'solar_system-20261001.sqlite.zst', 'sha256' => str_repeat('e', 64),
                'sqlite_sha256' => str_repeat('f', 64), 'catalogue_identity' => CatalogueObservation::payload()]);
        }
        if (str_contains($path, '/starter-targets/')) {
            $row = $fixture['results'][0];

            return Http::response($row + ['provenance' => $fixture['sources'][$row['source']]]);
        }

        return Http::response(array_replace($fixture, ['results' => array_slice($fixture['results'], 0, 24), 'limit' => 24, 'offset' => 0]));
    });
    try {
        for ($i = 0; $i < 2; $i++) {
            expect(app(SolarApiClient::class)->catalogueIdentity()->known())->toBeTrue();
            expect(app(StarterCatalogueClient::class)->catalogue()->total)->toBe($fixture['total']);
            expect(app(StarterCatalogueClient::class)->target($fixture['results'][0]['id'])->id)->toBe($fixture['results'][0]['id']);
            expect(app(SolarApiClient::class)->downloadManifest()->sqliteSha256)->toBe(str_repeat('f', 64));
        }
        expect(config('cache.serializable_classes'))->toBeFalse();
        Http::assertSentCount(4);
    } finally {
        File::deleteDirectory($directory);
    }
});

it('distinguishes API download data changes build changes and unknown historical identity', function ($downloadIdentity, $copy) {
    Http::fake(fn ($request) => Http::response(match (basename(parse_url($request->url(), PHP_URL_PATH))) {
        'catalogue' => CatalogueObservation::payload(),
        'download' => ['artefact' => 'solar_system-20261001.sqlite.zst', 'sha256' => str_repeat('e', 64),
            'sqlite_sha256' => str_repeat('f', 64), 'catalogue_identity' => $downloadIdentity],
        default => ['results' => []],
    }));
    $this->get('/about')->assertOk()->assertSee($copy)
        ->assertSee('Compressed file SHA-256')->assertSee('Uncompressed SQLite SHA-256')
        ->assertSee(config('site.download_url'), false)->assertSee('This separate identity check does not pin another page response.');
})->with([
    [CatalogueObservation::payload(), 'same build ID'],
    [CatalogueObservation::payload('a', 'c'), 'same logical data ID but different build provenance'],
    [CatalogueObservation::payload('c', 'd'), 'different data IDs'],
    [['schema_version' => 1, 'status' => 'unknown'], 'at least one catalogue identity is unknown'],
]);

it('keeps the manifest link usable when legacy API version metadata is unavailable', function () {
    Http::fake(['*' => Http::response([], 404)]);
    $this->get('/about')->assertOk()->assertSee('current API catalogue identity is unknown or unavailable')
        ->assertSee('Download version metadata is unavailable here')->assertSee(config('site.download_url'), false);
});

it('does not show a malformed download checksum or unsafe artifact label', function ($field, $value) {
    $manifest = ['artefact' => 'solar_system-20261001.sqlite.zst', 'sha256' => str_repeat('a', 64)];
    $manifest[$field] = $value;
    expect(DownloadManifest::fromArray($manifest))->toBeNull();
})->with([
    ['artefact', '<script>alert(1)</script>'], ['artefact', '../../catalogue.sqlite.zst'],
    ['sha256', 'unknown'], ['sqlite_sha256', ['bad']],
]);

it('refuses compressed identity responses so a small transfer cannot inflate past the metadata limit', function () {
    Http::fake(['*' => Http::response(CatalogueObservation::payload(), 200, ['Content-Encoding' => 'gzip'])]);
    expect(app(SolarApiClient::class)->catalogueIdentity()->known())->toBeFalse();
});

it('fetches real foreground data when generation changes between a cold lookup and refresh', function () {
    Http::fake(fn ($request) => Http::response(str_ends_with($request->url(), '/catalogue')
        ? CatalogueObservation::payload() : ['total_objects' => 42]));
    $key = app(CatalogueContext::class)->key('/stats', []);
    Event::listen(CacheMissed::class, function ($event) use ($key) {
        if ($event->key === $key) {
            Cache::put(CatalogueContext::storageKey(), ['identity' => CatalogueObservation::payload('c', 'd'),
                'token' => 'concurrent-build', 'checked' => now()->timestamp], 86400);
        }
    });
    expect(app(SolarApiClient::class)->stats()->totalObjects)->toBe(42);
    Http::assertSentCount(2);
    expect(Cache::get($key))->toBeNull();
});

it('does not let a paused identity producer overwrite a newer producer after its lease expires', function () {
    $calls = 0;
    Http::fake(function () use (&$calls) {
        $calls++;
        if ($calls === 1) {
            $this->travel(6)->seconds();
            expect(app(CatalogueContext::class)->current()['identity']->catalogueId)->toBe('sha256:'.str_repeat('b', 64));

            return Http::response(CatalogueObservation::payload('a', 'a'));
        }

        return Http::response(CatalogueObservation::payload('b', 'b'));
    });
    $result = app(CatalogueContext::class)->current();
    expect($result['identity']->catalogueId)->toBe('sha256:'.str_repeat('b', 64))
        ->and(app(CatalogueContext::class)->current()['token'])->toBe($result['token']);
    Http::assertSentCount(2);
});

it('moves batched positions to a new build and never uses stale positions for unknown identity', function () {
    $version = 'a';
    Http::fake(function ($request) use (&$version) {
        if (str_ends_with($request->url(), '/catalogue')) {
            return $version === 'unknown' ? Http::response(['schema_version' => 1, 'status' => 'unknown'])
                : Http::response(CatalogueObservation::payload($version, $version));
        }
        if ($version === 'unknown') {
            return Http::response([], 503);
        }

        return Http::response(['name' => 'Saturn', 'x_au' => $version === 'a' ? 1 : 2, 'y_au' => 0, 'z_au' => 0, 'distance_from_sun_au' => 2]);
    });
    $api = app(SolarApiClient::class);
    expect($api->positionsBatch(['planet-saturn'], '2026-10-01')['planet-saturn']->xAu)->toBe(1.0);
    $version = 'b';
    $this->travel(61)->seconds();
    expect($api->positionsBatch(['planet-saturn'], '2026-10-01')['planet-saturn']->xAu)->toBe(2.0);
    $version = 'unknown';
    $this->travel(61)->seconds();
    $key = app(CatalogueContext::class)->key('/positions/planet-saturn', ['date' => '2026-10-01']);
    Cache::put($key, ['value' => ['name' => 'Saturn', 'x_au' => 99], 'soft' => time() - 1], 3600);
    expect($api->positionsBatch(['planet-saturn'], '2026-10-01')['planet-saturn'])->toBeNull();
});

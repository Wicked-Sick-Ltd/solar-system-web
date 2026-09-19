<?php

declare(strict_types=1);

use App\Services\SolarApi\Data\ObjectDetail;
use App\Services\SolarApi\Data\ObjectSummary;
use App\Services\SolarApi\Data\SkyPosition;
use App\Services\SolarApi\Data\Stats;
use App\Services\SolarApi\Exceptions\SolarApiUnavailableException;
use App\Services\SolarApi\SolarApiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => fakeSolar());

function client(): SolarApiClient
{
    return app(SolarApiClient::class);
}

/** The cache key positionsBatch() reads and writes for one body on one date. */
function positionKey(string $id, string $date): string
{
    return 'solar:'.sha1("/positions/{$id}?".http_build_query(['date' => $date]));
}

it('maps an object list into typed DTOs', function () {
    $page = client()->objects(['type' => 'asteroid'], 24, 0);

    expect($page->items)->toHaveCount(24)
        ->and($page->items[0])->toBeInstanceOf(ObjectSummary::class)
        ->and($page->items[0]->name)->toBe('Object 0');
});

it('detects a next page by over-fetching one row', function () {
    // The stub returns 25 rows for a page size of 24, so hasMore is true.
    $page = client()->objects([], 24, 0);

    expect($page->hasMore)->toBeTrue()
        ->and($page->count())->toBe(24)             // the extra row is trimmed
        ->and($page->to())->toBe(24);
});

it('returns a full ObjectDetail for a known object', function () {
    $object = client()->object('planet-saturn');

    expect($object)->toBeInstanceOf(ObjectDetail::class)
        ->and($object->name)->toBe('Saturn')
        ->and($object->typeLabel())->toBe('Planet')
        ->and($object->orbital?->isPropagatable())->toBeTrue()
        ->and($object->physical?->radiusKm)->toBe(58232.0)
        ->and($object->visual?->safeColourHex())->toBe('#EAD6A0');
});

it('returns null for a 404 rather than throwing', function () {
    expect(client()->object('missing-object'))->toBeNull();
});

it('parses catalogue stats with helpers', function () {
    $stats = client()->stats();

    expect($stats)->toBeInstanceOf(Stats::class)
        ->and($stats->totalObjects)->toBe(15546)
        ->and($stats->planets())->toBe(8)
        ->and($stats->dwarfPlanets())->toBe(10)            // 5 + 5 candidates
        ->and($stats->lastRefreshed()?->year)->toBe(2026);
});

it('caches reads so a repeat call makes no second request', function () {
    client()->stats();
    client()->stats();

    Http::assertSentCount(1);
});

it('throws SolarApiUnavailableException when the backend is unreachable', function () {
    fakeSolarDown();

    client()->objects();
})->throws(SolarApiUnavailableException::class);

it('search returns SearchResult DTOs', function () {
    $results = client()->search('ceres');

    expect($results)->toHaveCount(1)
        ->and($results[0]->name)->toBe('Ceres')
        ->and($results[0]->typeLabel())->toBe('Dwarf planet');
});

it('computes a position for a propagatable body', function () {
    $position = client()->position('planet-saturn', '2026-06-01');

    expect($position?->distanceFromSunAu)->toBe(9.47);
});

it('only requests cold and soft-stale positions in a batch', function () {
    $date = '2026-06-01';

    Cache::put(positionKey('planet-saturn', $date), [
        'value' => ['name' => 'Cached Saturn', 'distance_from_sun_au' => 9.47],
        'soft' => time() + 300,
    ], 1800);
    Cache::put(positionKey('planet-earth', $date), [
        'value' => ['name' => 'Stale Earth', 'distance_from_sun_au' => 999],
        'soft' => time() - 1,
    ], 1800);

    $positions = client()->positionsBatch(
        ['planet-saturn', 'planet-earth', 'planet-mars'],
        $date,
    );

    expect($positions['planet-saturn']?->name)->toBe('Cached Saturn')
        ->and($positions['planet-earth']?->name)->toBe('Earth')
        ->and($positions['planet-earth']?->distanceFromSunAu)->toBe(1.0043887509911655)
        ->and($positions['planet-mars']?->name)->toBe('Saturn');

    Http::assertSentCount(2);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/positions/planet-saturn'));
});

it('returns nulls instead of throwing when a positions batch cannot reach the backend', function () {
    fakeSolarDown();

    // The orrery plots what it has; one unreachable batch must not 500 the page.
    $positions = client()->positionsBatch(['planet-earth', 'planet-mars'], '2026-06-01');

    expect($positions)->toBe(['planet-earth' => null, 'planet-mars' => null]);
});

it('keeps fresh cached positions when the rest of the batch is unreachable', function () {
    $date = '2026-06-01';
    fakeSolarDown();                                  // flushes the cache, so seed after it

    Cache::put(positionKey('planet-saturn', $date), [
        'value' => ['name' => 'Cached Saturn', 'distance_from_sun_au' => 9.47],
        'soft' => time() + 300,
    ], 1800);

    $positions = client()->positionsBatch(['planet-saturn', 'planet-earth'], $date);

    expect($positions['planet-saturn']?->name)->toBe('Cached Saturn')
        ->and($positions['planet-earth'])->toBeNull();
});

it('nulls a body whose position errors without dropping the rest of the batch', function () {
    $positions = client()->positionsBatch(['broken-body', 'dwarf-pluto', 'planet-earth'], '2026-06-01');

    expect($positions['broken-body'])->toBeNull()          // 500 from the backend
        ->and($positions['dwarf-pluto'])->toBeNull()       // 404: no ephemeris
        ->and($positions['planet-earth']?->name)->toBe('Earth');
});

it('serves a soft-stale position when its refresh fails, and keeps it cached', function () {
    $date = '2026-06-01';
    $key = positionKey('broken-body', $date);

    Cache::put($key, [
        'value' => ['name' => 'Stale Body', 'distance_from_sun_au' => 5.0],
        'soft' => time() - 1,
    ], 1800);

    $positions = client()->positionsBatch(['broken-body'], $date);

    // The stale copy still plots, and the failure is not cached over it.
    expect($positions['broken-body']?->name)->toBe('Stale Body')
        ->and(Cache::get($key)['value']['name'])->toBe('Stale Body');
});

it('fetches a sky position and maps the observer block', function () {
    fakeSolar();

    $sky = app(SolarApiClient::class)->sky('planet-saturn', '2026-09-15T21:00:00Z', 51.5, -0.12);

    expect($sky)->toBeInstanceOf(SkyPosition::class)
        ->and($sky->raHms)->toBe('23h 12m 04s')
        ->and($sky->constellationName)->toBe('Aquarius')
        ->and($sky->observer?->isUp)->toBeTrue()
        ->and($sky->observer?->riseUtc)->toBe('2026-09-15T18:41:00Z');

    Http::assertSent(fn ($r) => str_contains($r->url(), '/sky/planet-saturn') && (float) $r['lat'] === 51.5);
});

it('returns null for a sky 404 so the panel simply hides', function () {
    fakeSolar();

    expect(app(SolarApiClient::class)->sky('missing-sky'))->toBeNull();
});

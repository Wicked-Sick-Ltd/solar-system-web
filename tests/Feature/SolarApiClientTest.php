<?php

declare(strict_types=1);

use App\Services\SolarApi\Data\ObjectDetail;
use App\Services\SolarApi\Data\ObjectSummary;
use App\Services\SolarApi\Data\SkyPosition;
use App\Services\SolarApi\Data\Stats;
use App\Services\SolarApi\Exceptions\SolarApiUnavailableException;
use App\Services\SolarApi\SolarApiClient;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => fakeSolar());

function client(): SolarApiClient
{
    return app(SolarApiClient::class);
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

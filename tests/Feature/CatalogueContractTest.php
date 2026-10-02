<?php

declare(strict_types=1);

use App\Services\SolarApi\SolarApiClient;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $path = getenv('CATALOGUE_CONTRACT_PATH') ?: base_path('tests/fixtures/catalogue-contract.json');
    $this->contract = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    config(['cache.default' => 'array']);
    Cache::flush();
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(function ($request) {
        $path = parse_url($request->url(), PHP_URL_PATH);
        $c = $this->contract;
        $key = match ($path) {
            '/api/v1/exoplanets' => 'exoplanets',
            '/api/v1/exoplanets/'.$c['exoplanet']['id'] => 'exoplanet',
            '/api/v1/exoplanet-hosts/'.$c['host']['id'] => 'host',
            '/api/v1/galaxy' => 'galaxy',
            '/api/v1/meteor-showers' => 'meteor_showers',
            '/api/v1/meteor-showers/GEM' => 'meteor_shower',
            '/api/v1/objects' => ($request['after'] ?? '') === '' ? 'objects' : 'next_objects',
            '/api/v1/sky/planet-saturn' => match ((float) ($request['lat'] ?? 0)) {
                51.5 => 'sky_london',
                89.0 => 'sky_north',
                -89.0 => 'sky_south',
                default => null,
            },
            default => null,
        };

        if ($key !== null) {
            $expectedQuery = match ($key) {
                'exoplanets' => ['q' => 'Proxima', 'limit' => '25', 'offset' => '0'],
                'meteor_showers' => ['established_only' => 'true', 'limit' => '1000'],
                'objects', 'next_objects' => ['type' => 'asteroid', 'limit' => '25', 'offset' => '0',
                    'after' => $key === 'objects' ? '' : $c['objects']['results'][23]['id']],
                'sky_london' => ['date' => '2026-10-01T22:00:00Z', 'lat' => '51.5', 'lon' => '-0.12'],
                'sky_north' => ['date' => '2026-10-01T22:00:00Z', 'lat' => '89', 'lon' => '0'],
                'sky_south' => ['date' => '2026-10-01T22:00:00Z', 'lat' => '-89', 'lon' => '0'],
                default => [],
            };
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $actualQuery);
            ksort($actualQuery);
            ksort($expectedQuery);
            expect($request->method())->toBe('GET')->and($actualQuery)->toBe($expectedQuery);
        }

        // Match the real JSON wire representation, including integral floats.
        $payload = $key !== null && str_starts_with($key, 'sky_') ? $c['sky'][substr($key, 4)] : ($c[$key] ?? null);

        return $key === null ? Http::response([], 404) : Http::response(
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
            200, ['Content-Type' => 'application/json'],
        );
    });
});

it('consumes real offline exoplanet REST and MCP records without losing source values', function () {
    $api = app(SolarApiClient::class);
    $list = $api->exoplanets(['q' => 'Proxima']);
    $expected = $this->contract['exoplanet'];
    $planet = $api->exoplanet($expected['id']);

    expect($list->items[0]->id)->toBe($expected['id'])
        ->and($planet->measurements)->toBe($expected['source_data'])
        ->and($planet->retrievedAt)->toBe($expected['retrieved_at'])
        ->and($api->exoplanetHost($expected['host_id'])->id)->toBe($expected['host_id'])
        ->and(count($api->galaxyMap()->hosts))->toBe(count($this->contract['galaxy']['results']));

    $this->get('/exoplanets/'.$expected['id'])->assertOk()->assertSee($expected['name']);
    $this->get('/systems/'.$expected['host_id'])->assertOk()->assertSee($expected['host_name']);
});

it('preserves every meteor parameter set and the backend grouping contract', function () {
    $api = app(SolarApiClient::class);
    $list = $api->meteorShowers(true);
    $detail = $api->meteorShower('GEM');

    expect($list->parameterSetCount)->toBe(count($this->contract['meteor_showers']['items']))
        ->and($detail->code)->toBe('GEM')
        ->and(count($detail->parameterSets))->toBe(count($this->contract['meteor_shower']['parameter_sets']));
    foreach ($detail->parameterSets as $i => $set) {
        $row = $this->contract['meteor_shower']['parameter_sets'][$i];
        expect($set->adNo)->toBe($row['ad_no'])
            ->and($set->parentObjectId)->toBe($row['parent_object_id'])
            ->and($set->reference)->toBe($row['reference']);
    }
    $this->get('/meteor-showers/GEM')->assertOk()->assertSee($this->contract['meteor_shower']['name']);
});

it('continues asteroid cursor pages at the displayed boundary of real backend records', function () {
    $api = app(SolarApiClient::class);
    $first = $api->objects(['type' => 'asteroid'], 24, 0, '');
    $second = $api->objects(['type' => 'asteroid'], 24, 0, $first->nextAfter);

    expect($first->count())->toBe(24)->and($first->hasMore)->toBeTrue()
        ->and($first->nextAfter)->toBe($this->contract['objects']['results'][23]['id'])
        ->and($second->items[0]->id)->toBe($this->contract['objects']['results'][24]['id']);
    expect(array_intersect(array_column($first->items, 'id'), array_column($second->items, 'id')))->toBe([]);
});

it('pages the asteroid catalogue past page 1 by following the rendered cursor link', function () {
    // Page 1 of the full catalogue by ID: GET /objects?after= (keyset mode).
    $firstPage = $this->contract['objects']['results'];
    $secondPage = $this->contract['next_objects']['results'];
    $cursor = $firstPage[23]['id'];
    $nextUrl = route('asteroids', ['order' => 'id', 'after' => $cursor]);

    $this->get('/asteroids?order=id')->assertOk()
        ->assertSee('Showing 24 objects in catalogue ID order')
        ->assertSee($firstPage[0]['name'])->assertSee($firstPage[23]['name'])
        ->assertDontSee($firstPage[24]['name'])
        ->assertSee('href="'.e($nextUrl).'" rel="next"', escape: false);
    Http::assertSent(fn ($request) => str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/objects')
        && $request['after'] === '' && $request['offset'] === 0);

    // Page 2: the link carries the last displayed id, the backend answers
    // with the records strictly after it, and the page keeps advancing.
    $this->get($nextUrl)->assertOk()
        ->assertSee('Showing 24 objects in catalogue ID order')
        ->assertSee($firstPage[24]['name'])->assertSee($secondPage[23]['name'])
        ->assertDontSee($firstPage[0]['name'])->assertDontSee($secondPage[24]['name'])
        ->assertSee('href="'.e(route('asteroids', ['order' => 'id', 'after' => $secondPage[23]['id']])).'" rel="next"', escape: false);
    Http::assertSent(fn ($request) => str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/objects')
        && $request['after'] === $cursor && $request['offset'] === 0);
    expect($secondPage[0]['id'])->toBe($firstPage[24]['id']);
});

it('preserves real observer calculations including polar visibility states', function (string $label) {
    $expected = $this->contract['sky'][$label];
    $observer = $expected['observer'];
    if ($label === 'north') {
        expect($observer['circumpolar'])->toBeTrue();
    } elseif ($label === 'south') {
        expect($observer['never_rises'])->toBeTrue();
    }
    $sky = app(SolarApiClient::class)->sky('planet-saturn', '2026-10-01T22:00:00Z', $observer['lat'], $observer['lon']);

    expect($sky)->not->toBeNull()
        ->and($sky->name)->toBe($expected['name'])
        ->and($sky->observer)->not->toBeNull();
    foreach ([
        'lat' => 'lat', 'lon' => 'lon', 'altitudeDeg' => 'altitude_deg',
        'azimuthDeg' => 'azimuth_deg', 'sunAltitudeDeg' => 'sun_altitude_deg',
    ] as $property => $field) {
        expect($sky->observer->{$property})->toBe((float) $observer[$field]);
    }
    foreach ([
        'isUp' => 'is_up', 'isDark' => 'is_dark', 'circumpolar' => 'circumpolar',
        'neverRises' => 'never_rises', 'riseUtc' => 'rise_utc',
        'transitUtc' => 'transit_utc', 'setUtc' => 'set_utc',
    ] as $property => $field) {
        expect($sky->observer->{$property})->toBe($observer[$field]);
    }
})->with(['london', 'north', 'south']);

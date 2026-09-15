<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Solar API fakes
|--------------------------------------------------------------------------
|
| Helpers that stub the backend REST API so tests never touch the network.
| fakeSolar() returns canned, well-formed payloads for every endpoint the
| front-end calls; fakeSolarDown() simulates an unreachable backend.
|
*/

/** Stub the backend with realistic responses. Cache is forced to the array store. */
function fakeSolar(): void
{
    config(['cache.default' => 'array']);
    Cache::flush();

    Http::fake(function ($request) {
        $url = $request->url();
        $path = parse_url($url, PHP_URL_PATH) ?? '';

        return match (true) {
            str_contains($path, '/objects/missing-object') => Http::response(['detail' => 'not found'], 404),
            str_contains($path, '/objects/planet-saturn') => Http::response(saturnDetail()),
            str_contains($path, '/objects/dwarf-pluto') => Http::response(plutoDetail()),
            // Any other single-object detail request echoes a valid record back,
            // so the date-deterministic "featured today" pick always resolves.
            (bool) preg_match('#/objects/[^/]+$#', $path) => Http::response(objectDetail(basename($path))),
            str_ends_with($path, '/objects') => Http::response([
                'results' => objectRows(25), 'limit' => 25, 'offset' => 0,
            ]),
            str_contains($path, '/moons') => Http::response(['results' => objectRows(3)]),
            str_contains($path, '/rings') => Http::response(['results' => [
                ['id' => 1, 'name' => 'A Ring', 'inner_radius_km' => 122170, 'outer_radius_km' => 136775],
            ]]),
            str_contains($path, '/dwarf-planets') => Http::response(['results' => objectRows(5)]),
            str_contains($path, '/neos') => Http::response(['results' => objectRows(4)]),
            str_contains($path, '/comets/periodic') => Http::response(['results' => objectRows(4)]),
            str_contains($path, '/tnos') => Http::response(['results' => objectRows(4)]),
            str_contains($path, '/search') => Http::response(['query' => 'x', 'results' => [
                ['id' => 'dwarf-ceres', 'name' => 'Ceres', 'designation' => '(1) Ceres', 'object_type' => 'dwarf_planet'],
            ]]),
            // The backend has no ephemeris for Pluto (mirrors production).
            str_contains($path, '/positions/dwarf-pluto') => Http::response(['detail' => 'No object found'], 404),
            str_contains($path, '/positions/') => Http::response([
                'name' => 'Saturn', 'input_date' => '2026-06-01', 'distance_from_sun_au' => 9.47,
                'true_anomaly_deg' => 273.6, 'x_au' => 9.4, 'y_au' => 1.1, 'z_au' => -0.39, 'jd' => 2461192.5,
            ]),
            str_contains($path, '/stats') => Http::response(statsPayload()),
            str_contains($path, '/sources') => Http::response(['results' => [
                ['source_name' => 'JPL SBDB', 'n' => 12002, 'last_seen' => '2026-05-31T20:25:15Z'],
            ]]),
            default => Http::response(['results' => []]),
        };
    });
}

/** Simulate an unreachable backend: every call raises a connection error. */
function fakeSolarDown(): void
{
    config(['cache.default' => 'array']);
    Cache::flush();

    Http::fake(function () {
        throw new ConnectionException('Connection refused');
    });
}

function statsPayload(): array
{
    return [
        'total_objects' => 15546,
        'by_object_type' => [
            'planet' => 8, 'moon' => 273, 'dwarf_planet' => 5, 'dwarf_planet_candidate' => 5,
            'asteroid' => 8532, 'comet' => 4065, 'tno' => 1630, 'centaur' => 1027, 'star' => 1,
        ],
        'last_build' => ['finished_at' => '2026-05-31T20:25:22Z'],
    ];
}

/** @return list<array<string,mixed>> */
function objectRows(int $n): array
{
    $rows = [];
    for ($i = 0; $i < $n; $i++) {
        $rows[] = [
            'id' => "obj-{$i}",
            'name' => "Object {$i}",
            'object_type' => 'asteroid',
            'radius_km' => 10 + $i,
            'semi_major_axis_au' => 2.5,
            'orbital_period_days' => 1500,
        ];
    }

    return $rows;
}

function objectDetail(string $id): array
{
    return [
        'id' => $id,
        'name' => ucfirst(str_replace('-', ' ', $id)),
        'object_type' => 'moon',
        'orbital' => ['semi_major_axis_au' => 0.01, 'eccentricity' => 0.001, 'orbital_period_days' => 1.5],
        'physical' => ['radius_km' => 200],
        'visual' => [],
        'classifications' => [],
        'sources' => [],
    ];
}

function saturnDetail(): array
{
    return [
        'id' => 'planet-saturn',
        'name' => 'Saturn',
        'object_type' => 'planet',
        'parent_id' => 'sun',
        'wikipedia_url' => 'https://en.wikipedia.org/wiki/Saturn',
        'orbital' => [
            'semi_major_axis_au' => 9.537, 'eccentricity' => 0.0539,
            'orbital_period_days' => 10759.22, 'epoch' => 'J2000', 'frame' => 'J2000',
        ],
        'physical' => ['radius_km' => 58232, 'mass_kg' => 5.6834e26, 'density_g_cm3' => 0.687],
        'visual' => ['geometric_albedo' => 0.499, 'dominant_colour_hex' => '#EAD6A0'],
        'classifications' => [],
        'sources' => [
            ['table_name' => 'physical_properties', 'source_name' => 'NASA Planetary Fact Sheet', 'source_url' => 'https://nssdc.gsfc.nasa.gov/'],
        ],
    ];
}

/** Mirrors the production record: physical + visual blocks, but no orbital elements. */
function plutoDetail(): array
{
    return [
        'id' => 'dwarf-pluto',
        'name' => 'Pluto',
        'designation' => '(134340) Pluto',
        'object_type' => 'dwarf_planet',
        'parent_id' => 'sun',
        'discoverer' => 'Clyde Tombaugh',
        'discovery_date' => '1930-02-18',
        'wikipedia_url' => 'https://en.wikipedia.org/wiki/Pluto',
        'orbital' => null,
        'physical' => ['radius_km' => 1188.3, 'mass_kg' => 1.303e22, 'density_g_cm3' => 1.854],
        'visual' => ['geometric_albedo' => 0.52, 'dominant_colour_hex' => '#CFA88B'],
        'classifications' => [],
        'sources' => [
            ['table_name' => 'physical_properties', 'source_name' => 'NASA Planetary Fact Sheet', 'source_url' => 'https://nssdc.gsfc.nasa.gov/planetary/factsheet/'],
        ],
    ];
}

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
            str_contains($path, '/objects/ast-20099942-apophis') => Http::response(apophisDetail()),
            str_contains($path, '/objects/dwarf-pluto') => Http::response(plutoDetail()),
            str_contains($path, '/objects/moon-luna') => Http::response(lunaDetail()),
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
            str_contains($path, '/sky/missing-sky') => Http::response(['detail' => 'not found'], 404),
            str_contains($path, '/sky/') => Http::response(skyPayload(observer: isset($request['lat']))),
            // The backend has no ephemeris for Pluto (mirrors production).
            str_contains($path, '/positions/dwarf-pluto') => Http::response(['detail' => 'No object found'], 404),
            // A body whose ephemeris blows up upstream, for degradation tests.
            str_contains($path, '/positions/broken-body') => Http::response(['detail' => 'boom'], 500),
            // Earth's heliocentric position (2026-09-19) — the relative-position figure always fetches it.
            str_contains($path, '/positions/planet-earth') => Http::response([
                'name' => 'Earth', 'input_date' => '2026-09-19', 'distance_from_sun_au' => 1.0043887509911655,
                'true_anomaly_deg' => 253.86, 'x_au' => 1.0028213281111156, 'y_au' => -0.056090525073772546, 'z_au' => 0.0, 'jd' => 2461302.5,
            ]),
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
            'mass_kg' => 1.0e20 * ($i + 1),
            'density_g_cm3' => 1.5 + $i / 10,
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
        // v2 blocks
        'discovery' => ['discovered_on' => null, 'discoverer' => null, 'site' => null, 'citation' => null, 'source' => 'seed'],
        'designations' => [['designation' => 'Saturn', 'kind' => 'name'], ['designation' => 'NAIF 699', 'kind' => 'alternate']],
        'close_approaches' => [],
        'close_approach_count' => 0,
        'atmosphere' => [
            'surface_pressure_bar' => 1000.0, 'pressure_note' => '>>1000 bars', 'temperature_k' => 134.0, 'scale_height_km' => 59.5,
            'mean_molecular_weight' => 2.07, 'wind_note' => 'Up to 400 m/s',
            'composition' => [['species' => 'Molecular hydrogen (H2)', 'fraction' => 96.3, 'unit' => '%'], ['species' => 'Helium (He)', 'fraction' => 3.25, 'unit' => '%']],
        ],
        'impact_monitoring' => null,
    ];
}

/** An Apollo NEO with the full v2 detail: orbit quality, discovery citation, close approaches. */
function apophisDetail(): array
{
    return [
        'id' => 'ast-20099942-apophis', 'name' => 'Apophis', 'designation' => '99942 Apophis (2004 MN4)', 'object_type' => 'asteroid', 'parent_id' => 'sun',
        'orbital' => [
            'semi_major_axis_au' => 0.9227, 'eccentricity' => 0.1914, 'inclination_deg' => 3.34, 'orbital_period_days' => 323.7,
            'perihelion_au' => 0.746, 'aphelion_au' => 1.099, 'epoch' => '2461200.5', 'frame' => 'J2000',
            'orbit_class_code' => 'APO', 'orbit_class_name' => 'Apollo', 'moid_au' => 0.000254, 'tisserand_jupiter' => 6.464,
            'condition_code' => 0, 'data_arc_days' => 7412.0, 'first_obs' => '2004-03-15', 'last_obs' => '2024-06-30',
            'n_obs_used' => 8121, 'rms_arcsec' => 0.319, 'solution_date' => '2024-07-02 10:11:12', 'producer' => 'Davide Farnocchia',
        ],
        'physical' => ['radius_km' => 0.17, 'rotation_period_hours' => 30.56, 'slope_g' => 0.24],
        'visual' => ['absolute_magnitude_h' => 19.09, 'geometric_albedo' => 0.35, 'spectral_type' => 'Sq', 'spectral_type_tholen' => null],
        'classifications' => ['NEO', 'PHA', 'Apollo', 'Named'],
        'sources' => [],
        'discovery' => ['discovered_on' => '2004-06-19', 'discoverer' => 'R. A. Tucker, D. J. Tholen, F. Bernardi', 'site' => 'Kitt Peak',
            'citation' => 'Apophis is the Egyptian god of evil and destruction.', 'source' => 'JPL SBDB (lookup)'],
        'designations' => [['designation' => '99942', 'kind' => 'number'], ['designation' => 'Apophis', 'kind' => 'name'], ['designation' => '2004 MN4', 'kind' => 'provisional']],
        'close_approaches' => [
            ['body' => 'Earth', 'cd_iso' => '2029-04-13T21:46:00Z', 'dist_au' => 0.000254, 'dist_min_au' => 0.000253, 'dist_max_au' => 0.000255, 'v_rel_km_s' => 7.42, 't_sigma' => '< 00:01'],
            ['body' => 'Earth', 'cd_iso' => '2036-03-30T00:00:00Z', 'dist_au' => 0.3, 'dist_min_au' => 0.29, 'dist_max_au' => 0.31, 'v_rel_km_s' => 5.1, 't_sigma' => '00:14'],
        ],
        'close_approach_count' => 98,
        'atmosphere' => null,
        'impact_monitoring' => ['flagged' => 0, 'source' => 'JPL SBDB (lookup)'],
    ];
}

/** Earth's Moon: a moon whose orbital elements are geocentric, so heliocentric plots use the parent. */
function lunaDetail(): array
{
    return [
        'id' => 'moon-luna', 'name' => 'Moon', 'designation' => 'Luna', 'object_type' => 'moon', 'parent_id' => 'planet-earth',
        'orbital' => ['semi_major_axis_au' => 0.00257, 'eccentricity' => 0.0549, 'orbital_period_days' => 27.32, 'centre' => 'planet-earth'],
        'physical' => ['radius_km' => 1737.4], 'visual' => [], 'classifications' => [], 'sources' => [],
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

/** A /sky/{id} response; Saturn in Aquarius, seen from London at 21:00 UTC when observer=true. */
function skyPayload(bool $observer = false): array
{
    return [
        'name' => 'Saturn', 'designation' => null, 'input_datetime' => '2026-09-15T21:00:00Z',
        'resolved_from' => null, 'jd' => 2461299.375,
        'ra_deg' => 348.02, 'ra_hours' => 23.2, 'ra_hms' => '23h 12m 04s',
        'dec_deg' => -6.9, 'dec_dms' => '-06° 54′ 00″',
        'distance_from_earth_au' => 8.82, 'distance_from_sun_au' => 9.61, 'elongation_deg' => 171.3,
        'constellation' => ['abbr' => 'Aqr', 'name' => 'Aquarius'],
        'hemisphere' => 'equatorial', 'visible_from' => 'Visible from both hemispheres.',
        'observer' => $observer ? [
            'lat' => 51.5, 'lon' => -0.12,
            'altitude_deg' => 24.6, 'azimuth_deg' => 285.0, 'is_up' => true,
            'sun_altitude_deg' => -21.7, 'is_dark' => true,
            'rise_utc' => '2026-09-15T18:41:00Z', 'transit_utc' => '2026-09-16T00:12:00Z', 'set_utc' => '2026-09-16T05:44:00Z',
            'circumpolar' => false, 'never_rises' => false,
        ] : null,
        'frame' => 'equatorial J2000, geocentric; alt/az topocentric; two-body propagation',
        'accuracy_note' => 'Two-body approximation, good to about a degree for the planets.',
    ];
}

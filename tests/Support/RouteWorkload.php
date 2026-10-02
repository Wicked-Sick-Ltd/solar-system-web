<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class RouteWorkload
{
    /** @return array<string, array{string,string,array<string,mixed>}> */
    public static function scenarios(): array
    {
        return [
            'home' => ['GET', '/', []],
            'saturn' => ['GET', '/objects/planet-saturn', []],
            'search' => ['GET', '/search?q=Saturn', []],
            'exoplanets_page' => ['GET', '/exoplanets', []],
            'systems_5000' => ['GET', '/systems', []],
            'galaxy_5000' => ['GET', '/galaxy', []],
            'galaxy_data_5000' => ['GET', '/galaxy/data', []],
            'orrery' => ['GET', '/orrery?date=2026-10-01', []],
            'night_form' => ['GET', '/observe/night', []],
            'night_eight' => ['POST', '/observe/night', [
                'date' => '2026-10-01', 'timezone' => 'Europe/London', 'lat' => 51.5, 'lon' => -0.12,
                'targets' => ['moon', 'jupiter', 'saturn'], 'catalogue_targets' => 'bsc5p:hr2491,bsc5p:hr7001,openngc:NGC0224,openngc:NGC1976,openngc:NGC6720',
                'min_altitude_deg' => 20, 'sun_altitude_deg' => -12, 'min_moon_separation_deg' => 30,
                'window_start_utc' => '2026-10-01T11:00:00Z', 'window_end_utc' => '2026-10-02T11:00:00Z',
                'horizon' => "0 10\n90 20\n180 5\n270 15",
            ]],
        ];
    }

    public static function fake(): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        // Register bounded representative payloads before the established general fixture.
        Http::fake(function ($request) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            if (str_ends_with($path, '/catalogue')) {
                return Http::response(['schema_version' => 1, 'status' => 'known',
                    'catalogue_id' => 'sha256:'.str_repeat('a', 64), 'build_identifier' => 'sha256:'.str_repeat('b', 64),
                    'hash_policy' => 'catalogue-logical-v1', 'built_at' => '2026-10-01T00:00:00Z']);
            }
            if (str_ends_with($path, '/observing/night')) {
                $raw = gzdecode(file_get_contents(base_path('tests/fixtures/performance/catalogue-night-eight.json.gz')));

                return Http::response($raw, 200, ['Content-Type' => 'application/json']);
            }
            if (str_ends_with($path, '/galaxy')) {
                $hosts = [];
                for ($index = 0; $index < 5000; $index++) {
                    $host = exoplanetHostPayload();
                    $host['id'] = 'synthetic-host-'.$index;
                    $host['name'] = 'Synthetic host '.str_pad((string) $index, 4, '0', STR_PAD_LEFT);
                    $host['distance_pc'] = 1 + $index / 10;
                    $host['x_pc'] = $host['distance_pc'];
                    $host['y_pc'] = 0.0;
                    $host['z_pc'] = 0.0;
                    $host['planets'][0]['id'] = 'synthetic-planet-'.$index;
                    $host['planets'][0]['host_id'] = $host['id'];
                    $hosts[] = $host;
                }

                return Http::response(['available' => true, 'results' => $hosts, 'unmapped_hosts' => 100, 'truncated' => true]);
            }
            if (str_ends_with($path, '/exoplanets')) {
                $rows = [];
                for ($index = 0; $index < 24; $index++) {
                    $row = exoplanetPayload();
                    $row['id'] = 'synthetic-planet-'.$index;
                    $row['name'] = 'Synthetic planet '.$index;
                    $rows[] = $row;
                }

                return Http::response(['available' => true, 'results' => $rows, 'total' => 12000]);
            }

            return null;
        });
        fakeSolar();
        // General endpoint tests may prime metadata; the benchmark really starts empty.
        Cache::flush();
    }
}

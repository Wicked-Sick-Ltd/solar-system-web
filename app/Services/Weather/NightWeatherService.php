<?php

declare(strict_types=1);

namespace App\Services\Weather;

use Carbon\CarbonImmutable;

final class NightWeatherService
{
    public function __construct(private OpenMeteoClient $client) {}

    /** @return array<string, mixed> */
    public function forecast(NightWeatherRequest $request): array
    {
        $now = CarbonImmutable::now('UTC');
        $first = $now->startOfHour();
        $end = $now->startOfDay()->addDays(7);
        $overlaps = $request->end > $first && $request->start < $end;
        $snapshot = $overlaps ? $this->client->hourlyForecast($request->lat, $request->lon) : null;
        $hours = $snapshot->hours ?? [];
        $keys = array_keys($hours);
        sort($keys);
        $coverageStart = $keys[0] ?? null;
        $coverageEnd = $keys === [] ? null : CarbonImmutable::parse($keys[array_key_last($keys)])->addHour()->toIso8601ZuluString();
        $rows = [];
        foreach ($request->start->startOfHour()->range($request->end, '1 hour') as $hour) {
            if ($hour >= $request->end) {
                break;
            }
            $time = $hour->toIso8601ZuluString();
            $point = $hours[$time] ?? array_fill_keys(['cloud_cover', 'relative_humidity_2m', 'wind_speed_10m', 'visibility'], null);
            $reported = count(array_filter($point, static fn ($value) => $value !== null));
            $status = match (true) {
                $hour < $first || $hour >= $end => 'out_of_range',
                $snapshot === null => 'unavailable',
                $time < $coverageStart || $time >= $coverageEnd => 'out_of_range',
                $reported === 4 => 'available',
                $reported > 0 => 'partial',
                default => 'unknown',
            };
            // Never show cached past forecasts as historic weather observations.
            if ($status === 'out_of_range') {
                $point = array_fill_keys(array_keys($point), null);
            }
            $rows[] = ['time_utc' => $time, 'status' => $status, ...$point];
        }
        $statuses = array_unique(array_column($rows, 'status'));
        $status = count($statuses) === 1 ? reset($statuses) : ($overlaps && $snapshot === null ? 'unavailable' : 'partial');

        return [
            'schema_version' => 1, 'status' => $status,
            'observer' => ['lat' => $request->lat, 'lon' => $request->lon],
            'window_start_utc' => $request->start->toIso8601ZuluString(), 'window_end_utc' => $request->end->toIso8601ZuluString(),
            'source' => ['name' => 'Open-Meteo', 'url' => 'https://open-meteo.com/', 'license_url' => 'https://creativecommons.org/licenses/by/4.0/',
                'fetched_at_utc' => $snapshot?->fetchedAtUtc, 'requested_forecast_days' => 7,
                'returned_start_utc' => $coverageStart, 'returned_end_utc_exclusive' => $coverageEnd],
            'units' => ['cloud_cover' => '%', 'relative_humidity_2m' => '%', 'wind_speed_10m' => 'm/s', 'visibility' => 'm'],
            'hours' => $rows,
        ];
    }
}

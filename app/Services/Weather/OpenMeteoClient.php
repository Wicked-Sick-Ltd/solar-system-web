<?php

declare(strict_types=1);

namespace App\Services\Weather;

use App\Services\Weather\Data\WeatherOutlook;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

final class OpenMeteoClient
{
    private string $baseUrl;

    private int $timeout;

    private int $cacheSeconds;

    public function __construct()
    {
        $config = config('services.open_meteo');
        $this->baseUrl = rtrim((string) ($config['base_url'] ?? 'https://api.open-meteo.com'), '/');
        $this->timeout = max(1, (int) ($config['timeout'] ?? 6));
        $this->cacheSeconds = max(60, (int) ($config['cache_seconds'] ?? 1800));
    }

    public function tonightOutlook(float $lat, float $lon, ?string $bestHourUtc): ?WeatherOutlook
    {
        $lat = round($lat, 2);
        $lon = round($lon, 2);

        $hourly = Cache::remember(
            $this->cacheKey($lat, $lon),
            $this->cacheSeconds,
            fn () => $this->fetchHourlyForecast($lat, $lon),
        );

        if (! is_array($hourly) || $hourly === []) {
            return null;
        }

        $bestHour = $this->resolveBestHour($bestHourUtc, array_keys($hourly));
        if ($bestHour === null || ! isset($hourly[$bestHour])) {
            return null;
        }

        $point = $hourly[$bestHour];
        $cloudCover = isset($point['cloud_cover']) && is_numeric($point['cloud_cover'])
            ? (int) round((float) $point['cloud_cover'])
            : null;
        if ($cloudCover === null) {
            return null;
        }

        $humidity = isset($point['relative_humidity_2m']) && is_numeric($point['relative_humidity_2m'])
            ? (int) round((float) $point['relative_humidity_2m'])
            : null;
        $wind = isset($point['wind_speed_10m']) && is_numeric($point['wind_speed_10m'])
            ? round((float) $point['wind_speed_10m'], 1)
            : null;
        $visibility = isset($point['visibility']) && is_numeric($point['visibility'])
            ? (int) round((float) $point['visibility'])
            : null;

        return new WeatherOutlook(
            bestHourUtc: $bestHour,
            cloudCoverPercent: max(0, min(100, $cloudCover)),
            verdict: $this->cloudVerdict($cloudCover),
            dewRisk: $this->dewRisk($humidity, $wind),
            visibilityMetres: $visibility,
            windSpeedMS: $wind,
            humidityPercent: $humidity,
        );
    }

    /**
     * @return array<string,array<string,mixed>>|null
     */
    private function fetchHourlyForecast(float $lat, float $lon): ?array
    {
        try {
            $response = Http::baseUrl($this->baseUrl)
                ->acceptJson()
                ->timeout($this->timeout)
                ->get('/v1/forecast', [
                    'latitude' => $lat,
                    'longitude' => $lon,
                    'hourly' => 'cloud_cover,visibility,wind_speed_10m,relative_humidity_2m',
                    'forecast_days' => 2,
                    'timezone' => 'UTC',
                ]);
        } catch (ConnectionException) {
            return null;
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();
        $hourly = is_array($data['hourly'] ?? null) ? $data['hourly'] : null;
        if ($hourly === null) {
            return null;
        }

        $times = array_values((array) ($hourly['time'] ?? []));
        $cloud = array_values((array) ($hourly['cloud_cover'] ?? []));
        $visibility = array_values((array) ($hourly['visibility'] ?? []));
        $wind = array_values((array) ($hourly['wind_speed_10m'] ?? []));
        $humidity = array_values((array) ($hourly['relative_humidity_2m'] ?? []));
        $count = count($times);

        if ($count === 0 || $count !== count($cloud)) {
            return null;
        }

        $indexed = [];
        for ($i = 0; $i < $count; $i++) {
            $iso = $this->normaliseHour((string) $times[$i]);
            if ($iso === null) {
                continue;
            }
            $indexed[$iso] = [
                'cloud_cover' => $cloud[$i] ?? null,
                'visibility' => $visibility[$i] ?? null,
                'wind_speed_10m' => $wind[$i] ?? null,
                'relative_humidity_2m' => $humidity[$i] ?? null,
            ];
        }

        return $indexed === [] ? null : $indexed;
    }

    private function cacheKey(float $lat, float $lon): string
    {
        return sprintf('weather:open-meteo:%0.2f:%0.2f', $lat, $lon);
    }

    /**
     * @param  list<string>  $hours
     */
    private function resolveBestHour(?string $preferredHourUtc, array $hours): ?string
    {
        $target = $this->normaliseHour($preferredHourUtc ?? '')
            ?? CarbonImmutable::now('UTC')->startOfHour()->toIso8601ZuluString();

        $targetTs = CarbonImmutable::parse($target, 'UTC')->getTimestamp();
        $best = null;
        $bestDistance = null;

        foreach ($hours as $hour) {
            $hourIso = $this->normaliseHour($hour);
            if ($hourIso === null) {
                continue;
            }
            $distance = abs(CarbonImmutable::parse($hourIso, 'UTC')->getTimestamp() - $targetTs);
            if ($best === null || $distance < $bestDistance) {
                $best = $hourIso;
                $bestDistance = $distance;
            }
        }

        return $best;
    }

    private function normaliseHour(string $time): ?string
    {
        $time = trim($time);
        if ($time === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($time, 'UTC')->startOfHour()->toIso8601ZuluString();
        } catch (Throwable) {
            return null;
        }
    }

    private function cloudVerdict(int $cloudCover): string
    {
        return match (true) {
            $cloudCover <= 25 => __('Clear'),
            $cloudCover <= 70 => __('Some cloud'),
            default => __('Overcast'),
        };
    }

    private function dewRisk(?int $humidityPercent, ?float $windSpeedMS): string
    {
        if ($humidityPercent === null || $windSpeedMS === null) {
            return __('Dew risk unknown');
        }

        if ($humidityPercent >= 90 && $windSpeedMS <= 4.0) {
            return __('High dew risk');
        }
        if ($humidityPercent >= 80 && $windSpeedMS <= 7.0) {
            return __('Some dew risk');
        }

        return __('Low dew risk');
    }
}

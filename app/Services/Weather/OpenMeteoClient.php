<?php

declare(strict_types=1);

namespace App\Services\Weather;

use App\Services\Weather\Data\HourlyForecast;
use App\Services\Weather\Data\WeatherOutlook;
use Carbon\CarbonImmutable;
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
        if (! is_finite($lat) || ! is_finite($lon) || $lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
            return null;
        }
        // The legacy interface name is retained; this is a reference hour, not
        // a calculation of the best observing time or an object's visibility.
        $hour = $bestHourUtc === null ? CarbonImmutable::now('UTC')->startOfHour()->toIso8601ZuluString()
            : $this->normaliseHour($bestHourUtc);
        if ($hour === null) {
            return null;
        }
        $hourly = $this->hourlyForecast($lat, $lon)?->hours;

        // A missing hour must not silently become another date's forecast.
        if (! is_array($hourly) || ! isset($hourly[$hour]['cloud_cover'])) {
            return null;
        }
        $point = $hourly[$hour];
        $cloudCover = (int) round($point['cloud_cover']);
        $humidity = $point['relative_humidity_2m'] === null ? null : (int) round($point['relative_humidity_2m']);
        $wind = $point['wind_speed_10m'] === null ? null : round($point['wind_speed_10m'], 1);
        $visibility = $point['visibility'] === null ? null : (int) round($point['visibility']);

        return new WeatherOutlook(
            bestHourUtc: $hour,
            cloudCoverPercent: $cloudCover,
            verdict: $this->cloudVerdict($cloudCover),
            dewRisk: $this->dewRisk($humidity, $wind),
            visibilityMetres: $visibility,
            windSpeedMS: $wind,
            humidityPercent: $humidity,
        );
    }

    /** One validated snapshot shared by the observer and optional night forecast. */
    public function hourlyForecast(float $lat, float $lon): ?HourlyForecast
    {
        if (! is_finite($lat) || ! is_finite($lon) || abs($lat) > 90 || abs($lon) > 180) {
            return null;
        }
        $lat = round($lat, 2);
        $lon = round($lon, 2);

        $key = $this->cacheKey($lat, $lon);
        $cached = Cache::get($key);
        $cached = is_array($cached) ? HourlyForecast::fromCache($cached) : ($cached === false ? false : null);
        if ($cached instanceof HourlyForecast || $cached === false) {
            return $cached === false ? null : $cached;
        }
        // A cold concurrent lookup must not start another upstream request.
        $lock = Cache::lock($key.':refresh', $this->timeout + 5);
        if (! $lock->get()) {
            return null;
        }
        try {
            $cached = Cache::get($key);
            $cached = is_array($cached) ? HourlyForecast::fromCache($cached) : ($cached === false ? false : null);
            if ($cached instanceof HourlyForecast || $cached === false) {
                return $cached === false ? null : $cached;
            }
            $hours = $this->fetchHourlyForecast($lat, $lon);
            $snapshot = $hours === null ? null : new HourlyForecast(CarbonImmutable::now('UTC')->toIso8601ZuluString(), $hours);
            Cache::put($key, $snapshot === null ? false : ['fetched_at_utc' => $snapshot->fetchedAtUtc, 'hours' => $snapshot->hours], $snapshot === null ? 60 : $this->cacheSeconds);

            return $snapshot;
        } finally {
            $lock->release();
        }
    }

    /** @return array<string, array<string, float|null>>|null */
    private function fetchHourlyForecast(float $lat, float $lon): ?array
    {
        try {
            $response = Http::baseUrl($this->baseUrl)
                ->acceptJson()
                ->timeout($this->timeout)
                ->withOptions(['sink' => new BoundedWeatherStream, 'allow_redirects' => false])
                ->get('/v1/forecast', [
                    'latitude' => $lat,
                    'longitude' => $lon,
                    'hourly' => 'cloud_cover,visibility,wind_speed_10m,relative_humidity_2m',
                    'forecast_days' => 7,
                    // https://open-meteo.com/en/docs: wind defaults to km/h.
                    // Dew-risk thresholds and the DTO use metres per second.
                    'wind_speed_unit' => 'ms',
                    'timeformat' => 'iso8601',
                    'timezone' => 'UTC',
                ]);
        } catch (Throwable) {
            return null;
        }
        if (! $response->successful() || strlen($response->body()) > BoundedWeatherStream::MAX_BYTES) {
            return null;
        }
        $data = $response->json();
        if (! is_array($data) || ! is_array($data['hourly'] ?? null)) {
            return null;
        }
        if (array_key_exists('utc_offset_seconds', $data) && $data['utc_offset_seconds'] !== 0) {
            return null;
        }
        if (array_key_exists('timezone', $data) && ! in_array($data['timezone'], ['UTC', 'GMT', 'Etc/UTC', 'Etc/GMT'], true)) {
            return null;
        }
        if (array_key_exists('hourly_units', $data)) {
            if (! is_array($data['hourly_units'])) {
                return null;
            }
            foreach (['time' => 'iso8601', 'cloud_cover' => '%', 'visibility' => 'm', 'wind_speed_10m' => 'm/s', 'relative_humidity_2m' => '%'] as $field => $unit) {
                if (array_key_exists($field, $data['hourly_units']) && $data['hourly_units'][$field] !== $unit) {
                    return null;
                }
            }
        }

        $hourly = $data['hourly'];
        $times = $hourly['time'] ?? null;
        if (! is_array($times) || ! array_is_list($times) || $times === [] || count($times) > 168) {
            return null;
        }
        $series = [];
        foreach (['cloud_cover', 'visibility', 'wind_speed_10m', 'relative_humidity_2m'] as $field) {
            if (! array_key_exists($field, $hourly)) {
                if ($field === 'cloud_cover') {
                    return null;
                }
                $series[$field] = array_fill(0, count($times), null);

                continue;
            }
            if (! is_array($hourly[$field]) || ! array_is_list($hourly[$field]) || count($hourly[$field]) !== count($times)) {
                return null;
            }
            $series[$field] = $hourly[$field];
        }
        $indexed = [];
        foreach ($times as $index => $time) {
            $iso = is_string($time) ? $this->normaliseHour($time, exactHour: true) : null;
            if ($iso === null || isset($indexed[$iso])) {
                return null;
            }
            $point = [];
            foreach ($series as $field => $values) {
                $value = $values[$index];
                if ($value !== null && (! is_int($value) && ! is_float($value) || ! is_finite((float) $value) || $value < 0
                    || (in_array($field, ['cloud_cover', 'relative_humidity_2m'], true) && $value > 100)
                    || ($field === 'visibility' && round($value) >= PHP_INT_MAX))) {
                    return null;
                }
                $point[$field] = $value === null ? null : (float) $value;
            }
            $indexed[$iso] = $point;
        }

        return $indexed;
    }

    private function cacheKey(float $lat, float $lon): string
    {
        // Never reinterpret forecasts cached before explicit m/s units and
        // strict payload validation were introduced.
        return sprintf('weather:open-meteo:v4-7day:%s:%s:%0.2f:%0.2f', hash('sha256', $this->baseUrl), CarbonImmutable::now('UTC')->toDateString(), $lat, $lon);
    }

    private function normaliseHour(string $time, bool $exactHour = false): ?string
    {
        // Open-Meteo's requested ISO8601 UTC hours omit a suffix. Observer
        // timestamps may include UTC Z or +00:00 and seconds, but never prose.
        if (! preg_match('/^([0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2})(?::([0-9]{2}))?(?:Z|\+00:00)?$/D', $time, $parts)) {
            return null;
        }
        $canonical = $parts[1].':'.($parts[2] ?? '00');
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d\TH:i:s', $canonical, 'UTC');
            if (! $date || $date->year < 1 || $date->format('Y-m-d\TH:i:s') !== $canonical
                || ($exactHour && ($date->minute !== 0 || $date->second !== 0))) {
                return null;
            }

            return $date->startOfHour()->toIso8601ZuluString();
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

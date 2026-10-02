<?php

declare(strict_types=1);

use App\Services\Weather\Data\WeatherOutlook;
use App\Services\Weather\OpenMeteoClient;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['cache.default' => 'array', 'services.open_meteo.base_url' => 'https://weather.example.test']);
    Cache::flush();
    Http::swap(new Factory);
    Http::preventStrayRequests();
    $this->travelTo('2026-10-01 21:12:00 UTC');
});

function hourlyWeatherPayload(): array
{
    return [
        'timezone' => 'GMT', 'utc_offset_seconds' => 0,
        'hourly_units' => ['time' => 'iso8601', 'cloud_cover' => '%', 'visibility' => 'm', 'wind_speed_10m' => 'm/s', 'relative_humidity_2m' => '%'],
        'hourly' => [
            'time' => ['2026-10-01T21:00', '2026-10-01T22:00'],
            'cloud_cover' => [20, 80], 'visibility' => [12000, 8000],
            'wind_speed_10m' => [3.0, 6.0], 'relative_humidity_2m' => [93, 85],
        ],
    ];
}

function weatherFromPayload(array $payload, ?string $reference = '2026-10-01T21:12:00Z'): ?WeatherOutlook
{
    Http::fake(['weather.example.test/*' => Http::response($payload)]);

    return app(OpenMeteoClient::class)->tonightOutlook(51.5074, -0.1278, $reference);
}

it('explicitly requests documented metre-per-second units and a bounded UTC forecast', function () {
    $weather = weatherFromPayload(hourlyWeatherPayload());
    expect($weather->bestHourUtc)->toBe('2026-10-01T21:00:00Z')
        ->and($weather->windSpeedMS)->toBe(3.0)->and($weather->dewRisk)->toBe('High dew risk')
        ->and($weather->visibilityMetres)->toBe(12000)->and($weather->cloudCoverPercent)->toBe(20);
    Http::assertSent(fn ($request) => $request['wind_speed_unit'] === 'ms' && $request['timezone'] === 'UTC'
        && $request['timeformat'] === 'iso8601' && $request['forecast_days'] === 7
        && $request['latitude'] === 51.51 && $request['longitude'] === -0.13);
    app(OpenMeteoClient::class)->tonightOutlook(51.5074, -0.1278, '2026-10-01T22:45:00+00:00');
    Http::assertSentCount(1);
});

it('never interprets old kilometre-per-hour cache entries as metres per second', function () {
    Cache::put('weather:open-meteo:51.51:-0.13', ['2026-10-01T21:00:00Z' => [
        'cloud_cover' => 20, 'visibility' => 12000, 'wind_speed_10m' => 10.8, 'relative_humidity_2m' => 93,
    ]], 1800);
    expect(weatherFromPayload(hourlyWeatherPayload())->windSpeedMS)->toBe(3.0);
    Http::assertSentCount(1);
});

it('refuses contradictory unit or timezone metadata', function (string $field, mixed $value) {
    $payload = hourlyWeatherPayload();
    data_set($payload, $field, $value);
    expect(weatherFromPayload($payload))->toBeNull();
})->with([
    ['hourly_units.wind_speed_10m', 'km/h'], ['hourly_units.visibility', 'km'], ['hourly_units.cloud_cover', 'fraction'],
    ['hourly_units.relative_humidity_2m', null], ['hourly_units.time', 'unixtime'], ['hourly_units', 'm/s'],
    ['utc_offset_seconds', 3600], ['timezone', 'Europe/London'],
]);

it('allows omitted metadata because units are explicitly requested and versioned', function () {
    $payload = hourlyWeatherPayload();
    unset($payload['hourly_units'], $payload['timezone'], $payload['utc_offset_seconds']);
    expect(weatherFromPayload($payload)->windSpeedMS)->toBe(3.0);
});

it('rejects malformed hourly structures without casting or misaligning rows', function (string $field, mixed $value) {
    $payload = hourlyWeatherPayload();
    data_set($payload, $field, $value);
    expect(weatherFromPayload($payload))->toBeNull();
})->with([
    ['hourly', 'unexpected'], ['hourly.time', '2026-10-01T21:00'], ['hourly.time', []],
    ['hourly.time', ['first' => '2026-10-01T21:00', 'second' => '2026-10-01T22:00']],
    ['hourly.cloud_cover', 0], ['hourly.cloud_cover', [20]], ['hourly.cloud_cover', ['first' => 20, 'second' => 80]],
    ['hourly.wind_speed_10m', 3], ['hourly.visibility', []], ['hourly.relative_humidity_2m', [90]],
]);

it('rejects invalid hourly timestamps instead of rolling dates or silently dropping records', function (mixed $time) {
    $payload = hourlyWeatherPayload();
    $payload['hourly']['time'][1] = $time;
    expect(weatherFromPayload($payload))->toBeNull();
})->with([
    [['bad']], [null], [true], [123], ['tomorrow'], ['2026-02-30T22:00'], ['2026-10-01T24:00'],
    ['2026-10-01T22:30'], ['2026-10-01T22:00:01Z'], ['0000-01-01T22:00'],
    ['2026-10-01T21:00:00Z'], ['2026-10-01T22:00+01:00'],
]);

it('preserves unknown optional measurements separately from real zeros', function () {
    $payload = hourlyWeatherPayload();
    $payload['hourly']['cloud_cover'][0] = 0;
    $payload['hourly']['visibility'][0] = 0;
    $payload['hourly']['wind_speed_10m'][0] = 0;
    $payload['hourly']['relative_humidity_2m'][0] = 0;
    $weather = weatherFromPayload($payload);
    expect($weather->cloudCoverPercent)->toBe(0)->and($weather->visibilityMetres)->toBe(0)
        ->and($weather->windSpeedMS)->toBe(0.0)->and($weather->humidityPercent)->toBe(0);
    Cache::flush();
    Http::swap(new Factory);
    foreach (['visibility', 'wind_speed_10m', 'relative_humidity_2m'] as $field) {
        unset($payload['hourly'][$field]);
    }
    $weather = weatherFromPayload($payload);
    expect($weather->visibilityMetres)->toBeNull()->and($weather->windSpeedMS)->toBeNull()
        ->and($weather->humidityPercent)->toBeNull()->and($weather->dewRisk)->toBe('Dew risk unknown');
});

it('does not call missing cloud cover clear or infer dew risk from null optional cells', function () {
    $payload = hourlyWeatherPayload();
    $payload['hourly']['cloud_cover'][0] = null;
    expect(weatherFromPayload($payload))->toBeNull();
    Cache::flush();
    Http::swap(new Factory);
    $payload['hourly']['cloud_cover'][0] = 50;
    $payload['hourly']['relative_humidity_2m'][0] = null;
    expect(weatherFromPayload($payload)->dewRisk)->toBe('Dew risk unknown');
});

it('rejects invalid non-null values instead of clamping them into weather claims', function (string $field, mixed $value) {
    $payload = hourlyWeatherPayload();
    $payload['hourly'][$field][0] = $value;
    expect(weatherFromPayload($payload))->toBeNull();
})->with([
    ['cloud_cover', -0.1], ['cloud_cover', 100.1], ['cloud_cover', true], ['cloud_cover', '20'], ['cloud_cover', []],
    ['relative_humidity_2m', -0.1], ['relative_humidity_2m', 100.1], ['relative_humidity_2m', false],
    ['wind_speed_10m', -0.1], ['wind_speed_10m', '1e309'], ['wind_speed_10m', []],
    ['visibility', -1], ['visibility', 1e30], ['visibility', 'unknown'],
]);

it('requires a real reference hour without prose-date parsing or far-away substitution', function (string $reference) {
    expect(weatherFromPayload(hourlyWeatherPayload(), $reference))->toBeNull();
})->with(['tomorrow', '2026-02-30T21:00:00Z', '2026-10-01T25:00:00Z', '', '2026-10-01T20:00:00Z', '2030-01-01T21:00:00Z']);

it('uses the current UTC hour only when no reference was supplied', function () {
    expect(weatherFromPayload(hourlyWeatherPayload(), null)->bestHourUtc)->toBe('2026-10-01T21:00:00Z');
});

it('does not issue weather requests for invalid coordinates before rounding', function (float $lat, float $lon) {
    expect(app(OpenMeteoClient::class)->tonightOutlook($lat, $lon, null))->toBeNull();
    Http::assertNothingSent();
})->with([[90.001, 0.0], [-90.001, 0.0], [0.0, 180.001], [0.0, -180.001], [INF, 0.0], [0.0, NAN]]);

it('fails gracefully for HTTP errors and unreadable forecast bodies', function (int $status) {
    Http::fake(['weather.example.test/*' => Http::response('not JSON', $status)]);
    expect(app(OpenMeteoClient::class)->tonightOutlook(0, 0, null))->toBeNull();
})->with([200, 503]);

it('accepts exactly seven UTC days and refuses oversized responses', function (int $count, bool $valid) {
    $payload = hourlyWeatherPayload();
    $payload['hourly']['time'] = [];
    for ($hour = 0; $hour < $count; $hour++) {
        $payload['hourly']['time'][] = now('UTC')->startOfDay()->addHours($hour)->format('Y-m-d\TH:i');
    }
    foreach (['cloud_cover', 'visibility', 'wind_speed_10m', 'relative_humidity_2m'] as $field) {
        $payload['hourly'][$field] = array_fill(0, $count, 20);
    }
    expect(weatherFromPayload($payload) !== null)->toBe($valid);
})->with([[168, true], [169, false]]);

it('matches the containing hour independently of response order and preserves a missing slot', function () {
    $payload = hourlyWeatherPayload();
    foreach ($payload['hourly'] as $field => $values) {
        $payload['hourly'][$field] = array_reverse($values);
    }
    expect(weatherFromPayload($payload)->cloudCoverPercent)->toBe(20);
    Cache::flush();
    Http::swap(new Factory);
    $payload['hourly']['time'] = ['2026-10-01T20:00', '2026-10-01T22:00'];
    expect(weatherFromPayload($payload))->toBeNull();
});

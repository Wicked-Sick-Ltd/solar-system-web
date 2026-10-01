<?php

declare(strict_types=1);

use App\Http\Middleware\PrivateNightWeather;
use App\Services\Weather\OpenMeteoClient;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function nightWeatherInput(): array
{
    return ['lat' => '51.50001', 'lon' => '-0.12001', 'window_start_utc' => '2026-10-01T21:15:00Z', 'window_end_utc' => '2026-10-01T23:00:00Z'];
}

function nightWeatherPayload(): array
{
    return ['utc_offset_seconds' => 0, 'timezone' => 'UTC', 'hourly_units' => ['time' => 'iso8601', 'cloud_cover' => '%', 'visibility' => 'm', 'wind_speed_10m' => 'm/s', 'relative_humidity_2m' => '%'],
        'hourly' => ['time' => ['2026-10-01T21:00', '2026-10-01T22:00'], 'cloud_cover' => [0, 20], 'relative_humidity_2m' => [0, 80], 'wind_speed_10m' => [0, 3.125], 'visibility' => [0, 12000]]];
}

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(new DateTimeImmutable('2026-10-01T21:12:00Z'));
    config(['cache.default' => 'array', 'services.open_meteo.base_url' => 'https://weather.example.test']);
    Cache::flush();
    Http::swap(new Factory);
    Http::preventStrayRequests();
});

it('matches exact UTC hours and shares the rounded-location snapshot with the observer', function () {
    Http::fake(['*' => Http::response(nightWeatherPayload())]);
    app(OpenMeteoClient::class)->tonightOutlook(51.50002, -0.12002, '2026-10-01T21:00:00Z');
    $this->postJson('/observe/night/weather', nightWeatherInput())->assertOk()
        ->assertJsonPath('status', 'available')->assertJsonCount(2, 'hours')
        ->assertJsonPath('observer.lat', 51.5)->assertJsonPath('observer.lon', -0.12)
        ->assertJsonPath('hours.0.time_utc', '2026-10-01T21:00:00Z')->assertJsonPath('hours.0.cloud_cover', 0)
        ->assertJsonPath('hours.0.visibility', 0)->assertJsonPath('hours.0.wind_speed_10m', 0)
        ->assertJsonPath('hours.1.wind_speed_10m', 3.125)
        ->assertJsonPath('source.fetched_at_utc', '2026-10-01T21:12:00Z')
        ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertSessionMissing('_old_input');
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request['latitude'] === 51.5 && $request['longitude'] === -0.12 && $request['forecast_days'] === 7 && $request['wind_speed_unit'] === 'ms' && $request['timezone'] === 'UTC');
});

it('renders a native POST result with units, attribution and honest limits without flashing coordinates', function () {
    Http::fake(['*' => Http::response(nightWeatherPayload())]);
    $this->post('/observe/night/weather', nightWeatherInput())->assertOk()
        ->assertSee('Hourly forecast')->assertSee('Hourly forecast table; scroll horizontally for all weather columns')->assertSee('Horizontal visibility (m)')->assertSee('CC BY 4.0')
        ->assertSee('do not measure astronomical seeing')->assertSee('Retrieval time is not the model issue time.')
        ->assertSee('2026-10-01T21:00:00Z')->assertDontSee('51.50001')
        ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertSessionMissing('_old_input');
});

it('rejects raw malformed inputs before making a weather request', function (string $field, mixed $value) {
    $this->postJson('/observe/night/weather', array_replace(nightWeatherInput(), [$field => $value]))
        ->assertStatus(422)->assertJsonPath('status', 'invalid_request')
        ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer')->assertSessionMissing('_old_input');
    Http::assertNothingSent();
})->with([
    ['lat', true], ['lon', false], ['lat', []], ['lon', ['value' => 2]], ['lat', null], ['lon', ''], ['lat', ' 51.5 '],
    ['lat', 90.001], ['lon', -180.001], ['lat', '1e309'], ['lon', 'NaN'], ['lat', '1e1'],
    ['window_start_utc', []], ['window_start_utc', true], ['window_start_utc', '2026-02-30T21:00:00Z'],
    ['window_start_utc', 'tomorrow'], ['window_start_utc', '2026-10-01T21:15:00+00:00'], ['window_start_utc', '0000-10-01T21:15:00Z'],
    ['window_end_utc', '2026-10-01T21:15:00Z'], ['window_end_utc', '2026-10-01T20:15:00Z'],
    ['window_end_utc', '2026-10-02T23:15:01Z'], ['seeing', 'excellent'],
]);

it('does not fetch historical or distant-future forecasts', function (string $start, string $end) {
    $this->postJson('/observe/night/weather', array_replace(nightWeatherInput(), ['window_start_utc' => $start, 'window_end_utc' => $end]))
        ->assertOk()->assertJsonPath('status', 'out_of_range')->assertJsonPath('source.fetched_at_utc', null)
        ->assertJsonPath('hours.0.cloud_cover', null);
    Http::assertNothingSent();
})->with([
    ['2026-09-30T12:00:00Z', '2026-10-01T12:00:00Z'],
    ['2026-10-08T00:00:00Z', '2026-10-08T01:00:00Z'],
    ['2030-01-01T00:00:00Z', '2030-01-02T00:00:00Z'],
]);

it('preserves missing interior hours and nullable fields without choosing another hour', function () {
    $payload = nightWeatherPayload();
    $payload['hourly']['time'][1] = '2026-10-01T23:00';
    $payload['hourly']['cloud_cover'][0] = null;
    $payload['hourly']['visibility'][0] = null;
    Http::fake(['*' => Http::response($payload)]);
    $this->postJson('/observe/night/weather', array_replace(nightWeatherInput(), ['window_end_utc' => '2026-10-02T00:00:00Z']))
        ->assertOk()->assertJsonPath('status', 'partial')->assertJsonCount(3, 'hours')
        ->assertJsonPath('hours.0.status', 'partial')->assertJsonPath('hours.0.cloud_cover', null)
        ->assertJsonPath('hours.1.status', 'unknown')->assertJsonPath('hours.1.cloud_cover', null)
        ->assertJsonPath('hours.2.status', 'available')->assertJsonPath('hours.2.cloud_cover', 20);
});

it('marks all-null weather unknown and limits coverage to actual returned times', function () {
    $payload = nightWeatherPayload();
    foreach (['cloud_cover', 'visibility', 'wind_speed_10m', 'relative_humidity_2m'] as $field) {
        $payload['hourly'][$field] = [null, null];
    }
    Http::fake(['*' => Http::response($payload)]);
    $this->postJson('/observe/night/weather', nightWeatherInput())->assertOk()->assertJsonPath('status', 'unknown');
    $this->postJson('/observe/night/weather', array_replace(nightWeatherInput(), ['window_start_utc' => '2026-10-02T00:00:00Z', 'window_end_utc' => '2026-10-02T01:00:00Z']))
        ->assertOk()->assertJsonPath('status', 'out_of_range')->assertJsonPath('source.returned_end_utc_exclusive', '2026-10-01T23:00:00Z');
    Http::assertSentCount(1);
});

it('accepts at most 26 hours and handles UTC midnight without timezone reinterpretation', function () {
    Http::fake(['*' => Http::response(nightWeatherPayload())]);
    $this->postJson('/observe/night/weather', array_replace(nightWeatherInput(), ['window_end_utc' => '2026-10-02T23:15:00Z']))
        ->assertOk()->assertJsonCount(27, 'hours')->assertJsonPath('hours.3.time_utc', '2026-10-02T00:00:00Z');
});

it('excludes past hourly samples even when the cached provider response contains them', function () {
    Http::fake(['*' => Http::response(nightWeatherPayload())]);
    $this->travelTo(new DateTimeImmutable('2026-10-01T21:50:00Z'));
    app(OpenMeteoClient::class)->hourlyForecast(51.5, -0.12);
    $this->travelTo(new DateTimeImmutable('2026-10-01T22:01:00Z'));
    $this->postJson('/observe/night/weather', nightWeatherInput())->assertOk()
        ->assertJsonPath('hours.0.status', 'out_of_range')->assertJsonPath('hours.0.cloud_cover', null)
        ->assertJsonPath('hours.1.status', 'available');
    Http::assertSentCount(1);
});

it('returns unavailable on invalid upstream units or failures and permits a later retry', function () {
    $bad = nightWeatherPayload();
    $bad['hourly_units']['wind_speed_10m'] = 'km/h';
    Http::fake(['*' => Http::sequence()->push($bad)->pushStatus(503)->push(nightWeatherPayload())]);
    foreach ([1, 2] as $attempt) {
        $this->postJson('/observe/night/weather', nightWeatherInput())->assertStatus(503)
            ->assertJsonPath('status', 'unavailable')->assertJsonPath('hours.0.cloud_cover', null)
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->postJson('/observe/night/weather', nightWeatherInput())->assertStatus(503);
        Http::assertSentCount($attempt);
        $this->travel(61)->seconds();
    }
    $this->postJson('/observe/night/weather', nightWeatherInput())->assertOk()->assertJsonPath('status', 'available');
    Http::assertSentCount(3);
});

it('separates snapshots by provider and UTC date', function () {
    $this->travelTo(new DateTimeImmutable('2026-10-01T23:50:00Z'));
    Http::fake(['*' => Http::response(nightWeatherPayload())]);
    app(OpenMeteoClient::class)->hourlyForecast(51.5, -0.12);
    config(['services.open_meteo.base_url' => 'https://other.example.test']);
    app(OpenMeteoClient::class)->hourlyForecast(51.5, -0.12);
    $this->travelTo(new DateTimeImmutable('2026-10-02T00:00:00Z'));
    app(OpenMeteoClient::class)->hourlyForecast(51.5, -0.12);
    Http::assertSentCount(3);
});

it('rejects query coordinates, oversized bodies, malformed JSON and wrong media types privately', function () {
    $this->postJson('/observe/night/weather?lat=12.34567', nightWeatherInput())->assertStatus(422)->assertDontSee('12.34567')
        ->assertHeader('Referrer-Policy', 'no-referrer');
    $this->call('POST', '/observe/night/weather', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], str_repeat(' ', PrivateNightWeather::MAX_BYTES + 1))
        ->assertStatus(413)->assertHeader('Cache-Control', 'no-store, private');
    $this->call('POST', '/observe/night/weather', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], '{bad')
        ->assertStatus(422)->assertHeader('Cache-Control', 'no-store, private');
    $this->call('POST', '/observe/night/weather', [], [], [], ['CONTENT_TYPE' => 'text/plain'], 'lat=12')
        ->assertStatus(415)->assertHeader('Cache-Control', 'no-store, private');
    $this->get('/observe/night/weather')->assertStatus(405)->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer');
    Http::assertNothingSent();
});

it('requires CSRF outside the test exemption and privately bounds request rate', function () {
    $this->app->instance('env', 'production');
    $this->postJson('/observe/night/weather', nightWeatherInput())->assertStatus(419)
        ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer');
    $this->app->instance('env', 'testing');
    Http::fake(['*' => Http::response(nightWeatherPayload())]);
    for ($i = 0; $i < 12; $i++) {
        $this->postJson('/observe/night/weather', nightWeatherInput())->assertOk();
    }
    $this->postJson('/observe/night/weather', nightWeatherInput())->assertStatus(429)
        ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer');
    Http::assertSentCount(1);
});

it('keeps the 25-hour autumn clock-change night as 25 distinct UTC hours', function () {
    $this->travelTo(new DateTimeImmutable('2026-10-24T10:00:00Z'));
    $payload = nightWeatherPayload();
    $payload['hourly']['time'] = [];
    for ($i = 0; $i < 25; $i++) {
        $payload['hourly']['time'][] = now('UTC')->setTime(11, 0)->addHours($i)->format('Y-m-d\TH:i');
    }
    foreach (['cloud_cover', 'visibility', 'wind_speed_10m', 'relative_humidity_2m'] as $field) {
        $payload['hourly'][$field] = array_fill(0, 25, 10);
    }
    Http::fake(['*' => Http::response($payload)]);
    $this->postJson('/observe/night/weather', array_replace(nightWeatherInput(), [
        'window_start_utc' => '2026-10-24T11:00:00Z', 'window_end_utc' => '2026-10-25T12:00:00Z',
    ]))->assertOk()->assertJsonPath('status', 'available')->assertJsonCount(25, 'hours')
        ->assertJsonPath('hours.13.time_utc', '2026-10-25T00:00:00Z')->assertJsonPath('hours.14.time_utc', '2026-10-25T01:00:00Z');
});

it('does not make another provider call while this rounded-location refresh is locked', function () {
    $key = 'weather:open-meteo:v4-7day:'.hash('sha256', 'https://weather.example.test').':2026-10-01:51.50:-0.12:refresh';
    $lock = Cache::lock($key, 11);
    expect($lock->get())->toBeTrue();
    $this->postJson('/observe/night/weather', nightWeatherInput())->assertStatus(503)->assertJsonPath('status', 'unavailable');
    Http::assertNothingSent();
    $lock->release();
    Http::fake(['*' => Http::response(nightWeatherPayload())]);
    $this->postJson('/observe/night/weather', nightWeatherInput())->assertOk();
    Http::assertSentCount(1);
});

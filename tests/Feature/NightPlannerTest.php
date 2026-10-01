<?php

declare(strict_types=1);

use App\Services\Observing\NightPlan;
use App\Services\Observing\NightRequest;
use App\Services\SolarApi\Exceptions\SolarApiException;
use Illuminate\Support\Facades\Http;

function nightInput(): array
{
    return ['date' => '2026-10-01', 'timezone' => 'Europe/London', 'lat' => 51.5001, 'lon' => -0.1201,
        'targets' => ['moon', 'saturn'], 'min_altitude_deg' => 20, 'sun_altitude_deg' => -12, 'min_moon_separation_deg' => 0];
}

function nightFixture(): array
{
    return json_decode(file_get_contents(base_path('tests/fixtures/observing/night.json')), true, 512, JSON_THROW_ON_ERROR);
}

it('opens the planner without calculating or remembering query coordinates', function () {
    Http::fake();
    $this->get('/observe/night?lat=12.34567&lon=1.23456')->assertOk()
        ->assertSee('Plan a night')->assertDontSee('12.34567')->assertHeader('Cache-Control', 'no-store, private');
    Http::assertNothingSent();
});

it('renders one validated batch with rounded coordinates and explicit model limits', function () {
    Http::fake(['*' => Http::response(nightFixture())]);
    $this->post('/observe/night', nightInput())->assertOk()
        ->assertSee('Your night')->assertSee('Saturn')->assertSee('astropy-builtin')
        ->assertSee('Moon illuminated:')->assertSee('Altitude and direction sample table')
        ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertSessionMissing('_old_input');
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/observing/night')
        && $request['lat'] === 51.5 && $request['lon'] === -0.12 && $request['targets'] === 'moon,saturn');
});

it('rejects invalid input before contacting the ephemeris', function (string $field, mixed $value) {
    Http::fake();
    $this->post('/observe/night', array_replace(nightInput(), [$field => $value]))->assertStatus(422)
        ->assertDontSee('Your night')->assertSessionMissing('_old_input');
    Http::assertNothingSent();
})->with([
    ['date', '2026-02-30'], ['date', '2101-01-01'], ['date', []], ['timezone', 'Bad/Zone'],
    ['lat', 91], ['lon', -181], ['lat', 'NaN'], ['lon', '1e309'], ['lat', true], ['lat', []],
    ['targets', []], ['targets', ['sun']], ['targets', ['moon', 'moon']], ['targets', 'moon'],
    ['targets', [true]], ['targets', ['unexpected' => 'moon']], ['max_cloud_percent', 30], ['sun_altitude_deg', -10], ['min_altitude_deg', 86], ['min_moon_separation_deg', -1],
]);

it('does not present malformed upstream data as an observing result', function (string $path, mixed $value) {
    $data = nightFixture();
    data_set($data, $path, $value);
    Http::fake(['*' => Http::response($data)]);
    $this->post('/observe/night', nightInput())->assertStatus(503)->assertDontSee('Your night')->assertSee('no substitute positions');
})->with([
    ['schema_version', 2], ['observer.lat', 51.51], ['observer.timezone', 'UTC'], ['night.date', '2026-10-02'],
    ['night.end_utc', '2026-10-02T12:00:00Z'], ['night.duration_hours', 25], ['night.start_utc', '2026-02-30T11:00:00Z'],
    ['method', []], ['method.sample_minutes', 30], ['method.iers.status', 'unknown'],
    ['method.iers.end_utc', '2020-01-01T00:00:00Z'], ['constraints.min_sun_separation_deg', 0],
    ['moon.illumination_fraction', 2], ['moon.reference_utc', '2020-01-01T00:00:00Z'],
    ['targets', []], ['targets.1.id', 'moon'], ['targets.0.status', 'visible'],
    ['targets.0.samples.0.altitude_deg', 91], ['targets.0.samples.0.azimuth_deg', -1],
    ['targets.0.samples.0.time_utc', '2026-10-01T11:00:01Z'], ['targets.0.samples.1.time_utc', '2026-10-01T11:00:00Z'],
    ['targets.0.windows', [['start_utc' => '2026-10-01T12:00:00Z', 'end_utc' => '2026-10-01T11:00:00Z']]],
    ['moon.samples', []], ['targets.0.samples.0.distance_au', null], ['targets.0.samples.0.sun_separation_deg', '40'],
]);

it('handles an older backend, missing coverage and failed connections without exposing upstream diagnostics', function (int $status) {
    Http::fake(['*' => Http::response(['detail' => '/private/server/path'], $status)]);
    $this->post('/observe/night', nightInput())->assertStatus(503)->assertDontSee('/private/server/path')->assertDontSee('Your night');
    Http::assertSentCount(1);
})->with([404, 422, 500, 503]);

it('rejects a response from another night across a daylight-saving change', function () {
    $data = nightFixture();
    $query = NightRequest::parse(array_replace(nightInput(), ['date' => '2026-10-24']));
    $data['night']['date'] = '2026-10-24';
    expect(fn () => NightPlan::validate($data, $query))->toThrow(SolarApiException::class);
});

it('escapes upstream labels and keeps invalid numeric booleans out of results', function () {
    $data = nightFixture();
    $data['targets'][0]['name'] = '<script>alert(1)</script>';
    Http::fake(['*' => Http::response($data)]);
    $this->post('/observe/night', nightInput())->assertOk()->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    $data['targets'][0]['samples'][0]['altitude_deg'] = true;
    expect(fn () => NightPlan::validate($data, NightRequest::parse(nightInput())))->toThrow(SolarApiException::class);
});

it('keeps valid form values while associating errors without session flashing', function () {
    Http::fake();
    $this->post('/observe/night', array_replace(nightInput(), ['min_altitude_deg' => 86]))
        ->assertStatus(422)->assertSee('value="Europe/London"', false)
        ->assertSee('value="2026-10-01"', false)->assertSee('value="51.5001"', false)
        ->assertSee('aria-describedby="night-error-min_altitude_deg"', false)
        ->assertSessionMissing('_old_input');
    Http::assertNothingSent();
});

it('never turns an unresolved empty crossing into a confident absence claim', function () {
    $data = nightFixture();
    $data['darkness'] = ['status' => 'unresolved_grazing', 'intervals' => []];
    foreach ($data['targets'] as &$target) {
        $target['status'] = 'unresolved_grazing';
        $target['windows'] = [];
    }
    Http::fake(['*' => Http::response($data)]);
    $this->post('/observe/night', nightInput())->assertOk()
        ->assertSee('No confirmed interval')->assertSee('No confirmed window')
        ->assertDontSee('None at the chosen threshold')->assertDontSee('No matching window in this local night');
});

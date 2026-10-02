<?php

declare(strict_types=1);

use App\Services\Observing\NightConstraints;
use App\Services\Observing\NightPlan;
use App\Services\Observing\NightRequest;
use App\Services\SolarApi\Exceptions\SolarApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

function constrainedInput(): array
{
    return ['date' => '2026-10-01', 'timezone' => 'Europe/London', 'lat' => 51.5, 'lon' => -0.12,
        'targets' => ['moon', 'saturn'], 'min_altitude_deg' => 20, 'sun_altitude_deg' => -12, 'min_moon_separation_deg' => 0];
}

it('sends selected hours and a canonical terrain profile in one private JSON POST', function () {
    $data = json_decode(file_get_contents(base_path('tests/fixtures/observing/night.json')), true, flags: JSON_THROW_ON_ERROR);
    $a = '2026-10-01T20:00:00Z';
    $b = '2026-10-02T04:00:00Z';
    $clip = static fn (array $windows): array => array_values(array_filter(array_map(
        static fn (array $row): array => ['start_utc' => max($a, $row['start_utc']), 'end_utc' => min($b, $row['end_utc'])], $windows),
        static fn (array $row): bool => $row['start_utc'] < $row['end_utc']));
    $data['constraints']['window_start_utc'] = $a;
    $data['constraints']['window_end_utc'] = $b;
    $data['constraints']['horizon_mask'] = [['azimuth_deg' => 0, 'min_altitude_deg' => 10], ['azimuth_deg' => 180, 'min_altitude_deg' => 10]];
    $data['darkness']['intervals'] = $clip($data['darkness']['intervals']);
    foreach ($data['targets'] as &$target) {
        $target['windows'] = $clip($target['windows']);
        $target['status'] = $target['windows'] === [] ? 'no_matching_window' : 'windows_found';
        foreach ($target['samples'] as &$sample) {
            $sample['horizon_altitude_deg'] = 10;
        }
        unset($sample);
    }
    unset($target);
    Http::fake(['*' => Http::response($data)]);
    $this->post('/observe/night', [...constrainedInput(), 'window_start_utc' => $a, 'window_end_utc' => $b, 'horizon' => "180 10\n360 10"])
        ->assertOk()->assertSee('Selected observing interval')->assertSee('Your supplied horizon profile was applied')
        ->assertSessionMissing('_old_input');
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['window_start_utc'] === $a
        && $request['horizon_mask'] == $data['constraints']['horizon_mask'] && ! str_contains($request->url(), '?'));
});

it('rejects unsupported or ambiguous interval and horizon inputs before an API request', function (array $extra) {
    Http::fake();
    $this->post('/observe/night', [...constrainedInput(), ...$extra])->assertStatus(422)->assertSessionMissing('_old_input');
    Http::assertNothingSent();
})->with([
    [['window_start_utc' => '2026-10-01T20:00:00Z']],
    [['window_start_utc' => true, 'window_end_utc' => true]],
    [['window_start_utc' => '2026-10-01T20:00:00Z', 'window_end_utc' => '2026-10-01T20:00:00Z']],
    [['window_start_utc' => '2026-10-01T09:00:00Z', 'window_end_utc' => '2026-10-02T12:00:00Z']],
    [['window_start_utc' => '2026-02-30T20:00:00Z', 'window_end_utc' => '2026-10-02T04:00:00Z']],
    [['window_start_utc' => '2026-10-01T20:00:00+01:00', 'window_end_utc' => '2026-10-02T04:00:00Z']],
    [['horizon' => ['0 10', '180 20']]], [['horizon' => '0 10']], [['horizon' => "0 10\n360 20"]],
    [['horizon' => "0 10\n180 1e999"]], [['horizon' => "0 10\n180 -91"]], [['horizon' => "0 10\n361 20"]],
]);

it('resolves default observing intervals across daylight saving without assuming 24 hours', function () {
    $request = NightRequest::parse([...constrainedInput(), 'date' => '2026-10-24']);
    [$a, $b] = NightConstraints::window($request);
    expect(strtotime($b) - strtotime($a))->toBe(25 * 3600);
    expect($request['horizon_mask'])->toBeNull();
});

it('interpolates across north while preserving negative terrain and the baseline', function () {
    $mask = NightConstraints::horizon("270 -10\n90 30");
    expect(NightConstraints::altitude($mask, 0))->toBe(10.0)
        ->and(NightConstraints::altitude($mask, 360))->toBe(10.0)
        ->and(NightConstraints::altitude($mask, 270))->toBe(-10.0);
    expect(fn () => NightRequest::parse([...constrainedInput(), 'horizon' => implode("\n", array_map(static fn ($n): string => "$n 0", range(0, 72)))]))
        ->toThrow(ValidationException::class);
});

it('rejects a response that silently drops the requested terrain or selected hours', function (string $path, mixed $value) {
    $data = json_decode(file_get_contents(base_path('tests/fixtures/observing/night.json')), true, flags: JSON_THROW_ON_ERROR);
    data_set($data, $path, $value);
    expect(fn () => NightPlan::validate($data, NightRequest::parse(constrainedInput())))->toThrow(SolarApiException::class);
})->with([
    ['constraints.window_start_utc', '2026-10-01T20:00:00Z'], ['constraints.horizon_mask', []],
    ['targets.0.samples.0.required_min_altitude_deg', 0], ['targets.0.samples.0.horizon_altitude_deg', 0],
]);

it('keeps distinct seconds visible for a one-second selected interval', function () {
    $data = json_decode(file_get_contents(base_path('tests/fixtures/observing/night.json')), true, flags: JSON_THROW_ON_ERROR);
    $a = '2026-10-01T20:00:00Z';
    $b = '2026-10-01T20:00:01Z';
    $data['constraints']['window_start_utc'] = $a;
    $data['constraints']['window_end_utc'] = $b;
    $data['darkness'] = ['status' => 'intervals_found', 'intervals' => [['start_utc' => $a, 'end_utc' => $b]]];
    foreach ($data['targets'] as &$target) {
        $target['windows'] = [];
        $target['status'] = 'no_matching_window';
    }
    unset($target);
    Http::fake(['*' => Http::response($data)]);
    $this->post('/observe/night', [...constrainedInput(), 'window_start_utc' => $a, 'window_end_utc' => $b])
        ->assertOk()->assertSee('1 Oct 21:00:00 +01:00')->assertSee('1 Oct 21:00:01 +01:00');
});

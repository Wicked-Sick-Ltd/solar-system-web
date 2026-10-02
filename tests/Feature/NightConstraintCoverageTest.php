<?php

use App\Services\Observing\NightPlan;
use App\Services\Observing\NightRequest;
use App\Services\Observing\NightSession;
use App\Services\SolarApi\Exceptions\SolarApiException;
use Illuminate\Support\Facades\Http;

function coverageRequest(): array
{
    return ['date' => '2026-10-01', 'timezone' => 'Europe/London', 'lat' => 51.5, 'lon' => -0.12,
        'targets' => ['moon', 'saturn'], 'min_altitude_deg' => 20, 'sun_altitude_deg' => -12, 'min_moon_separation_deg' => 0];
}

// Enum variations are structural/render fixtures, not independent astronomy evidence.
function coverageFixture(string $altitude = 'partial', string $darkness = 'partial'): array
{
    $data = json_decode(file_get_contents(base_path('tests/fixtures/observing/night.json')), true, flags: JSON_THROW_ON_ERROR);
    foreach ($data['targets'] as &$target) {
        $target['constraint_coverage'] = ['scope' => 'selected_interval',
            'start_utc' => $data['constraints']['window_start_utc'], 'end_utc' => $data['constraints']['window_end_utc'],
            'altitude' => $altitude, 'darkness' => $darkness];
        if ($altitude === 'never_satisfied' || $darkness === 'never_satisfied') {
            $target['windows'] = [];
            $target['status'] = 'no_matching_window';
        }
    }
    unset($target);
    if ($darkness === 'always_satisfied') {
        $data['darkness'] = ['status' => 'intervals_found', 'intervals' => [[
            'start_utc' => $data['constraints']['window_start_utc'], 'end_utc' => $data['constraints']['window_end_utc'],
        ]]];
    } elseif ($darkness === 'never_satisfied') {
        $data['darkness'] = ['status' => 'no_matching_interval', 'intervals' => []];
    }

    return $data;
}

it('accepts legacy plans without inventing independent constraint coverage', function () {
    $data = coverageFixture();
    foreach ($data['targets'] as &$target) {
        unset($target['constraint_coverage']);
    }
    unset($target);
    Http::fake(['*' => Http::response($data)]);
    $this->post('/observe/night', coverageRequest())->assertOk()->assertDontSee('Individual constraints in the selected interval');
    $out = NightSession::summary(NightPlan::validate($data, NightRequest::parse(coverageRequest())), NightRequest::parse(coverageRequest()));
    expect($out['targets'][0])->not->toHaveKey('constraint_coverage');
});

it('shows refined states with interval and uncertainty scope while retaining them in JSON summaries', function (string $status, string $text) {
    $data = coverageFixture($status, $status);
    Http::fake(['*' => Http::response($data)]);
    $response = $this->post('/observe/night', coverageRequest())->assertOk()
        ->assertSee('Individual constraints in the selected interval')->assertSee($text)
        ->assertSee('do not establish permanent rise/set behaviour')->assertSee('does not guarantee visibility')
        ->assertHeader('Cache-Control', 'no-store, private');
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//*[@data-constraint-coverage]')->length)->toBe(2);
    $summary = json_decode($xpath->query('//*[@data-night-session-data]')->item(0)->textContent, true, flags: JSON_THROW_ON_ERROR);
    expect($summary['targets'][0]['constraint_coverage'])->toBe($data['targets'][0]['constraint_coverage']);
    Http::assertSentCount(1);
})->with([
    ['always_satisfied', 'Satisfied throughout this interval.'],
    ['never_satisfied', 'No resolved part of this interval satisfies this constraint.'],
    ['partial', 'Satisfied during part of this interval.'],
    ['unresolved', 'Coverage remains unresolved in this interval'],
]);

it('rejects present malformed or mismatched optional diagnostics before rendering', function (mixed $coverage) {
    $data = coverageFixture();
    $data['targets'][0]['constraint_coverage'] = $coverage;
    Http::fake(['*' => Http::response($data)]);
    $this->post('/observe/night', coverageRequest())->assertStatus(503)->assertDontSee('Your night');
})->with([
    [null], [false], [1], ['always_satisfied'], [[]],
    [['scope' => 'all_time', 'start_utc' => '2026-10-01T11:00:00Z', 'end_utc' => '2026-10-02T11:00:00Z', 'altitude' => 'partial', 'darkness' => 'partial']],
    [['scope' => 'selected_interval', 'start_utc' => '2026-10-01T11:00:01Z', 'end_utc' => '2026-10-02T11:00:00Z', 'altitude' => 'partial', 'darkness' => 'partial']],
    [['scope' => 'selected_interval', 'start_utc' => '2026-10-01T11:00:00Z', 'end_utc' => '2026-10-02T12:00:00+01:00', 'altitude' => 'partial', 'darkness' => 'partial']],
    [['scope' => 'selected_interval', 'start_utc' => '2026-10-01T11:00:00Z', 'end_utc' => '2026-10-02T11:00:00Z', 'altitude' => true, 'darkness' => 'partial']],
    [['scope' => 'selected_interval', 'start_utc' => '2026-10-01T11:00:00Z', 'end_utc' => '2026-10-02T11:00:00Z', 'altitude' => 'circumpolar', 'darkness' => 'partial']],
    [['scope' => 'selected_interval', 'start_utc' => '2026-10-01T11:00:00Z', 'end_utc' => '2026-10-02T11:00:00Z', 'altitude' => 'partial']],
    [['scope' => 'selected_interval', 'start_utc' => '2026-10-01T11:00:00Z', 'end_utc' => '2026-10-02T11:00:00Z', 'altitude' => 'partial', 'darkness' => [], 'extra' => true]],
]);

it('requires consistent darkness diagnostics and rejects impossible never-with-windows results', function (string $field, string $value) {
    $data = coverageFixture();
    $data['targets'][0]['constraint_coverage'][$field] = $value;
    expect(fn () => NightPlan::validate($data, NightRequest::parse(coverageRequest())))->toThrow(SolarApiException::class);
})->with([['altitude', 'never_satisfied'], ['darkness', 'never_satisfied'], ['darkness', 'always_satisfied'], ['darkness', 'unresolved']]);

it('keeps independent coverage distinct from the older combined grazing status', function () {
    $data = coverageFixture('always_satisfied', 'partial');
    $data['targets'][0]['status'] = 'unresolved_grazing';
    expect(NightPlan::validate($data, NightRequest::parse(coverageRequest())))->toBe($data);
    $data = coverageFixture('unresolved', 'unresolved');
    expect($data['targets'][0]['status'])->toBe('windows_found')
        ->and(NightPlan::validate($data, NightRequest::parse(coverageRequest())))->toBe($data);
});

it('supports optional per-target rollout without manufacturing diagnostics for missing rows', function () {
    $data = coverageFixture();
    unset($data['targets'][1]['constraint_coverage']);
    $query = NightRequest::parse(coverageRequest());
    $out = NightSession::summary(NightPlan::validate($data, $query), $query);
    expect($out['targets'][0]['constraint_coverage'])->toBe($data['targets'][0]['constraint_coverage'])
        ->and($out['targets'][1])->not->toHaveKey('constraint_coverage');
});

it('retains actual JPL diagnostics across a 25-hour night and an explicitly selected eight-hour interval', function (string $file, bool $selected) {
    $data = json_decode(gzdecode(file_get_contents(base_path('tests/fixtures/observing/'.$file.'.json.gz'))), true, flags: JSON_THROW_ON_ERROR);
    $input = ['date' => '2026-10-24', 'timezone' => 'Europe/London', 'lat' => 51.5, 'lon' => -0.12,
        'targets' => ['moon'], 'catalogue_targets' => 'bsc5p:hr2491,openngc:NGC0224', 'min_altitude_deg' => 20,
        'sun_altitude_deg' => -12, 'min_moon_separation_deg' => 30, 'horizon' => "0 5\n180 15"];
    if ($selected) {
        $input['window_start_utc'] = '2026-10-24T20:00:00Z';
        $input['window_end_utc'] = '2026-10-25T04:00:00Z';
    }
    $query = NightRequest::parse($input);
    $plan = NightPlan::validate($data, $query);
    $coverage = $plan['targets'][2]['constraint_coverage'];
    expect($plan['night']['duration_hours'])->toBe(25.0)
        ->and($coverage['altitude'])->toBe($selected ? 'always_satisfied' : 'partial')
        ->and($coverage['darkness'])->toBe($selected ? 'always_satisfied' : 'partial')
        ->and($coverage['start_utc'])->toBe($selected ? '2026-10-24T20:00:00Z' : '2026-10-24T11:00:00Z')
        ->and($coverage['end_utc'])->toBe($selected ? '2026-10-25T04:00:00Z' : '2026-10-25T12:00:00Z');
    Http::fake(['*' => Http::response($data)]);
    $response = $this->post('/observe/night', $input)->assertOk()->assertSee('Individual constraints in the selected interval');
    if ($selected) {
        $response->assertSee('24 Oct 21:00:00 +01:00')->assertSee('25 Oct 04:00:00 +00:00')->assertSee('Satisfied throughout this interval.');
        // A valid whole-night endpoint cannot masquerade as selected-window coverage.
        $data['targets'][2]['constraint_coverage']['end_utc'] = $data['night']['end_utc'];
        expect(fn () => NightPlan::validate($data, $query))->toThrow(SolarApiException::class);
    }
})->with([['coverage-jpl-dst', false], ['coverage-jpl-selected', true]]);

it('rejects partial darkness without both satisfied and unsatisfied resolved intervals', function (string $actual) {
    $data = coverageFixture('partial', $actual);
    foreach ($data['targets'] as &$target) {
        $target['constraint_coverage']['darkness'] = 'partial';
    }
    unset($target);
    expect(fn () => NightPlan::validate($data, NightRequest::parse(coverageRequest())))->toThrow(SolarApiException::class);
})->with(['always_satisfied', 'never_satisfied']);

it('uses the same optional diagnostics on an explained shortlist without another request', function () {
    $data = json_decode(gzdecode(file_get_contents(base_path('tests/fixtures/observing/shortlist.json.gz'))), true, flags: JSON_THROW_ON_ERROR);
    foreach ($data['plan']['targets'] as &$target) {
        $target['constraint_coverage'] = ['scope' => 'selected_interval', 'start_utc' => $data['request']['window_start_utc'],
            'end_utc' => $data['request']['window_end_utc'], 'altitude' => 'partial', 'darkness' => 'partial'];
    }
    unset($target);
    Http::fake(['*' => Http::response($data)]);
    $input = coverageRequest();
    unset($input['targets']);
    $input += ['equipment_mode' => 'naked_eye', 'preference' => 'balanced', 'shortlist_limit' => 3];
    $this->post('/observe/shortlist', $input)->assertOk()->assertSee('Your explained shortlist')
        ->assertSee('Individual constraints in the selected interval')->assertSee('Satisfied during part of this interval.');
    Http::assertSentCount(1);
});

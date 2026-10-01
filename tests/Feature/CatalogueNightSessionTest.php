<?php

declare(strict_types=1);

use App\Services\Observing\NightPlan;
use App\Services\Observing\NightRequest;
use App\Services\Observing\NightSession;
use Illuminate\Support\Facades\Http;

function catalogueNightFixture(): array
{
    return json_decode(file_get_contents(base_path('tests/fixtures/observing/catalogue-night.json')), true, flags: JSON_THROW_ON_ERROR);
}
function catalogueNightInput(): array
{
    return ['date' => '2026-10-01', 'timezone' => 'Europe/London', 'lat' => 51.5, 'lon' => -0.12,
        'targets' => ['moon'], 'catalogue_targets' => 'bsc5p:hr2491, openngc:NGC0224', 'min_altitude_deg' => 20,
        'sun_altitude_deg' => -12, 'min_moon_separation_deg' => 30, 'horizon' => "0 10\n90 20\n180 5\n270 15"];
}

it('prepares catalogue target hints without copying query coordinates or calculating', function () {
    Http::fake();
    $this->get('/observe/night?targets[]=bsc5p:hr2491&targets[]=moon&lat=12.34567')->assertOk()
        ->assertSee('value="bsc5p:hr2491"', false)->assertDontSee('12.34567')->assertDontSee('Your night');
    $this->get('/observe/night?targets[]=sun')->assertStatus(422)->assertSee('target link is invalid');
    Http::assertNothingSent();
});

it('renders mixed catalogue and solar results with source provenance and correct journal references', function () {
    Http::fake(['*' => Http::response(catalogueNightFixture())]);
    $this->post('/observe/night', catalogueNightInput())->assertOk()
        ->assertSee('JPL DE440s')->assertSee('Catalogue source and coordinate assumptions')
        ->assertSee('physical distance are unknown')->assertSee('Source snapshot SHA-256')
        ->assertSee('catalogue=starter', false)->assertSee('target=bsc5p%3Ahr2491', false)
        ->assertDontSee('planet-bsc5p')->assertSee('Download session JSON')
        ->assertSessionMissing('_old_input');
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request['targets'] === 'moon,bsc5p:hr2491,openngc:NGC0224');
});

it('rejects duplicate or excessive mixed targets before any calculation', function (array $changes) {
    Http::fake();
    $this->post('/observe/night', [...catalogueNightInput(), ...$changes])->assertStatus(422);
    Http::assertNothingSent();
})->with([
    [['targets' => ['moon', 'bsc5p:hr2491']]],
    [['catalogue_targets' => ['bsc5p:hr2491']]],
    [['catalogue_targets' => 'Sun']],
    [['targets' => ['moon', 'mercury', 'venus', 'mars', 'jupiter', 'saturn', 'uranus', 'neptune']]],
]);

it('does not render catalogue directions with invented distances or missing source metadata', function (string $path, mixed $value) {
    $data = catalogueNightFixture();
    data_set($data, $path, $value);
    Http::fake(['*' => Http::response($data)]);
    $this->post('/observe/night', catalogueNightInput())->assertStatus(503)->assertDontSee('Your night')->assertDontSee('Download session JSON');
})->with([
    ['targets.1.samples.0.distance_au', 10], ['targets.1.catalogue.frame_transform', null],
    ['targets.1.catalogue.input_coordinates.frame', 'ICRS'], ['method.iers.snapshot.sha256', 'unknown'],
    ['method.kernel.private_path', '/private/kernel'],
]);

it('exports exact original inputs and scientific identities without copying arbitrary fields or chart samples', function () {
    $query = NightRequest::parse(catalogueNightInput());
    $plan = NightPlan::validate(catalogueNightFixture(), $query);
    $plan['targets'][0]['windows'][0]['private_extra'] = 'never export';
    $plan['observer']['private_extra'] = 'never export';
    $query['unrelated'] = 'never export';
    $out = NightSession::summary($plan, $query);
    expect($out['input']['targets'])->toBe('moon,bsc5p:hr2491,openngc:NGC0224')
        ->and($out['method']['kernel']['sha256'])->toBe($plan['method']['kernel']['sha256'])
        ->and($out['targets'][1]['catalogue'])->toBe($plan['targets'][1]['catalogue'])
        ->and($out['constraints']['horizon_mask'])->toBe($query['horizon_mask'])
        ->and(json_encode($out))->not->toContain('private_extra', 'never export', '"samples"', 'unrelated');
});

it('encodes script terminators safely inside the downloadable session metadata', function () {
    $data = catalogueNightFixture();
    $data['targets'][0]['name'] = '</script><img src=x onerror=alert(1)>';
    Http::fake(['*' => Http::response($data)]);
    $this->post('/observe/night', catalogueNightInput())->assertOk()
        ->assertDontSee('</script><img', false)->assertSee('\\u003C/script\\u003E', false);
});

<?php

declare(strict_types=1);

use App\Services\CloseApproachFlyby;
use App\Services\HorizonsClient;
use App\Services\SolarApi\Data\CloseApproach;
use App\Services\SolarApi\Data\ObjectDetail;
use App\Support\Flyby\TwoBody;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => fakeSolar());

it('draws an approximate flyby on the object page when horizons has no vectors', function () {
    $this->travelTo(new DateTimeImmutable('2026-10-10T12:00:00Z'));

    $response = $this->get('/objects/ast-20099942-apophis');

    $response->assertOk()
        ->assertSee('Flyby geometry')
        ->assertSee('The path is approximate', escape: false)
        ->assertSee('0.10 LD')
        ->assertSee('2029-04-13 21:46 UTC')
        ->assertSee('id="flyby-title"', escape: false)
        ->assertSee('id="flyby-desc"', escape: false)
        ->assertSee('id="flyby-summary"', escape: false)
        ->assertSee('data-flyby-path', escape: false)
        ->assertSee('data-flyby-moon-orbit', escape: false)
        ->assertSee('data-flyby-sun', escape: false)
        ->assertSee('class="h-auto w-full max-w-full"', escape: false)
        ->assertDontSee('<animate', escape: false);

    preg_match('/data-flyby-path points="([^"]+)"/', $response->getContent(), $path);
    expect(substr_count($path[1] ?? '', ' '))->toBeGreaterThan(10);

    $css = file_get_contents(resource_path('css/app.css'));
    expect($css)->toContain('.flyby-path { fill: none; stroke: #e6c98a;')
        ->and($css)->toContain("[data-theme='light'] .flyby-path");
});

it('does not draw a flyby for an object with no earth encounter', function () {
    $this->get('/objects/planet-saturn')->assertOk()->assertDontSee('data-flyby', escape: false);
});

it('uses a cached horizons arc and does not call the path approximate', function () {
    config(['cache.default' => 'array']);
    Cache::flush();
    // fakeSolar() already stubbed every host. Swap in a fresh factory so this
    // test can answer Horizons itself.
    Http::swap(new Factory);
    Http::preventStrayRequests();
    $rows = [
        '2461325.5, A.D. 2026-Oct-12 00:00:00.0000, 1.0E+06, 5.0E+05, -1.0E+05, -8.0, -5.0, 1.0,',
        '2461328.5, A.D. 2026-Oct-15 00:00:00.0000, -2.5E+03, -6.8E+04, -2.08E+05, -8.4, -5.6, 1.9,',
        '2461331.5, A.D. 2026-Oct-18 00:00:00.0000, -1.2E+06, -8.0E+05, 1.0E+05, -8.2, -5.5, 2.0,',
    ];
    Http::fake(function ($request) use ($rows) {
        $command = (string) ($request['COMMAND'] ?? '');
        $line = match (true) {
            str_contains($command, '301') => '2461328.5, A.D. 2026-Oct-15 00:00:00.0000, -1.29E+05, -3.79E+05, -3.4E+04, 0.9, -0.3, 0.02,',
            str_contains($command, "'10'") => '2461328.5, A.D. 2026-Oct-15 00:00:00.0000, -1.39E+08, -5.42E+07, 3.0E+03, 1.0, -2.0, 0.0,',
            default => implode("\n", $rows),
        };

        return Http::response(['result' => "\$\$SOE\n{$line}\n\$\$EOE\n"]);
    });

    $object = ObjectDetail::fromArray([
        'id' => 'ast-54661369',
        'name' => '2026 TP6',
        'designation' => '(2026 TP6)',
        'object_type' => 'asteroid',
        'orbital' => [
            'semi_major_axis_au' => 0.9775, 'eccentricity' => 0.347, 'inclination_deg' => 3.57,
            'longitude_ascending_node_deg' => 22.61, 'argument_periapsis_deg' => 112.07,
            'mean_anomaly_deg' => 155.98, 'epoch_jd' => 2461200.5, 'mean_motion_deg_per_day' => 1.0198,
        ],
        'close_approaches' => [[
            'body' => 'Earth', 'cd_iso' => '2026-10-14T23:57:00Z', 'dist_au' => 0.0014650778151516,
        ]],
    ]);

    $frame = app(CloseApproachFlyby::class)->forObject($object, CarbonImmutable::parse('2026-10-10T00:00:00Z'));
    $again = app(CloseApproachFlyby::class)->forObject($object, CarbonImmutable::parse('2026-10-10T00:00:00Z'));

    expect($frame)->not->toBeNull()
        ->and($frame->trajectoryApproximate)->toBeFalse()
        ->and($frame->moonApproximate)->toBeFalse()
        ->and($frame->summary)->toContain('JPL Horizons')
        ->and($frame->summary)->not->toContain('The path is approximate')
        ->and($again?->distanceLabel)->toBe($frame->distanceLabel);

    $arcCalls = Http::recorded()->filter(fn ($pair): bool => str_contains((string) ($pair[0]['COMMAND'] ?? ''), '54661369'));
    expect($arcCalls)->toHaveCount(1);
});

it('parses a horizons vector block and ignores a miss', function () {
    $parsed = HorizonsClient::parse(<<<'TEXT'
$$SOE
2461328.500000000, A.D. 2026-Oct-15 00:00:00.0000,  -4.067372986495495E+03, -6.900090295154757E+04, -2.079970376815444E+05, -8.396090721528839E+00, -5.648064389001231E+00,  1.941615057767628E+00,
$$EOE
TEXT);

    expect($parsed)->toHaveCount(1)
        ->and($parsed[0]->xKm)->toEqualWithDelta(-4067.37, 0.1)
        ->and($parsed[0]->vxKmS)->toEqualWithDelta(-8.396, 0.001)
        ->and(HorizonsClient::parse('No matches found'))->toBeNull()
        ->and(TwoBody::KM_PER_AU)->toBe(149597870.7);
});

it('chooses the next earth encounter and a horizons command', function () {
    $flyby = app(CloseApproachFlyby::class);
    $object = ObjectDetail::fromArray(apophisDetail());

    expect($flyby->commands($object))->toBe(['99942', '2004 MN4'])
        ->and($flyby->select($object->closeApproaches, CarbonImmutable::parse('2026-10-10T00:00:00Z'))?->cdIso)->toBe('2029-04-13T21:46:00Z')
        ->and($flyby->select($object->closeApproaches, CarbonImmutable::parse('2030-01-01T00:00:00Z'))?->cdIso)->toBe('2036-03-30T00:00:00Z')
        ->and($flyby->select($object->closeApproaches, CarbonImmutable::parse('2040-01-01T00:00:00Z'))?->cdIso)->toBe('2036-03-30T00:00:00Z')
        ->and($flyby->commands(ObjectDetail::fromArray([
            'id' => 'ast-54661369', 'name' => '2026 TP6', 'designation' => '(2026 TP6)',
        ])))->toBe(['54661369', '2026 TP6']);

    $moon = new CloseApproach('Moon', '2029-04-13T21:46:00Z', 0.01, null, null, null, null);
    expect($flyby->select([$moon]))->toBeNull();
});

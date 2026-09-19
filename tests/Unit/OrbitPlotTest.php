<?php

declare(strict_types=1);

use App\Services\SolarApi\Data\OrbitalElements;
use App\Services\SolarApi\Data\Position;
use App\Support\OrbitPlot;

// Live values captured from api.sol.wickedsick.com on 2026-09-19T00:00Z.
function venusElements(): OrbitalElements
{
    return OrbitalElements::fromArray([
        'semi_major_axis_au' => 0.72333566, 'eccentricity' => 0.00677672, 'inclination_deg' => 3.39467605,
        'longitude_ascending_node_deg' => 76.679843, 'argument_periapsis_deg' => 54.884,
        'mean_anomaly_deg' => 50.115, 'orbital_period_days' => 224.701,
    ]);
}

function venusPosition(): Position
{
    return Position::fromArray([
        'x_au' => 0.653831595555249, 'y_au' => -0.3170318450598295, 'z_au' => -0.04207308125849239,
        'distance_from_sun_au' => 0.7278565039174592, 'true_anomaly_deg' => 202.5895193743544,
    ]);
}

function earthPosition(): Position
{
    return Position::fromArray([
        'x_au' => 1.0028213281111156, 'y_au' => -0.056090525073772546, 'z_au' => 1.4987944071576875e-08,
        'distance_from_sun_au' => 1.0043887509911655, 'true_anomaly_deg' => 253.86162686287832,
    ]);
}

it('projects the orbit into the ecliptic so the path passes through the API position', function () {
    $point = OrbitPlot::orbitPoint(venusElements(), 202.5895193743544);

    expect($point['x'])->toEqualWithDelta(0.6538, 0.001)
        ->and($point['y'])->toEqualWithDelta(-0.3170, 0.001);
});

it('samples a closed orbit path', function () {
    $path = OrbitPlot::orbitPath(venusElements(), 72);

    expect($path)->toHaveCount(73)
        ->and($path[0]['x'])->toEqualWithDelta($path[72]['x'], 1e-9)
        ->and($path[0]['y'])->toEqualWithDelta($path[72]['y'], 1e-9);
});

it('draws a circle for an orbit with no inclination or node data', function () {
    $circular = OrbitalElements::fromArray(['semi_major_axis_au' => 1.0, 'eccentricity' => 0.0, 'orbital_period_days' => 365.25]);

    foreach (OrbitPlot::orbitPath($circular, 8) as $p) {
        expect(hypot($p['x'], $p['y']))->toEqualWithDelta(1.0, 1e-9);
    }
});

it('refuses to draw open (e >= 1) or element-less orbits', function () {
    $hyperbolic = OrbitalElements::fromArray(['semi_major_axis_au' => -2.0, 'eccentricity' => 1.2]);
    $empty = OrbitalElements::fromArray([]);

    expect(OrbitPlot::orbitPath($hyperbolic))->toBe([])
        ->and(OrbitPlot::orbitPath($empty))->toBe([]);
});

it('measures the Earth–object distance from the two vectors', function () {
    // Venus–Earth on 2026-09-19: ~0.436 AU (cross-checked against /sky distance_from_earth_au).
    expect(OrbitPlot::distanceAu(venusPosition(), earthPosition()))->toEqualWithDelta(0.436, 0.002);
});

it('maps AU to SVG space with north up and a linear scale fitted to the furthest body', function () {
    $scale = OrbitPlot::scale([venusPosition(), earthPosition()], venusElements(), maxRadiusPx: 100.0);

    // Earth's orbit (1.004 AU today) is the furthest thing drawn → it lands at the edge.
    expect($scale)->toEqualWithDelta(100.0 / 1.0043887509911655, 1e-6);

    $sun = OrbitPlot::toSvg(0.0, 0.0, $scale, centre: 120.0);
    $east = OrbitPlot::toSvg(1.0, 0.0, $scale, centre: 120.0);   // +x → right
    $north = OrbitPlot::toSvg(0.0, 1.0, $scale, centre: 120.0);  // +y → up (smaller SVG y)

    expect($sun)->toBe(['cx' => 120.0, 'cy' => 120.0])
        ->and($east['cx'])->toBeGreaterThan(120.0)->and($east['cy'])->toEqualWithDelta(120.0, 1e-9)
        ->and($north['cy'])->toBeLessThan(120.0);
});

it('fits the scale to the aphelion when the object ranges further than Earth', function () {
    $halley = OrbitalElements::fromArray(['semi_major_axis_au' => 17.93, 'eccentricity' => 0.968, 'orbital_period_days' => 27500]);
    $far = Position::fromArray(['x_au' => -19.4, 'y_au' => 27.6, 'z_au' => -9.9, 'distance_from_sun_au' => 35.17, 'true_anomaly_deg' => 180.8]);

    // aphelion = a(1+e) = 35.29 AU, just beyond today's 35.17 → the whole orbit fits.
    expect(OrbitPlot::scale([$far, earthPosition()], $halley, 100.0))->toEqualWithDelta(100.0 / 35.29, 0.01);
});

it('formats light travel time in minutes or hours', function () {
    expect(OrbitPlot::lightTime(0.436))->toBe('3.6 light-minutes')
        ->and(OrbitPlot::lightTime(8.82))->toBe('1.2 light-hours')
        ->and(OrbitPlot::lightTime(35.2))->toBe('4.9 light-hours');
});

<?php

declare(strict_types=1);

use App\Services\SolarApi\Data\OrbitalElements;
use App\Support\Flyby\FlybyFrame;
use App\Support\Flyby\LowPrecisionEphemeris;
use App\Support\Flyby\TwoBody;
use App\Support\Flyby\VectorSample;
use App\Support\OrbitPlot;
use Carbon\CarbonImmutable;

/**
 * Geocentric ecliptic samples for (2026 TP6), 11–17 Oct 2026, from JPL Horizons
 * (centre 500@399, ecliptic J2000, km). The 14 Oct 23:57 row is the close approach.
 *
 * @return list<VectorSample>
 */
function tp6Samples(CarbonImmutable $start): array
{
    $coords = [
        [2.148773826439947E+06, 1.391676880212042E+06, -6.639682080295229E+05, -8.292543572494665, -5.624038793230666, 1.736176537398956],
        [1.790678909082621E+06, 1.148737909362833E+06, -5.889248127440005E+05, -8.286314306787713, -5.623235133878071, 1.738090235484897],
        [1.432798208423615E+06, 9.058178343997756E+05, -5.137922714545678E+05, -8.282746431183298, -5.623242866618718, 1.740356516901389],
        [1.074995508038133E+06, 6.628681256339008E+05, -4.385453772996397E+05, -8.282990942189429, -5.624781060750349, 1.743533447439930],
        [7.170498487872183E+05, 4.197899382830105E+05, -3.631127758402259E+05, -8.290258605044158, -5.629681343516500, 1.749535803936306],
        [3.584501014119089E+05, 1.763332577116543E+05, -2.872100996389216E+05, -8.317455015830996, -5.644110605047295, 1.769488602657433],
        [-2.556074547678232E+03, -6.798420922375433E+04, -2.083464003246712E+05, -8.396112442023400, -5.648531449830388, 1.940191667404968],
        [-3.634416362068653E+05, -3.093547848506812E+05, -1.203817815986283E+05, -8.312610538069686, -5.545537981715604, 2.074110740701258],
        [-7.217867196020186E+05, -5.482798367867249E+05, -3.059962346082728E+04, -8.283660910747662, -5.520787636674092, 2.079940608017533],
        [-1.079429190575391E+06, -7.865608169634303E+05, 5.925793916397126E+04, -8.275606928118748, -5.511955609121071, 2.079930286070880],
        [-1.436899873454571E+06, -1.024578823137946E+06, 1.490993188357414E+05, -8.274780818878819, -5.507897097733466, 2.079361151094913],
        [-1.794425951195806E+06, -1.262475356064442E+06, 2.389130787033578E+05, -8.277879390188668, -5.506120696027619, 2.078668498102561],
        [-2.152148228363633E+06, -1.500328649270805E+06, 3.286949791247296E+05, -8.283744986461059, -5.505816614817291, 2.077884681088229],
    ];

    return array_map(fn (array $row, int $index): VectorSample => new VectorSample(
        TwoBody::julianDay($start->addHours($index * 12)),
        $row[0], $row[1], $row[2], $row[3], $row[4], $row[5],
    ), $coords, array_keys($coords));
}

function tp6Frame(): FlybyFrame
{
    $start = CarbonImmutable::parse('2026-10-11T23:57:00Z');
    $approach = $start->addHours(72);
    $sun = new VectorSample(TwoBody::julianDay($approach), -0.929340514823053 * TwoBody::KM_PER_AU, -0.3620231900595813 * TwoBody::KM_PER_AU, 0.0);
    $moon = new VectorSample(TwoBody::julianDay($approach), -129100.5901781187, -379537.9430025715, -34775.49078256162);

    $frame = FlybyFrame::compose(
        '2026 TP6',
        $approach,
        0.0014650778151516,
        tp6Samples($start),
        $moon,
        $sun,
        trajectoryApproximate: false,
        moonApproximate: false,
        objectElements: OrbitalElements::fromArray([
            'semi_major_axis_au' => 0.9775178129541988,
            'eccentricity' => 0.3472117308582373,
            'inclination_deg' => 3.565342377117589,
            'longitude_ascending_node_deg' => 22.61198008514457,
            'argument_periapsis_deg' => 112.0675891250749,
            'mean_anomaly_deg' => 155.9835553928668,
            'epoch_jd' => 2461200.5,
            'mean_motion_deg_per_day' => 1.019804804538491,
        ]),
    );

    expect($frame)->not->toBeNull();

    return $frame;
}

it('keeps the horizons curve instead of replacing it with the chord', function () {
    $samples = tp6Samples(CarbonImmutable::parse('2026-10-11T23:57:00Z'));

    expect(FlybyFrame::chordDeviationKm($samples))->toBeGreaterThan(8000)
        ->and($samples)->toHaveCount(13)
        ->and($samples[6]->distanceKm())->toEqualWithDelta(219172.6, 1.0)
        ->and(hypot($samples[6]->xKm, $samples[6]->yKm))->toEqualWithDelta(68032.2, 1.0);
});

it('projects the encounter with earth at the centre, north up, and the catalogue distance', function () {
    $frame = tp6Frame();

    expect($frame->trajectoryApproximate)->toBeFalse()
        ->and($frame->distanceLabel)->toBe('0.57 LD · 219,173 km')
        ->and($frame->timeLabel)->toBe('2026-10-14 23:57 UTC')
        ->and($frame->planeNote)->toContain('south of the ecliptic')
        ->and($frame->summary)->toContain('JPL Horizons')
        ->and($frame->summary)->not->toContain('The path is approximate')
        ->and($frame->path)->toHaveCount(13);

    // Closest approach is near the earth marker; +y ecliptic is up, so a negative
    // Y sample sits below the centre.
    expect(hypot($frame->closestX - $frame->earthX, $frame->closestY - $frame->earthY))->toBeLessThan(20)
        ->and($frame->moon['y'])->toBeGreaterThan($frame->earthY)
        ->and($frame->moon['x'])->toBeLessThan($frame->earthX)
        ->and($frame->sun['x2'])->toBeLessThan($frame->earthX)
        ->and($frame->sun['y2'])->toBeGreaterThan($frame->earthY);

    $moonDrawn = hypot($frame->moon['x'] - $frame->earthX, $frame->moon['y'] - $frame->earthY);
    expect($moonDrawn / $frame->moonOrbitRadius)->toEqualWithDelta(hypot(-129100.5901781187, -379537.9430025715) / TwoBody::MOON_ORBIT_KM, 0.02)
        ->and($frame->ticks)->not->toBeEmpty()
        ->and($frame->arrow)->not->toBeNull()
        ->and($frame->inset)->not->toBeNull();
});

it('points the direction arrow along the outgoing velocity', function () {
    $frame = tp6Frame();
    $parts = array_map(
        fn (string $pair): array => array_map(floatval(...), explode(',', $pair)),
        explode(' ', (string) $frame->arrow['points']),
    );
    $tip = $parts[0];
    $wingMid = [($parts[1][0] + $parts[2][0]) / 2, ($parts[1][1] + $parts[2][1]) / 2];
    // Velocity at closest approach is toward -x and -y ecliptic, which is left and down.
    expect($tip[0])->toBeLessThan($wingMid[0])
        ->and($tip[1])->toBeGreaterThan($wingMid[1]);
});

it('propagates a circular orbit onto the ecliptic axes', function () {
    $orbit = OrbitalElements::fromArray([
        'semi_major_axis_au' => 1.0,
        'eccentricity' => 0.0,
        'inclination_deg' => 0.0,
        'longitude_ascending_node_deg' => 0.0,
        'argument_periapsis_deg' => 0.0,
        'mean_anomaly_deg' => 90.0,
        'epoch_jd' => 2451545.0,
        'orbital_period_days' => 365.25,
    ]);

    $point = TwoBody::heliocentricAu($orbit, 2451545.0);

    expect($point['x'])->toEqualWithDelta(0.0, 1e-9)
        ->and($point['y'])->toEqualWithDelta(1.0, 1e-6)
        ->and($point['z'])->toEqualWithDelta(0.0, 1e-9);

    $plotted = OrbitPlot::orbitPoint($orbit, 90.0);
    expect($point['x'])->toEqualWithDelta($plotted['x'], 1e-9)
        ->and($point['y'])->toEqualWithDelta($plotted['y'], 1e-9);
});

it('refuses a hyperbolic orbit and a copy of earth sitting on earth', function () {
    $hyperbolic = OrbitalElements::fromArray([
        'semi_major_axis_au' => -2.0, 'eccentricity' => 1.2, 'inclination_deg' => 10,
        'longitude_ascending_node_deg' => 20, 'argument_periapsis_deg' => 30,
        'mean_anomaly_deg' => 10, 'epoch_jd' => 2451545.0, 'orbital_period_days' => 100,
    ]);
    expect(TwoBody::heliocentricAu($hyperbolic, 2460000.0))->toBeNull();

    $samples = TwoBody::geocentricArc(TwoBody::earth(), CarbonImmutable::parse('2026-10-14T00:00:00Z'), halfDays: 1, stepHours: 12);
    expect($samples)->not->toBeEmpty()
        ->and($samples[0]->distanceKm())->toBeLessThan(1.0);
});

it('places the low-precision sun in the same ecliptic quadrant as horizons', function () {
    $sun = LowPrecisionEphemeris::sun(CarbonImmutable::parse('2026-10-14T23:57:00Z'));
    $horizons = atan2(-0.3620231900595813, -0.929340514823053);
    $angle = atan2($sun->yKm, $sun->xKm);

    expect($sun->xKm)->toBeLessThan(0)
        ->and($sun->yKm)->toBeLessThan(0)
        ->and(abs($angle - $horizons))->toBeLessThan(deg2rad(1.0));

    $moon = LowPrecisionEphemeris::moon(CarbonImmutable::parse('2026-10-14T23:57:00Z'));
    expect($moon->distanceKm())->toBeGreaterThan(350000)
        ->and($moon->distanceKm())->toBeLessThan(430000);
});

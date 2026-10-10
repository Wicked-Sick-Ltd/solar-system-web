<?php

declare(strict_types=1);

namespace App\Support\Flyby;

use App\Services\SolarApi\Data\OrbitalElements;
use Carbon\CarbonImmutable;

/**
 * Two-body Kepler propagation in the ecliptic J2000 frame.
 *
 * Earth's elements are the catalogue J2000 set for planet-earth (solar-system-db).
 * A result is null when the elements cannot describe a closed, oriented ellipse —
 * parabolic and hyperbolic orbits are left to a numerical ephemeris rather than
 * drawn as a straight line.
 */
final class TwoBody
{
    public const float KM_PER_AU = 149597870.7;

    public const float MOON_ORBIT_KM = 384400.0;

    public const float EARTH_RADIUS_KM = 6378.137;

    public const float MOON_RADIUS_KM = 1737.4;

    public static function earth(): OrbitalElements
    {
        return OrbitalElements::fromArray([
            'epoch_jd' => 2451545.0,
            'semi_major_axis_au' => 1.00000261,
            'eccentricity' => 0.01671123,
            'inclination_deg' => -0.00001531,
            'longitude_ascending_node_deg' => 0.0,
            'argument_periapsis_deg' => 102.937,
            'mean_anomaly_deg' => 358.617,
            'orbital_period_days' => 365.256,
        ]);
    }

    public static function julianDay(CarbonImmutable $instant): float
    {
        return 2440587.5 + ($instant->getTimestamp() + $instant->micro / 1_000_000) / 86400;
    }

    public static function carbonFromJd(float $jd): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestampUTC((int) round(($jd - 2440587.5) * 86400));
    }

    /**
     * Heliocentric ecliptic coordinates in AU.
     *
     * @return array{x: float, y: float, z: float}|null
     */
    public static function heliocentricAu(OrbitalElements $elements, float $jd): ?array
    {
        $a = $elements->semiMajorAxisAu;
        $e = $elements->eccentricity;
        if ($a === null || $e === null || $a <= 0.0 || $e < 0.0 || $e >= 1.0) {
            return null;
        }
        if ($elements->inclinationDeg === null
            || $elements->longitudeAscendingNodeDeg === null
            || $elements->argumentPeriapsisDeg === null) {
            return null;
        }

        $epoch = $elements->epochJd;
        if ($epoch === null && is_numeric($elements->epoch)) {
            $epoch = (float) $elements->epoch;
        }
        $motion = $elements->meanMotionDegPerDay;
        if (($motion === null || $motion == 0.0) && $elements->orbitalPeriodDays !== null && $elements->orbitalPeriodDays > 0.0) {
            $motion = 360.0 / $elements->orbitalPeriodDays;
        }
        if ($epoch === null || $motion === null || $motion == 0.0) {
            return null;
        }

        if ($elements->meanAnomalyDeg !== null) {
            $meanDeg = $elements->meanAnomalyDeg + $motion * ($jd - $epoch);
        } elseif ($elements->perihelionTimeJd !== null) {
            $meanDeg = $motion * ($jd - $elements->perihelionTimeJd);
        } else {
            return null;
        }

        $mean = deg2rad(fmod($meanDeg, 360.0));
        if ($mean > M_PI) {
            $mean -= 2 * M_PI;
        } elseif ($mean < -M_PI) {
            $mean += 2 * M_PI;
        }

        $anomaly = self::eccentricAnomaly($mean, $e);
        $nu = 2 * atan2(sqrt(1 + $e) * sin($anomaly / 2), sqrt(1 - $e) * cos($anomaly / 2));
        $radius = $a * (1 - $e * $e) / (1 + $e * cos($nu));
        if (! is_finite($radius) || $radius <= 0.0) {
            return null;
        }

        $i = deg2rad($elements->inclinationDeg);
        $node = deg2rad($elements->longitudeAscendingNodeDeg);
        $u = deg2rad($elements->argumentPeriapsisDeg) + $nu;
        $point = [
            'x' => $radius * (cos($node) * cos($u) - sin($node) * sin($u) * cos($i)),
            'y' => $radius * (sin($node) * cos($u) + cos($node) * sin($u) * cos($i)),
            'z' => $radius * sin($u) * sin($i),
        ];

        return (is_finite($point['x']) && is_finite($point['y']) && is_finite($point['z'])) ? $point : null;
    }

    /**
     * Geocentric ecliptic samples across an encounter window, object minus Earth.
     *
     * @return list<VectorSample>
     */
    public static function geocentricArc(OrbitalElements $object, CarbonImmutable $approachAt, float $halfDays = 3.0, float $stepHours = 3.0): array
    {
        if ($stepHours <= 0.0 || $halfDays <= 0.0) {
            return [];
        }

        $earth = self::earth();
        $steps = (int) round((2 * $halfDays * 24) / $stepHours);
        $start = $approachAt->utc()->subHours((int) round($halfDays * 24));
        $samples = [];

        for ($k = 0; $k <= $steps; $k++) {
            $instant = $start->addHours((int) round($k * $stepHours));
            $jd = self::julianDay($instant);
            $objectAu = self::heliocentricAu($object, $jd);
            $earthAu = self::heliocentricAu($earth, $jd);
            if ($objectAu === null || $earthAu === null) {
                return [];
            }
            $sample = new VectorSample(
                $jd,
                ($objectAu['x'] - $earthAu['x']) * self::KM_PER_AU,
                ($objectAu['y'] - $earthAu['y']) * self::KM_PER_AU,
                ($objectAu['z'] - $earthAu['z']) * self::KM_PER_AU,
            );
            if (! $sample->finite()) {
                return [];
            }
            $samples[] = $sample;
        }

        return $samples;
    }

    private static function eccentricAnomaly(float $meanRad, float $eccentricity): float
    {
        $anomaly = $eccentricity > 0.8 ? M_PI : $meanRad;
        for ($i = 0; $i < 20; $i++) {
            $slope = 1 - $eccentricity * cos($anomaly);
            if ($slope == 0.0) {
                break;
            }
            $delta = ($anomaly - $eccentricity * sin($anomaly) - $meanRad) / $slope;
            $anomaly -= $delta;
            if (abs($delta) < 1e-12) {
                break;
            }
        }

        return $anomaly;
    }
}

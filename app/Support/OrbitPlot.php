<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\SolarApi\Data\OrbitalElements;
use App\Services\SolarApi\Data\Position;

/**
 * Geometry for the "relative position" figure on an object page: a top-down
 * view of the ecliptic plane with the Sun at the origin, +x towards the
 * vernal equinox and +y 90° ahead of it (the frame the backend's /positions
 * endpoint reports in), rendered to SVG with north up.
 *
 * Pure functions, no I/O. Distances in AU, angles in degrees at the boundary.
 */
final class OrbitPlot
{
    /** Light travel time for 1 AU, seconds (IAU 2012 AU / c). */
    private const LIGHT_SECONDS_PER_AU = 499.004784;

    /**
     * Heliocentric ecliptic x/y (AU) of a point on the orbit at a given true anomaly.
     *
     * @return array{x: float, y: float}
     */
    public static function orbitPoint(OrbitalElements $o, float $trueAnomalyDeg): array
    {
        $a = (float) $o->semiMajorAxisAu;
        $e = (float) $o->eccentricity;
        $i = deg2rad($o->inclinationDeg ?? 0.0);
        $node = deg2rad($o->longitudeAscendingNodeDeg ?? 0.0);
        $peri = deg2rad($o->argumentPeriapsisDeg ?? 0.0);
        $nu = deg2rad($trueAnomalyDeg);

        $r = $a * (1 - $e * $e) / (1 + $e * cos($nu));
        $u = $peri + $nu; // argument of latitude

        return [
            'x' => $r * (cos($node) * cos($u) - sin($node) * sin($u) * cos($i)),
            'y' => $r * (sin($node) * cos($u) + cos($node) * sin($u) * cos($i)),
        ];
    }

    /**
     * The orbit projected onto the ecliptic, sampled at $steps equal true-anomaly
     * intervals and closed (first point repeated last). Empty when the elements
     * cannot describe a closed ellipse — missing a or e, or e ≥ 1 (parabolic and
     * hyperbolic comets), for which only the current marker is drawn.
     *
     * @return list<array{x: float, y: float}>
     */
    public static function orbitPath(OrbitalElements $o, int $steps = 72): array
    {
        if ($o->semiMajorAxisAu === null || $o->eccentricity === null
            || $o->semiMajorAxisAu <= 0.0 || $o->eccentricity >= 1.0 || $steps < 3) {
            return [];
        }

        $path = [];
        for ($k = 0; $k <= $steps; $k++) {
            $path[] = self::orbitPoint($o, 360.0 * $k / $steps);
        }

        return $path;
    }

    /** Straight-line distance between two heliocentric positions, AU. Null if either lacks a vector. */
    public static function distanceAu(Position $a, Position $b): ?float
    {
        if ($a->xAu === null || $a->yAu === null || $b->xAu === null || $b->yAu === null) {
            return null;
        }

        $dz = ($a->zAu ?? 0.0) - ($b->zAu ?? 0.0);

        return sqrt(($a->xAu - $b->xAu) ** 2 + ($a->yAu - $b->yAu) ** 2 + $dz ** 2);
    }

    /**
     * Pixels per AU so that everything drawn — every body's current distance and
     * the object's whole orbit (its aphelion) — fits inside $maxRadiusPx. Linear:
     * the figure is to scale, which is the point of it.
     *
     * @param  list<Position>  $positions
     */
    public static function scale(array $positions, ?OrbitalElements $o, float $maxRadiusPx): float
    {
        $maxAu = 1.0; // Earth's orbit is always drawn

        foreach ($positions as $p) {
            if ($p->distanceFromSunAu !== null) {
                $maxAu = max($maxAu, $p->distanceFromSunAu);
            }
        }

        if ($o !== null && $o->semiMajorAxisAu !== null && $o->eccentricity !== null
            && $o->semiMajorAxisAu > 0.0 && $o->eccentricity < 1.0) {
            $maxAu = max($maxAu, $o->semiMajorAxisAu * (1 + $o->eccentricity));
        }

        return $maxRadiusPx / $maxAu;
    }

    /**
     * Ecliptic AU → SVG pixels: +x right, +y up (SVG y grows downwards, so it flips).
     *
     * @return array{cx: float, cy: float}
     */
    public static function toSvg(float $xAu, float $yAu, float $scale, float $centre): array
    {
        return ['cx' => $centre + $xAu * $scale, 'cy' => $centre - $yAu * $scale];
    }

    /** "3.6 light-minutes", "1.2 light-hours", "1.3 light-seconds". */
    public static function lightTime(float $au): string
    {
        $seconds = $au * self::LIGHT_SECONDS_PER_AU;

        if ($seconds < 60.0) {
            return number_format($seconds, 1).' light-seconds';
        }
        if ($seconds < 3600.0) {
            return number_format($seconds / 60.0, 1).' light-minutes';
        }

        return number_format($seconds / 3600.0, 1).' light-hours';
    }
}

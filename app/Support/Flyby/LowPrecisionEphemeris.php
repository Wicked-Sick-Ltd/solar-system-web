<?php

declare(strict_types=1);

namespace App\Support\Flyby;

use Carbon\CarbonImmutable;

/**
 * Low-precision geocentric Sun and Moon (Meeus, Astronomical Algorithms).
 *
 * Used when a numerical ephemeris is unavailable. Accurate to a fraction of a
 * degree for the Sun and a few thousand kilometres for the Moon — enough to
 * point the diagram, and labelled approximate by the caller.
 */
final class LowPrecisionEphemeris
{
    public static function sun(CarbonImmutable $instant): VectorSample
    {
        $jd = TwoBody::julianDay($instant->utc());
        $days = $jd - 2451545.0;
        $anomaly = 357.52911 + 0.98560028 * $days;
        $longitude = 280.459 + 0.98564736 * $days
            + 1.915 * sin(deg2rad($anomaly))
            + 0.020 * sin(deg2rad(2 * $anomaly));
        $radiusAu = 1.00014 - 0.01671 * cos(deg2rad($anomaly)) - 0.00014 * cos(deg2rad(2 * $anomaly));
        $lambda = deg2rad($longitude);

        return new VectorSample(
            $jd,
            $radiusAu * cos($lambda) * TwoBody::KM_PER_AU,
            $radiusAu * sin($lambda) * TwoBody::KM_PER_AU,
            0.0,
        );
    }

    public static function moon(CarbonImmutable $instant): VectorSample
    {
        $jd = TwoBody::julianDay($instant->utc());
        $t = ($jd - 2451545.0) / 36525.0;
        $elongation = 297.8501921 + 445267.1114034 * $t - 0.0018819 * $t ** 2;
        $sunAnomaly = 357.5291092 + 35999.0502909 * $t - 0.0001536 * $t ** 2;
        $anomaly = 134.9633964 + 477198.8675055 * $t + 0.0087414 * $t ** 2;
        $latitude = 93.2720950 + 483202.0175233 * $t - 0.0036539 * $t ** 2;
        $meanLongitude = 218.3164477 + 481267.88123421 * $t - 0.0015786 * $t ** 2;

        $longitude = $meanLongitude
            + 6.288774 * self::sin($anomaly)
            + 1.274027 * self::sin(2 * $elongation - $anomaly)
            + 0.658314 * self::sin(2 * $elongation)
            + 0.213618 * self::sin(2 * $anomaly)
            - 0.185116 * self::sin($sunAnomaly)
            - 0.114332 * self::sin(2 * $latitude)
            + 0.058793 * self::sin(2 * $elongation - 2 * $anomaly)
            + 0.057066 * self::sin(2 * $elongation - $sunAnomaly - $anomaly)
            + 0.053322 * self::sin(2 * $elongation + $anomaly)
            + 0.045758 * self::sin(2 * $elongation - $sunAnomaly)
            + 0.040923 * self::sin($anomaly - $sunAnomaly)
            - 0.034720 * self::sin($elongation)
            - 0.030383 * self::sin($sunAnomaly + $anomaly)
            + 0.015327 * self::sin(2 * $elongation - 2 * $latitude)
            - 0.012528 * self::sin($anomaly + 2 * $latitude)
            + 0.010980 * self::sin($anomaly - 2 * $latitude)
            + 0.010675 * self::sin(4 * $elongation - $anomaly);

        $beta = 5.128122 * self::sin($latitude)
            + 0.280602 * self::sin($anomaly + $latitude)
            + 0.277693 * self::sin($anomaly - $latitude)
            + 0.173237 * self::sin(2 * $elongation - $latitude)
            + 0.055413 * self::sin(2 * $elongation - $anomaly + $latitude)
            + 0.046271 * self::sin(2 * $elongation - $anomaly - $latitude)
            + 0.032573 * self::sin(2 * $elongation + $latitude)
            + 0.017198 * self::sin(2 * $anomaly + $latitude);

        $distance = 385000.56
            - 20905.355 * self::cos($anomaly)
            - 3699.111 * self::cos(2 * $elongation - $anomaly)
            - 2955.968 * self::cos(2 * $elongation)
            - 569.925 * self::cos(2 * $anomaly)
            + 246.158 * self::cos(2 * $elongation - 2 * $anomaly)
            - 204.586 * self::cos(2 * $elongation - $sunAnomaly)
            - 152.138 * self::cos(2 * $elongation - $sunAnomaly - $anomaly)
            - 129.620 * self::cos($anomaly - $sunAnomaly)
            - 109.667 * self::cos($elongation)
            - 105.028 * self::cos($sunAnomaly + $anomaly);

        $lon = deg2rad($longitude);
        $lat = deg2rad($beta);

        return new VectorSample(
            $jd,
            $distance * cos($lat) * cos($lon),
            $distance * cos($lat) * sin($lon),
            $distance * sin($lat),
        );
    }

    private static function sin(float $degrees): float
    {
        return sin(deg2rad($degrees));
    }

    private static function cos(float $degrees): float
    {
        return cos(deg2rad($degrees));
    }
}

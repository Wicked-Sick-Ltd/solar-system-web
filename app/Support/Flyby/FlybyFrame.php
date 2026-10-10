<?php

declare(strict_types=1);

namespace App\Support\Flyby;

use App\Services\SolarApi\Data\CloseApproach;
use App\Services\SolarApi\Data\OrbitalElements;
use App\Services\SolarApi\Data\Position;
use App\Support\Format;
use App\Support\OrbitPlot;
use Carbon\CarbonImmutable;

/**
 * Screen geometry for one Earth close approach: a top-down ecliptic view
 * with Earth at the centre. Positions stay in kilometres until this class
 * projects them; +x is the vernal equinox and +y is north, drawn upward.
 */
final readonly class FlybyFrame
{
    public const float WIDTH = 700.0;

    public const float HEIGHT = 560.0;

    /**
     * @param  list<array{x: float, y: float}>  $path
     * @param  list<array{x: float, y: float, label: string}>  $ticks
     * @param  array{x: float, y: float}|null  $moon
     * @param  array{x1: float, y1: float, x2: float, y2: float, labelX: float, labelY: float}  $sun
     * @param  array{points: string}|null  $arrow
     * @param  array{x: float, y: float, size: float, earthX: float, earthY: float, objectX: float, objectY: float, earthPath: string, objectPath: string}|null  $inset
     */
    public function __construct(
        public string $title,
        public string $summary,
        public bool $trajectoryApproximate,
        public bool $moonApproximate,
        public float $earthX,
        public float $earthY,
        public float $earthRadius,
        public float $moonOrbitRadius,
        public array $path,
        public string $pathPoints,
        public float $closestX,
        public float $closestY,
        public string $distanceLabel,
        public string $timeLabel,
        public float $labelX,
        public float $labelY,
        public string $labelAnchor,
        public array $ticks,
        public ?array $moon,
        public array $sun,
        public ?array $arrow,
        public ?array $inset,
        public ?string $planeNote,
        public float $width,
        public float $height,
        public bool $compact,
    ) {}

    /**
     * @param  list<VectorSample>  $samples
     */
    public static function compose(
        string $objectName,
        CarbonImmutable $approachAt,
        float $distanceAu,
        array $samples,
        ?VectorSample $moon,
        ?VectorSample $sun,
        bool $trajectoryApproximate,
        bool $moonApproximate,
        ?OrbitalElements $objectElements,
        bool $compact = false,
    ): ?self {
        $samples = array_values(array_filter($samples, fn (VectorSample $sample): bool => $sample->finite()));
        if (count($samples) < 2 || ! is_finite($distanceAu) || $distanceAu < 0.0) {
            return null;
        }
        usort($samples, fn (VectorSample $a, VectorSample $b): int => $a->jd <=> $b->jd);

        $approachAt = $approachAt->utc();
        $closest = self::interpolate($samples, TwoBody::julianDay($approachAt));
        if ($closest === null) {
            return null;
        }

        $earthX = $compact ? 188.0 : 270.0;
        $earthY = $compact ? 168.0 : 292.0;
        $plotRadius = $compact ? 132.0 : 230.0;
        $width = $compact ? 400.0 : self::WIDTH;
        $height = $compact ? 320.0 : self::HEIGHT;
        $reachKm = TwoBody::MOON_ORBIT_KM;
        foreach ($samples as $sample) {
            $reachKm = max($reachKm, hypot($sample->xKm, $sample->yKm));
        }
        if ($moon !== null) {
            $reachKm = max($reachKm, hypot($moon->xKm, $moon->yKm));
        }
        $scale = $plotRadius / ($reachKm * 1.12);

        $project = fn (float $xKm, float $yKm): array => [
            'x' => round($earthX + $xKm * $scale, 1),
            'y' => round($earthY - $yKm * $scale, 1),
        ];

        $path = [];
        foreach ($samples as $sample) {
            $path[] = $project($sample->xKm, $sample->yKm);
        }
        $closestPoint = $project($closest->xKm, $closest->yKm);
        $moonPoint = $moon !== null ? $project($moon->xKm, $moon->yKm) : null;

        $distanceKm = $distanceAu * TwoBody::KM_PER_AU;
        $lunar = $distanceAu / CloseApproach::AU_PER_LUNAR_DISTANCE;
        $distanceLabel = (number_format($lunar, $lunar < 10 ? 2 : 1)).' LD · '.(Format::km($distanceKm) ?? '');
        $timeLabel = $approachAt->format('Y-m-d H:i').' UTC';

        $label = self::labelPosition($closestPoint, $path, $width, $height, $compact);
        $ticks = self::ticks($samples, $approachAt, $project, $closestPoint);
        $arrow = self::arrow($samples, $closest, $project, $scale);
        $sunRay = self::sunRay($sun ?? LowPrecisionEphemeris::sun($approachAt), $earthX, $earthY, $plotRadius, $width - 24.0);
        $inset = $compact ? null : self::inset($objectElements, $approachAt);
        $planeNote = self::planeNote($closest);

        $summary = self::summary(
            $objectName,
            $timeLabel,
            $distanceLabel,
            $samples,
            $trajectoryApproximate,
            $moonApproximate,
            $planeNote,
            $inset !== null,
            $compact,
        );

        return new self(
            title: __('Ecliptic flyby of :name at :time', ['name' => $objectName, 'time' => $timeLabel]),
            summary: $summary,
            trajectoryApproximate: $trajectoryApproximate,
            moonApproximate: $moonApproximate,
            earthX: $earthX,
            earthY: $earthY,
            earthRadius: max(3.5, TwoBody::EARTH_RADIUS_KM * $scale),
            moonOrbitRadius: round(TwoBody::MOON_ORBIT_KM * $scale, 1),
            path: $path,
            pathPoints: self::points($path),
            closestX: $closestPoint['x'],
            closestY: $closestPoint['y'],
            distanceLabel: $distanceLabel,
            timeLabel: $timeLabel,
            labelX: $label['x'],
            labelY: $label['y'],
            labelAnchor: $label['anchor'],
            ticks: $ticks,
            moon: $moonPoint,
            sun: $sunRay,
            arrow: $arrow,
            inset: $inset,
            planeNote: $planeNote,
            width: $width,
            height: $height,
            compact: $compact,
        );
    }

    /**
     * How far the middle of a trajectory strays from the straight chord
     * between its endpoints, in kilometres in the ecliptic plane.
     *
     * @param  list<VectorSample>  $samples
     */
    public static function chordDeviationKm(array $samples): float
    {
        if (count($samples) < 3) {
            return 0.0;
        }
        $start = $samples[0];
        $end = $samples[array_key_last($samples)];
        $dx = $end->xKm - $start->xKm;
        $dy = $end->yKm - $start->yKm;
        $length = hypot($dx, $dy);
        if ($length < 1.0) {
            return 0.0;
        }

        $deviation = 0.0;
        foreach (array_slice($samples, 1, -1) as $sample) {
            $distance = abs($dx * ($start->yKm - $sample->yKm) - $dy * ($start->xKm - $sample->xKm)) / $length;
            $deviation = max($deviation, $distance);
        }

        return $deviation;
    }

    /**
     * @param  list<VectorSample>  $samples
     */
    public static function interpolate(array $samples, float $jd): ?VectorSample
    {
        if ($samples === []) {
            return null;
        }
        if ($jd <= $samples[0]->jd) {
            return $samples[0];
        }
        $last = $samples[array_key_last($samples)];
        if ($jd >= $last->jd) {
            return $last;
        }
        for ($i = 1, $count = count($samples); $i < $count; $i++) {
            $after = $samples[$i];
            if ($jd > $after->jd) {
                continue;
            }
            $before = $samples[$i - 1];
            $span = $after->jd - $before->jd;
            $fraction = $span == 0.0 ? 0.0 : ($jd - $before->jd) / $span;

            return new VectorSample(
                $jd,
                $before->xKm + ($after->xKm - $before->xKm) * $fraction,
                $before->yKm + ($after->yKm - $before->yKm) * $fraction,
                $before->zKm + ($after->zKm - $before->zKm) * $fraction,
                self::blend($before->vxKmS, $after->vxKmS, $fraction),
                self::blend($before->vyKmS, $after->vyKmS, $fraction),
                self::blend($before->vzKmS, $after->vzKmS, $fraction),
            );
        }

        return $last;
    }

    /**
     * @param  list<array{x: float, y: float}>  $points
     */
    private static function points(array $points): string
    {
        return implode(' ', array_map(
            fn (array $point): string => $point['x'].','.$point['y'],
            $points,
        ));
    }

    /**
     * Park the miss-distance label in the corner furthest from the trajectory
     * so it does not sit on the time ticks. The inset occupies the top-right
     * of the full figure.
     *
     * @param  array{x: float, y: float}  $closest
     * @param  list<array{x: float, y: float}>  $path
     * @return array{x: float, y: float, anchor: string}
     */
    private static function labelPosition(array $closest, array $path, float $width, float $height, bool $compact): array
    {
        $candidates = [
            ['x' => 20.0, 'y' => $compact ? 52.0 : 56.0, 'anchor' => 'start'],
            ['x' => $width - 20.0, 'y' => $height - 40.0, 'anchor' => 'end'],
            ['x' => 20.0, 'y' => $height - 40.0, 'anchor' => 'start'],
        ];
        if ($compact) {
            $candidates[] = ['x' => $width - 20.0, 'y' => 52.0, 'anchor' => 'end'];
        }

        $best = $candidates[0];
        $bestScore = -1.0;
        foreach ($candidates as $candidate) {
            $score = INF;
            foreach ($path as $point) {
                $score = min($score, hypot($candidate['x'] - $point['x'], $candidate['y'] - $point['y']));
            }
            $score = min($score, hypot($candidate['x'] - $closest['x'], $candidate['y'] - $closest['y']));
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }

        return $best;
    }

    /**
     * @param  list<VectorSample>  $samples
     * @param  callable(float, float): array{x: float, y: float}  $project
     * @param  array{x: float, y: float}  $closestPoint
     * @return list<array{x: float, y: float, label: string}>
     */
    private static function ticks(array $samples, CarbonImmutable $approachAt, callable $project, array $closestPoint): array
    {
        $first = TwoBody::carbonFromJd($samples[0]->jd)->utc()->startOfDay();
        $last = TwoBody::carbonFromJd($samples[array_key_last($samples)]->jd)->utc();
        if ($first->lt(TwoBody::carbonFromJd($samples[0]->jd))) {
            $first = $first->addDay();
        }

        $ticks = [];
        for ($day = $first; $day->lte($last); $day = $day->addDay()) {
            $sample = self::interpolate($samples, TwoBody::julianDay($day));
            if ($sample === null) {
                continue;
            }
            $point = $project($sample->xKm, $sample->yKm);
            if (hypot($point['x'] - $closestPoint['x'], $point['y'] - $closestPoint['y']) < 72.0) {
                continue;
            }
            $crowded = false;
            foreach ($ticks as $kept) {
                if (hypot($point['x'] - $kept['x'], $point['y'] - $kept['y']) < 64.0) {
                    $crowded = true;
                    break;
                }
            }
            if ($crowded) {
                continue;
            }
            $ticks[] = ['x' => $point['x'], 'y' => $point['y'], 'label' => $day->format('j M')];
        }

        return $ticks;
    }

    /**
     * @param  list<VectorSample>  $samples
     * @param  callable(float, float): array{x: float, y: float}  $project
     * @return array{points: string}|null
     */
    private static function arrow(array $samples, VectorSample $closest, callable $project, float $scale): ?array
    {
        $index = 0;
        $nearest = INF;
        foreach ($samples as $i => $sample) {
            $gap = abs($sample->jd - $closest->jd);
            if ($gap < $nearest) {
                $nearest = $gap;
                $index = $i;
            }
        }
        $ahead = min(count($samples) - 1, $index + max(1, (int) floor(count($samples) * 0.18)));
        if ($ahead === $index && $index > 0) {
            $ahead = $index;
            $index--;
        }
        $from = $samples[$index];
        $to = $samples[$ahead];
        $dx = $to->xKm - $from->xKm;
        $dy = $to->yKm - $from->yKm;
        if ($from->vxKmS !== null && $from->vyKmS !== null && hypot($from->vxKmS, $from->vyKmS) > 0.0 && $ahead - $index <= 1) {
            $dx = $from->vxKmS;
            $dy = $from->vyKmS;
        }
        $length = hypot($dx, $dy);
        if ($length < 1e-6 || $scale <= 0.0) {
            return null;
        }
        $ux = $dx / $length;
        $uy = $dy / $length;
        $back = 16.0 / $scale;
        $wing = 7.0 / $scale;
        $tip = $project($to->xKm, $to->yKm);
        $base = $project($to->xKm - $ux * $back, $to->yKm - $uy * $back);
        $left = $project($to->xKm - $ux * $back - $uy * $wing, $to->yKm - $uy * $back + $ux * $wing);
        $right = $project($to->xKm - $ux * $back + $uy * $wing, $to->yKm - $uy * $back - $ux * $wing);

        return ['points' => $tip['x'].','.$tip['y'].' '.$left['x'].','.$left['y'].' '.$right['x'].','.$right['y']];
    }

    /**
     * @return array{x1: float, y1: float, x2: float, y2: float, labelX: float, labelY: float}
     */
    private static function sunRay(VectorSample $sun, float $earthX, float $earthY, float $plotRadius, float $maxX): array
    {
        $angle = atan2($sun->yKm, $sun->xKm);
        $x1 = $earthX + cos($angle) * $plotRadius * 0.62;
        $y1 = $earthY - sin($angle) * $plotRadius * 0.62;
        $x2 = $earthX + cos($angle) * $plotRadius * 0.88;
        $y2 = $earthY - sin($angle) * $plotRadius * 0.88;
        $labelX = min($maxX, max(24.0, $x2 + cos($angle) * 14));
        $labelY = min($plotRadius + $earthY, max(24.0, $y2 - sin($angle) * 14));

        return [
            'x1' => round($x1, 1),
            'y1' => round($y1, 1),
            'x2' => round($x2, 1),
            'y2' => round($y2, 1),
            'labelX' => round($labelX, 1),
            'labelY' => round($labelY, 1),
        ];
    }

    /**
     * @return array{x: float, y: float, size: float, earthX: float, earthY: float, objectX: float, objectY: float, earthPath: string, objectPath: string}|null
     */
    private static function inset(?OrbitalElements $object, CarbonImmutable $approachAt): ?array
    {
        if ($object === null) {
            return null;
        }
        $jd = TwoBody::julianDay($approachAt);
        $objectAu = TwoBody::heliocentricAu($object, $jd);
        $earthAu = TwoBody::heliocentricAu(TwoBody::earth(), $jd);
        if ($objectAu === null || $earthAu === null) {
            return null;
        }

        $objectPosition = Position::fromArray([
            'x_au' => $objectAu['x'], 'y_au' => $objectAu['y'], 'z_au' => $objectAu['z'],
            'distance_from_sun_au' => sqrt($objectAu['x'] ** 2 + $objectAu['y'] ** 2 + $objectAu['z'] ** 2),
        ]);
        $earthPosition = Position::fromArray([
            'x_au' => $earthAu['x'], 'y_au' => $earthAu['y'], 'z_au' => $earthAu['z'],
            'distance_from_sun_au' => sqrt($earthAu['x'] ** 2 + $earthAu['y'] ** 2 + $earthAu['z'] ** 2),
        ]);
        $box = 150.0;
        $origin = 75.0;
        $scale = OrbitPlot::scale([$objectPosition, $earthPosition], $object, 62.0);
        $map = function (float $xAu, float $yAu) use ($scale, $origin): array {
            $point = OrbitPlot::toSvg($xAu, $yAu, $scale, $origin);

            return ['x' => round($point['cx'], 1), 'y' => round($point['cy'], 1)];
        };
        $polyline = function (array $orbit) use ($map): string {
            return implode(' ', array_map(
                fn (array $point): string => implode(',', $map($point['x'], $point['y'])),
                $orbit,
            ));
        };

        $earth = $map($earthAu['x'], $earthAu['y']);
        $body = $map($objectAu['x'], $objectAu['y']);

        return [
            'x' => 528.0,
            'y' => 24.0,
            'size' => $box,
            'earthX' => $earth['x'],
            'earthY' => $earth['y'],
            'objectX' => $body['x'],
            'objectY' => $body['y'],
            'earthPath' => $polyline(OrbitPlot::orbitPath(TwoBody::earth(), 48)),
            'objectPath' => $polyline(OrbitPlot::orbitPath($object, 48)),
        ];
    }

    private static function planeNote(VectorSample $closest): ?string
    {
        $distance = $closest->distanceKm();
        if ($distance < 1.0 || abs($closest->zKm) / $distance < 0.25) {
            return null;
        }

        return $closest->zKm > 0.0
            ? __('At closest approach the object is north of the ecliptic.')
            : __('At closest approach the object is south of the ecliptic.');
    }

    /**
     * @param  list<VectorSample>  $samples
     */
    private static function summary(
        string $objectName,
        string $timeLabel,
        string $distanceLabel,
        array $samples,
        bool $trajectoryApproximate,
        bool $moonApproximate,
        ?string $planeNote,
        bool $hasInset,
        bool $compact,
    ): string {
        $start = TwoBody::carbonFromJd($samples[0]->jd)->utc()->format('Y-m-d');
        $end = TwoBody::carbonFromJd($samples[array_key_last($samples)]->jd)->utc()->format('Y-m-d');
        $parts = [
            __('Top-down view of the ecliptic, Earth at the centre, for the :time close approach of :name.', [
                'time' => $timeLabel,
                'name' => $objectName,
            ]),
            __('Catalogue miss distance :distance.', ['distance' => $distanceLabel]),
            __('The curve is the geocentric path from :start to :end, with a mark at UTC midnights and an arrow for the direction of motion. The circle is the Moon’s mean orbit; the Moon is plotted at closest approach. A ray shows the Sun’s direction.', [
                'start' => $start,
                'end' => $end,
            ]),
            __('The labelled distance is the full separation. The drawn radius is the part that lies in the ecliptic plane.'),
        ];
        if ($compact) {
            $parts[2] = __('The curve is the geocentric path from :start to :end. The circle is the Moon’s mean orbit and a ray shows the Sun’s direction.', [
                'start' => $start,
                'end' => $end,
            ]);
        }
        if ($planeNote !== null) {
            $parts[] = $planeNote;
        }
        $parts[] = $trajectoryApproximate
            ? __('The path is approximate: a two-body Kepler propagation of the catalogue orbital elements, not a numerical integration.')
            : __('The path is a geocentric ecliptic ephemeris from JPL Horizons.');
        if ($moonApproximate) {
            $parts[] = __('The Moon’s position is approximate.');
        }
        if ($hasInset) {
            $parts[] = __('The inset is a heliocentric view and is approximate.');
        }

        return implode(' ', $parts);
    }

    private static function blend(?float $before, ?float $after, float $fraction): ?float
    {
        if ($before === null || $after === null) {
            return $before ?? $after;
        }

        return $before + ($after - $before) * $fraction;
    }
}

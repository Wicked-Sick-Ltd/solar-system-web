<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\SolarApi\Data\ObjectDetail;

/**
 * Short, shareable takeaways for one object, derived only from its catalogue
 * measurements: a single fun fact and a few key stats. Used by the object of
 * the day page and its share card, so both always say the same thing.
 */
final class ObjectHighlights
{
    private const EARTH_DIAMETER_KM = 12742.0;

    private const EARTH_GRAVITY_MS2 = 9.80665;

    private const KM_PER_AU = 149597870.7;

    /** Light travel time for 1 AU, seconds (IAU 2012 AU / c). */
    private const LIGHT_SECONDS_PER_AU = 499.004784;

    /**
     * The most surprising fact the measurements support, or null when the
     * record is too sparse. Moons' orbital elements are planetocentric, so
     * Sun-relative facts are only drawn for bodies that orbit the Sun.
     */
    public static function funFact(ObjectDetail $object): ?string
    {
        $fact = self::pickFact($object);

        return $fact === null ? null : mb_strtoupper(mb_substr($fact, 0, 1)).mb_substr($fact, 1);
    }

    private static function pickFact(ObjectDetail $object): ?string
    {
        $name = $object->name === 'Moon' ? __('the Moon') : $object->name;
        $phys = $object->physical;
        $year = $object->orbital?->orbitalPeriodDays;
        $isMoon = self::isMoon($object);
        $rotationDays = $phys?->rotationPeriodHours !== null ? abs($phys->rotationPeriodHours) / 24 : null;
        $solarDayDays = $phys?->lengthOfDayHours !== null ? $phys->lengthOfDayHours / 24 : null;

        if (! $isMoon && $year !== null && $year > 0) {
            if ($solarDayDays !== null && $solarDayDays > $year) {
                return __('Sunrise to sunrise, a day on :name lasts :day Earth days — longer than its :year-day year.', [
                    'name' => $name, 'day' => self::round($solarDayDays), 'year' => self::round($year),
                ]);
            }
            if ($rotationDays !== null && $rotationDays > $year) {
                return __(':name takes :day Earth days to spin once — longer than its :year-day year.', [
                    'name' => $name, 'day' => self::round($rotationDays), 'year' => self::round($year),
                ]);
            }
        }

        $tilt = $phys?->axialTiltDeg;
        if (! $isMoon && $tilt !== null && $tilt >= 60 && $tilt <= 110) {
            return __(':name is tipped :tilt° on its side, so it rolls around the Sun like a ball.', [
                'name' => $name, 'tilt' => self::round($tilt),
            ]);
        }

        if ($phys?->densityGCm3 !== null && $phys->densityGCm3 > 0 && $phys->densityGCm3 < 1) {
            return __(':name is less dense than water — :density g/cm³ on average.', [
                'name' => $name, 'density' => number_format($phys->densityGCm3, 2),
            ]);
        }

        $diameter = $phys?->diameterKm();
        if ($diameter !== null && $diameter / self::EARTH_DIAMETER_KM >= 3) {
            return __('About :count Earths would fit side by side across :name.', [
                'name' => $name, 'count' => (int) round($diameter / self::EARTH_DIAMETER_KM),
            ]);
        }

        if ($rotationDays !== null && $rotationDays > 0 && $rotationDays * 24 < 6) {
            return __('A day on :name lasts just :hours hours.', [
                'name' => $name, 'hours' => number_format($rotationDays * 24, 1),
            ]);
        }

        if ($isMoon && $rotationDays !== null && $year !== null && $year > 0 && abs($rotationDays - $year) / $year < 0.02) {
            return __(':name is tidally locked: it spins once per orbit (:period), so the same face always points at its planet.', [
                'name' => $name, 'period' => Format::periodDays($year),
            ]);
        }

        if (! $isMoon && $year !== null && $year / 365.25 > 50) {
            return __(':name takes :years years to travel once around the Sun.', [
                'name' => $name, 'years' => self::round($year / 365.25),
            ]);
        }

        $gravity = $phys?->surfaceGravityMS2;
        if ($gravity !== null && $gravity > 0) {
            $ratio = $gravity / self::EARTH_GRAVITY_MS2;
            if ($ratio > 1.25) {
                return __('You would weigh :ratio times as much on :name as you do on Earth.', [
                    'name' => $name, 'ratio' => number_format($ratio, 1),
                ]);
            }
            if ($ratio < 0.8) {
                return __('You would weigh just :percent% of your Earth weight on :name.', [
                    'name' => $name, 'percent' => $ratio < 0.1 ? number_format($ratio * 100, 1) : (string) round($ratio * 100),
                ]);
            }
        }

        $au = $object->orbital?->semiMajorAxisAu;
        if (! $isMoon && $au !== null && $au > 0) {
            return __('Sunlight takes :time to reach :name.', [
                'name' => $name, 'time' => self::duration($au * self::LIGHT_SECONDS_PER_AU),
            ]);
        }

        if ($isMoon && $year !== null && $year > 0) {
            return __(':name circles its planet once every :period.', [
                'name' => $name, 'period' => Format::periodDays($year),
            ]);
        }

        return null;
    }

    /** @return list<array{label: string, value: string}> */
    public static function keyStats(ObjectDetail $object): array
    {
        $stats = [];

        if ($diameter = Format::km($object->physical?->diameterKm())) {
            $stats[] = ['label' => __('Diameter'), 'value' => $diameter];
        }

        $au = $object->orbital?->semiMajorAxisAu;
        if ($au !== null && $au > 0) {
            $stats[] = self::isMoon($object)
                ? ['label' => __('From its planet'), 'value' => number_format($au * self::KM_PER_AU).' km']
                : ['label' => __('From the Sun'), 'value' => Format::au($au, 2)];
        }

        if ($period = Format::periodDays($object->orbital?->orbitalPeriodDays)) {
            $stats[] = ['label' => __('Orbital period'), 'value' => $period];
        }

        return $stats;
    }

    private static function isMoon(ObjectDetail $object): bool
    {
        return $object->objectType === 'moon';
    }

    private static function round(float $value): string
    {
        return $value >= 100 ? number_format($value) : rtrim(rtrim(number_format($value, 1), '0'), '.');
    }

    private static function duration(float $seconds): string
    {
        $seconds = (int) round($seconds);
        if ($seconds < 3600) {
            return __(':m min :s s', ['m' => intdiv($seconds, 60), 's' => $seconds % 60]);
        }

        $minutes = (int) round($seconds / 60);

        return __(':h h :m min', ['h' => intdiv($minutes, 60), 'm' => $minutes % 60]);
    }
}

<?php

declare(strict_types=1);

namespace App\Support\Sky;

use App\Services\SolarApi\Data\MeteorParameterSet;
use App\Services\SolarApi\Data\MeteorShower;
use App\Support\Flyby\LowPrecisionEphemeris;
use App\Support\Format;
use App\Support\MeteorShowerPage;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * "When and where to see it from the UK", derived from the catalogue record.
 *
 * Inputs come from the shower's own IAU Meteor Data Center parameter set (the
 * one MeteorShowerPage already treats as the reported peak): the peak solar
 * longitude (J2000) and the radiant right ascension and declination. Nothing
 * here is a forecast of meteor rates.
 *
 * - Next peak: the next instant the Sun reaches that solar longitude, using
 *   the low-precision solar formula (Astronomical Almanac / Meeus), corrected
 *   from the equinox of date to J2000. Good to well under an hour.
 * - Darkness: Sun more than 12 degrees below the horizon (nautical twilight),
 *   at a fixed reference point in central London.
 * - Best window: dark, and the radiant at least 30 degrees up. Seen rates
 *   scale roughly with the sine of the radiant altitude, so 30 degrees is half
 *   the rate of a radiant overhead.
 * - Moon: illuminated fraction at the peak and whether it is up during the
 *   best window, from LowPrecisionEphemeris.
 *
 * Positions ignore precession of the radiant, refraction and parallax, so
 * altitudes and times are approximate (about a degree, a few minutes).
 */
final class UkMeteorViewing
{
    public const float LATITUDE = 51.5074;

    public const float LONGITUDE = -0.1278;

    public const string PLACE = 'London';

    public const string TIMEZONE = 'Europe/London';

    public const float DARK_SUN_ALTITUDE = -12.0;

    public const float MIN_RADIANT_ALTITUDE = 30.0;

    private const int STEP_SECONDS = 300;

    /**
     * @return array{
     *     peak_utc: DateTimeImmutable,
     *     peak_local: DateTimeImmutable,
     *     night_start_local: DateTimeImmutable,
     *     night_end_local: DateTimeImmutable,
     *     dark_start_local: DateTimeImmutable,
     *     dark_end_local: DateTimeImmutable,
     *     peak_in_dark: bool,
     *     best_start_local: ?DateTimeImmutable,
     *     best_end_local: ?DateTimeImmutable,
     *     highest_local: DateTimeImmutable,
     *     highest_altitude_deg: float,
     *     highest_azimuth_deg: float,
     *     never_rises: bool,
     *     moon_illumination: float,
     *     moon_waxing: bool,
     *     moon_up_fraction: float,
     *     solar_longitude_deg: float,
     *     ra_deg: float,
     *     dec_deg: float,
     *     ad_no: int,
     * }|null
     */
    public static function forShower(MeteorShower $shower, ?DateTimeInterface $now = null): ?array
    {
        $set = self::peakSet($shower);
        if ($set === null) {
            return null;
        }
        $longitude = $set->measurements['solar_longitude_deg'];
        $ra = $set->measurements['ra_deg'];
        $dec = $set->measurements['dec_deg'];
        if ($longitude === null || $ra === null || $dec === null) {
            return null;
        }

        $now = DateTimeImmutable::createFromInterface($now ?? new DateTimeImmutable)->setTimezone(new DateTimeZone('UTC'));
        $peak = self::nextSolarLongitude($longitude, $now->modify('-1 day'));

        $window = self::window($peak, $longitude, $ra, $dec);

        return $window === null ? null : ['ad_no' => $set->adNo] + $window;
    }

    /** The annual, best-supported parameter set: the same one the page title reports. */
    public static function peakSet(MeteorShower $shower): ?MeteorParameterSet
    {
        $longitude = MeteorShowerPage::peakSolarLongitude($shower);
        if ($longitude === null) {
            return null;
        }
        $candidates = array_values(array_filter(
            $shower->parameterSets,
            static fn (MeteorParameterSet $set): bool => $set->measurements['solar_longitude_deg'] === $longitude
                && $set->measurements['ra_deg'] !== null && $set->measurements['dec_deg'] !== null,
        ));
        if ($candidates === []) {
            return null;
        }
        usort($candidates, static fn (MeteorParameterSet $a, MeteorParameterSet $b): int => ($b->members ?? -1) <=> ($a->members ?? -1));

        return $candidates[0];
    }

    /** First instant at or after $from when the Sun's J2000 ecliptic longitude equals $longitude. */
    public static function nextSolarLongitude(float $longitude, DateTimeInterface $from): DateTimeImmutable
    {
        $jd = self::julianDay($from);
        $ahead = fmod(fmod($longitude - self::sunLongitudeJ2000($jd), 360.0) + 360.0, 360.0);
        $jd += $ahead / 0.98564736;
        for ($i = 0; $i < 4; $i++) {
            $delta = fmod(fmod($longitude - self::sunLongitudeJ2000($jd) + 540.0, 360.0), 360.0) - 180.0;
            $jd += $delta / 0.98564736;
        }

        return self::fromJulianDay($jd);
    }

    /** Apparent solar longitude of date, referred to the J2000 equinox (degrees). */
    public static function sunLongitudeJ2000(float $jd): float
    {
        $days = $jd - 2451545.0;
        $anomaly = deg2rad(357.52911 + 0.98560028 * $days);
        $ofDate = 280.459 + 0.98564736 * $days + 1.915 * sin($anomaly) + 0.020 * sin(2 * $anomaly);
        // General precession in longitude, 1.3969713 degrees per Julian century.
        $j2000 = $ofDate - 1.3969713 * ($days / 36525.0);

        return fmod(fmod($j2000, 360.0) + 360.0, 360.0);
    }

    /** @return array{0: float, 1: float} altitude and azimuth (degrees, azimuth from north through east) */
    public static function altAz(float $raDeg, float $decDeg, float $jd, float $lat = self::LATITUDE, float $lon = self::LONGITUDE): array
    {
        $gmst = 280.46061837 + 360.98564736629 * ($jd - 2451545.0);
        $hour = deg2rad(fmod(fmod($gmst + $lon - $raDeg, 360.0) + 360.0, 360.0));
        $phi = deg2rad($lat);
        $delta = deg2rad($decDeg);
        $sinAlt = sin($phi) * sin($delta) + cos($phi) * cos($delta) * cos($hour);
        $alt = asin(max(-1.0, min(1.0, $sinAlt)));
        $az = atan2(-sin($hour) * cos($delta), cos($phi) * sin($delta) - sin($phi) * cos($delta) * cos($hour));

        return [rad2deg($alt), fmod(rad2deg($az) + 360.0, 360.0)];
    }

    /** @return array{0: float, 1: float} right ascension and declination of the Sun (degrees, of date) */
    public static function sunRaDec(float $jd): array
    {
        $v = LowPrecisionEphemeris::sun(self::carbon($jd));

        return self::equatorial($v->xKm, $v->yKm, $v->zKm, $jd);
    }

    /** @return array{0: float, 1: float} right ascension and declination of the Moon (degrees, of date, geocentric) */
    public static function moonRaDec(float $jd): array
    {
        $v = LowPrecisionEphemeris::moon(self::carbon($jd));

        return self::equatorial($v->xKm, $v->yKm, $v->zKm, $jd);
    }

    /** @return array{0: float, 1: bool} illuminated fraction (0 to 1) and whether the Moon is waxing */
    public static function moonPhase(float $jd): array
    {
        $sun = LowPrecisionEphemeris::sun(self::carbon($jd));
        $moon = LowPrecisionEphemeris::moon(self::carbon($jd));
        $sunLon = atan2($sun->yKm, $sun->xKm);
        $moonLon = atan2($moon->yKm, $moon->xKm);
        $cosElongation = ($sun->xKm * $moon->xKm + $sun->yKm * $moon->yKm + $sun->zKm * $moon->zKm)
            / (sqrt($sun->xKm ** 2 + $sun->yKm ** 2 + $sun->zKm ** 2) * sqrt($moon->xKm ** 2 + $moon->yKm ** 2 + $moon->zKm ** 2));
        $elongation = acos(max(-1.0, min(1.0, $cosElongation)));
        // Phase angle is close to 180 degrees minus the elongation for the Moon.
        $fraction = (1 - cos($elongation)) / 2;
        $ahead = fmod(fmod(rad2deg($moonLon - $sunLon), 360.0) + 360.0, 360.0);

        return [$fraction, $ahead < 180.0];
    }

    /** "13–14 December 2026", or "31 December 2026 – 1 January 2027" across a month or year. */
    public static function nightLabel(DateTimeInterface $evening, DateTimeInterface $morning): string
    {
        if ($evening->format('Y-m') === $morning->format('Y-m')) {
            return $evening->format('j').'–'.$morning->format('j F Y');
        }
        if ($evening->format('Y') === $morning->format('Y')) {
            return $evening->format('j F').' – '.$morning->format('j F Y');
        }

        return $evening->format('j F Y').' – '.$morning->format('j F Y');
    }

    /** UK-English compass words for an azimuth, from the 16-point label. */
    public static function direction(float $azimuthDeg): string
    {
        $words = ['N' => 'north', 'E' => 'east', 'S' => 'south', 'W' => 'west'];
        $label = Format::bearing($azimuthDeg);

        return implode('-', array_map(static fn (string $point): string => $words[$point], str_split($label)));
    }

    /** "waxing crescent", "full", and so on, from the illuminated fraction. */
    public static function moonPhaseName(float $illumination, bool $waxing): string
    {
        return match (true) {
            $illumination < 0.03 => __('new'),
            $illumination > 0.97 => __('full'),
            abs($illumination - 0.5) <= 0.03 => $waxing ? __('first quarter') : __('last quarter'),
            $illumination < 0.5 => $waxing ? __('waxing crescent') : __('waning crescent'),
            default => $waxing ? __('waxing gibbous') : __('waning gibbous'),
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function window(DateTimeImmutable $peak, float $longitude, float $ra, float $dec): ?array
    {
        $tz = new DateTimeZone(self::TIMEZONE);
        $peakJd = self::julianDay($peak);

        // Sample 30 hours either side of the peak, then keep the dark spell closest to it.
        $samples = [];
        for ($s = -30 * 3600; $s <= 30 * 3600; $s += self::STEP_SECONDS) {
            $jd = $peakJd + $s / 86400;
            [$sunAlt] = self::altAz(...[...self::sunRaDec($jd), $jd]);
            [$alt, $az] = self::altAz($ra, $dec, $jd);
            $samples[] = ['jd' => $jd, 'sun' => $sunAlt, 'alt' => $alt, 'az' => $az];
        }
        $spells = [];
        $current = null;
        foreach ($samples as $sample) {
            if ($sample['sun'] <= self::DARK_SUN_ALTITUDE) {
                $current[] = $sample;
            } elseif ($current !== null) {
                $spells[] = $current;
                $current = null;
            }
        }
        if ($current !== null) {
            $spells[] = $current;
        }
        if ($spells === []) {
            // No darkness within 30 hours (not possible from London, kept for other reference points).
            return null;
        }
        usort($spells, static function (array $a, array $b) use ($peakJd): int {
            return self::distance($a, $peakJd) <=> self::distance($b, $peakJd);
        });
        $dark = $spells[0];
        $first = $dark[0];
        $last = $dark[array_key_last($dark)];

        $best = array_values(array_filter($dark, static fn (array $s): bool => $s['alt'] >= self::MIN_RADIANT_ALTITUDE));
        $highest = $dark[0];
        foreach ($dark as $sample) {
            if ($sample['alt'] > $highest['alt']) {
                $highest = $sample;
            }
        }

        $moonPool = $best !== [] ? $best : $dark;
        $moonUp = 0;
        foreach ($moonPool as $sample) {
            [$moonAlt] = self::altAz(...[...self::moonRaDec($sample['jd']), $sample['jd']]);
            if ($moonAlt > 0) {
                $moonUp++;
            }
        }
        [$illumination, $waxing] = self::moonPhase($peakJd);

        $local = static fn (float $jd): DateTimeImmutable => self::fromJulianDay($jd)->setTimezone($tz);
        $nightStart = $local($first['jd']);
        $evening = (int) $nightStart->format('G') >= 12 ? $nightStart->setTime(0, 0) : $nightStart->modify('-1 day')->setTime(0, 0);

        return [
            'peak_utc' => $peak,
            'peak_local' => $peak->setTimezone($tz),
            'night_start_local' => $evening,
            'night_end_local' => $evening->modify('+1 day'),
            'dark_start_local' => $nightStart,
            'dark_end_local' => $local($last['jd']),
            'peak_in_dark' => $peakJd >= $first['jd'] && $peakJd <= $last['jd'],
            'best_start_local' => $best === [] ? null : $local($best[0]['jd']),
            'best_end_local' => $best === [] ? null : $local($best[array_key_last($best)]['jd']),
            'highest_local' => $local($highest['jd']),
            'highest_altitude_deg' => $highest['alt'],
            'highest_azimuth_deg' => $highest['az'],
            'never_rises' => $highest['alt'] <= 0,
            'moon_illumination' => $illumination,
            'moon_waxing' => $waxing,
            'moon_up_fraction' => $moonUp / count($moonPool),
            'solar_longitude_deg' => $longitude,
            'ra_deg' => $ra,
            'dec_deg' => $dec,
        ];
    }

    /** @param list<array{jd: float}> $spell */
    private static function distance(array $spell, float $jd): float
    {
        $start = $spell[0]['jd'];
        $end = $spell[array_key_last($spell)]['jd'];

        return $jd < $start ? $start - $jd : ($jd > $end ? $jd - $end : 0.0);
    }

    /** @return array{0: float, 1: float} */
    private static function equatorial(float $x, float $y, float $z, float $jd): array
    {
        $t = ($jd - 2451545.0) / 36525.0;
        $epsilon = deg2rad(23.439291 - 0.0130042 * $t);
        $ye = $y * cos($epsilon) - $z * sin($epsilon);
        $ze = $y * sin($epsilon) + $z * cos($epsilon);
        $ra = fmod(rad2deg(atan2($ye, $x)) + 360.0, 360.0);
        $dec = rad2deg(atan2($ze, sqrt($x ** 2 + $ye ** 2)));

        return [$ra, $dec];
    }

    private static function julianDay(DateTimeInterface $instant): float
    {
        return 2440587.5 + ((float) $instant->format('U.u')) / 86400;
    }

    private static function fromJulianDay(float $jd): DateTimeImmutable
    {
        $seconds = (int) round(($jd - 2440587.5) * 86400);

        return (new DateTimeImmutable('@'.$seconds))->setTimezone(new DateTimeZone('UTC'));
    }

    private static function carbon(float $jd): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestampUTC((int) round(($jd - 2440587.5) * 86400));
    }
}

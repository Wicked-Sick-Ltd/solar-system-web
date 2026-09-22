<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Museum-label number formatting. Astronomy spans 20-odd orders of magnitude,
 * so these helpers reach for scientific notation, AU, and "× 10ⁿ" where plain
 * decimals would be unreadable — and return null (not "0" or "—") when a value
 * is genuinely absent, leaving the caller to decide how to show a gap.
 */
final class Format
{
    public static function lightYears(?float $parsecs): string
    {
        return $parsecs === null ? __('Distance unknown') : self::unit($parsecs * 3.261563777, 'light-years');
    }

    /** Preserve limits and asymmetric uncertainties from archive measurements.
     * @param  array<string,mixed>  $data
     */
    public static function exoplanetMeasurement(array $data, string $field, string $unit): string
    {
        if (! isset($data[$field]) || ! is_numeric($data[$field])) {
            return __('Unknown');
        }
        $limit = (int) ($data[$field.'lim'] ?? 0);
        $prefix = match ($limit) {
            1 => '< ', -1 => '> ', default => ''
        };
        $text = $prefix.self::number((float) $data[$field], 4);
        if ($limit === 0 && isset($data[$field.'err1'], $data[$field.'err2'])) {
            $text .= ' (+'.self::number(abs((float) $data[$field.'err1']), 4).' / −'.self::number(abs((float) $data[$field.'err2']), 4).')';
        }

        return $text.' '.$unit;
    }

    /** A distance in astronomical units. */
    public static function au(?float $au, int $places = 3): ?string
    {
        if ($au === null) {
            return null;
        }

        return self::trimZeros(number_format($au, $places)).' AU';
    }

    /** A length in km, switching to scientific notation for large/small values. */
    public static function km(?float $km, int $places = 0): ?string
    {
        if ($km === null) {
            return null;
        }

        if ($km !== 0.0 && (abs($km) >= 1_000_000 || abs($km) < 0.01)) {
            return self::scientific($km, 2).' km';
        }

        $places = abs($km) < 10 ? 2 : $places;

        return number_format($km, $places).' km';
    }

    /** A mass in kg — always scientific; that's how astronomers read it. */
    public static function massKg(?float $kg): ?string
    {
        if ($kg === null) {
            return null;
        }

        return self::scientific($kg, 3).' kg';
    }

    /** A duration given in days, rendered in the most readable unit. */
    public static function periodDays(?float $days): ?string
    {
        if ($days === null) {
            return null;
        }

        if ($days < 2) {
            $hours = $days * 24;

            return self::trimZeros(number_format($hours, 1)).' hours';
        }

        if ($days < 900) {
            return self::trimZeros(number_format($days, 1)).' days';
        }

        $years = $days / 365.25;

        return self::trimZeros(number_format($years, $years < 100 ? 2 : 1)).' years';
    }

    public static function hours(?float $hours): ?string
    {
        if ($hours === null) {
            return null;
        }

        if (abs($hours) >= 48) {
            return self::trimZeros(number_format($hours / 24, 2)).' days';
        }

        return self::trimZeros(number_format($hours, 2)).' hours';
    }

    public static function degrees(?float $deg, int $places = 2): ?string
    {
        if ($deg === null) {
            return null;
        }

        return self::trimZeros(number_format($deg, $places)).'°';
    }

    public static function number(?float $value, int $places = 3): ?string
    {
        if ($value === null) {
            return null;
        }

        return self::trimZeros(number_format($value, $places));
    }

    public static function unit(?float $value, string $unit, int $places = 2): ?string
    {
        if ($value === null) {
            return null;
        }

        return self::trimZeros(number_format($value, $places)).' '.$unit;
    }

    /** A whole count with thousands separators. */
    public static function count(int $value): string
    {
        return number_format($value);
    }

    /**
     * Scientific notation with a Unicode superscript exponent,
     * e.g. 5.683 × 10²⁶.
     */
    public static function scientific(float $value, int $places = 3): string
    {
        if ($value === 0.0) {
            return '0';
        }

        $exponent = (int) floor(log10(abs($value)));

        // Small exponents read better as plain decimals.
        if ($exponent >= -2 && $exponent <= 3) {
            return self::trimZeros(number_format($value, max($places, 0)));
        }

        $mantissa = $value / (10 ** $exponent);

        return self::trimZeros(number_format($mantissa, $places)).' × 10'.self::superscript($exponent);
    }

    public static function date(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        // Bare years and partial dates are common in discovery metadata.
        if (preg_match('/^\d{4}$/', $raw)) {
            return $raw;
        }

        try {
            return CarbonImmutable::parse($raw)->isoFormat('D MMMM YYYY');
        } catch (\Throwable) {
            return $raw;
        }
    }

    /** 16-point compass label for an azimuth measured from north through east. */
    public static function bearing(float $azimuthDeg): string
    {
        $points = ['N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE', 'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW'];

        return $points[(int) floor((fmod($azimuthDeg, 360) + 360 + 11.25) / 22.5) % 16];
    }

    public static function relative(?CarbonInterface $when): ?string
    {
        return $when?->diffForHumans();
    }

    private static function trimZeros(string $formatted): string
    {
        if (! str_contains($formatted, '.')) {
            return $formatted;
        }

        return rtrim(rtrim($formatted, '0'), '.');
    }

    private static function superscript(int $n): string
    {
        $map = ['0' => '⁰', '1' => '¹', '2' => '²', '3' => '³', '4' => '⁴',
            '5' => '⁵', '6' => '⁶', '7' => '⁷', '8' => '⁸', '9' => '⁹', '-' => '⁻'];

        return strtr((string) $n, $map);
    }
}

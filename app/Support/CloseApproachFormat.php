<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\SolarApi\Data\CloseApproach;

/** Keep missing values, true zero and positive values below display precision distinct. */
final class CloseApproachFormat
{
    /** Bowell relation: diameter in km from absolute magnitude H and visual albedo. */
    private const float DIAMETER_CONSTANT_KM = 1329.0;

    private const float DARK_ALBEDO = 0.05;

    private const float BRIGHT_ALBEDO = 0.25;

    public static function measurement(?float $value, string $unit, int $places): string
    {
        if ($value === null) {
            return '—';
        }
        $resolution = 10 ** -$places;
        if ($value > 0 && $value < $resolution) {
            return '<'.Format::unit($resolution, $unit, $places);
        }

        return Format::unit($value, $unit, $places) ?? '—';
    }

    /**
     * Measured diameter when a radius is on record; otherwise a range from
     * absolute magnitude. Null when neither is available.
     *
     * @return array{text:string,approximate:bool,note:?string}|null
     */
    public static function size(CloseApproach $approach): ?array
    {
        $diameter = $approach->diameterKm();
        if ($diameter !== null) {
            return [
                'text' => self::lengthFromKm($diameter),
                'approximate' => false,
                'note' => null,
            ];
        }

        $magnitude = $approach->absoluteMagnitudeH;
        if ($magnitude === null) {
            return null;
        }

        $base = self::DIAMETER_CONSTANT_KM * (10 ** (-0.2 * $magnitude));
        if (! is_finite($base) || $base <= 0) {
            return null;
        }

        $minKm = $base / sqrt(self::BRIGHT_ALBEDO);
        $maxKm = $base / sqrt(self::DARK_ALBEDO);
        if (! is_finite($minKm) || ! is_finite($maxKm) || $maxKm <= 0) {
            return null;
        }

        $note = __('Estimated from absolute magnitude H = :h, assuming a visual albedo between 0.05 and 0.25. This is not a measured diameter.', [
            'h' => Format::number($magnitude, 2),
        ]);

        if ($maxKm < 0.001) {
            return [
                'text' => __('under 1 m'),
                'approximate' => true,
                'note' => $note,
            ];
        }

        $minText = self::lengthFromKm($minKm);
        $maxText = self::lengthFromKm($maxKm);
        $range = $minText === $maxText ? $minText : $minText.'–'.$maxText;

        return [
            'text' => __('about :range', ['range' => $range]),
            'approximate' => true,
            'note' => $note,
        ];
    }

    public static function lengthFromKm(float $km): string
    {
        if ($km < 1) {
            $metres = $km * 1000;
            $places = $metres < 10 ? 1 : 0;
            $formatted = number_format($metres, $places);
            if ($places > 0) {
                $formatted = rtrim(rtrim($formatted, '0'), '.');
            }

            return $formatted.' m';
        }

        return Format::km($km) ?? '—';
    }
}

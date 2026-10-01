<?php

declare(strict_types=1);

namespace App\Support;

/** Keep missing values, true zero and positive values below display precision distinct. */
final class CloseApproachFormat
{
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
}

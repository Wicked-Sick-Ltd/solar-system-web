<?php

declare(strict_types=1);

namespace App\Services\Weather\Data;

use Carbon\CarbonImmutable;
use Throwable;

final readonly class HourlyForecast
{
    /** @param array<string, array<string, float|null>> $hours */
    public function __construct(public string $fetchedAtUtc, public array $hours) {}

    /** @param array<mixed> $data */
    public static function fromCache(array $data): ?self
    {
        $fetched = $data['fetched_at_utc'] ?? null;
        $hours = $data['hours'] ?? null;
        if (! self::validTime($fetched) || ! is_array($hours) || $hours === [] || count($hours) > 168) {
            return null;
        }
        foreach ($hours as $time => $point) {
            if (! self::validTime($time) || substr($time, 14) !== '00:00Z' || ! is_array($point)
                || count($point) !== 4 || array_diff(['cloud_cover', 'visibility', 'wind_speed_10m', 'relative_humidity_2m'], array_keys($point)) !== []) {
                return null;
            }
            foreach ($point as $field => $value) {
                if ($value !== null && ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value) || $value < 0
                    || (in_array($field, ['cloud_cover', 'relative_humidity_2m'], true) && $value > 100)
                    || ($field === 'visibility' && round($value) >= PHP_INT_MAX))) {
                    return null;
                }
            }
        }

        return new self($fetched, $hours);
    }

    private static function validTime(mixed $time): bool
    {
        if (! is_string($time) || ! preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$/D', $time)) {
            return false;
        }
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $time, 'UTC');

            return $date && $date->year >= 1 && $date->toIso8601ZuluString() === $time;
        } catch (Throwable) {
            return false;
        }
    }
}

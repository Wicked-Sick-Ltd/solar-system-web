<?php

declare(strict_types=1);

namespace App\Services\SolarApi\Data;

use App\Services\SolarApi\Exceptions\SolarApiException;

/** A measured host summary returned by the galaxy map, not a complete star record. */
final readonly class MeasuredHost
{
    public function __construct(
        public string $id,
        public string $name,
        public float $distancePc,
        public int $planetCount,
        public ?float $distancePlusPc,
        public ?float $distanceMinusPc,
    ) {}

    /** @param array<string, mixed> $row */
    public static function fromArray(array $row): self
    {
        foreach (['id', 'name'] as $field) {
            if (! is_string($row[$field] ?? null) || trim($row[$field]) === '') {
                throw new SolarApiException('Invalid measured host identity.');
            }
        }
        $distance = self::number($row['distance_pc'] ?? null);
        $count = $row['planet_count'] ?? null;
        if ($distance === null || $distance <= 0 || ! is_int($count) || $count < 0) {
            throw new SolarApiException('Invalid measured host summary.');
        }

        return new self($row['id'], $row['name'], $distance, $count,
            self::number($row['distance_error_plus_pc'] ?? null), self::number($row['distance_error_minus_pc'] ?? null));
    }

    private static function number(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }
        if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value)) {
            throw new SolarApiException('Invalid measured host distance.');
        }

        return (float) $value;
    }
}

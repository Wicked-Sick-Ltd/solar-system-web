<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\SolarApi\Data\ObjectDetail;
use App\Services\SolarApi\Data\Position;

/**
 * Pluto's distance from the Sun, for the dedication panel on its detail page.
 *
 * The backend carries no orbital elements for Pluto (only physical + visual
 * blocks), so the page would otherwise show no distance at all. Reference
 * values come from the NASA Planetary Fact Sheet; live values from the API
 * take precedence whenever they exist.
 */
final readonly class PlutoDistance
{
    public const string OBJECT_ID = 'dwarf-pluto';

    /** NASA Planetary Fact Sheet — semi-major axis 5,906.4 million km. */
    public const float MEAN_AU = 39.48;

    /** NASA Planetary Fact Sheet — perihelion 4,436.8 million km. */
    public const float PERIHELION_AU = 29.66;

    /** NASA Planetary Fact Sheet — aphelion 7,375.9 million km. */
    public const float APHELION_AU = 49.31;

    private function __construct(
        public float $meanAu,
        public float $perihelionAu,
        public float $aphelionAu,
        public ?float $nowAu,
        private bool $live,
    ) {}

    public static function appliesTo(ObjectDetail $object): bool
    {
        return $object->id === self::OBJECT_ID;
    }

    public static function for(ObjectDetail $object, ?Position $position): self
    {
        $orbital = $object->orbital;
        $now = $position?->distanceFromSunAu;

        return new self(
            meanAu: $orbital->semiMajorAxisAu ?? self::MEAN_AU,
            perihelionAu: $orbital->perihelionAu ?? self::PERIHELION_AU,
            aphelionAu: $orbital->aphelionAu ?? self::APHELION_AU,
            nowAu: $now,
            live: $orbital?->semiMajorAxisAu !== null || $now !== null,
        );
    }

    /** True when at least one figure came from the API rather than the fact sheet. */
    public function isLive(): bool
    {
        return $this->live;
    }
}

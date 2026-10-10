<?php

declare(strict_types=1);

namespace App\Support\Flyby;

/**
 * One geocentric ecliptic state (J2000). Distances are kilometres.
 */
final readonly class VectorSample
{
    public function __construct(
        public float $jd,
        public float $xKm,
        public float $yKm,
        public float $zKm,
        public ?float $vxKmS = null,
        public ?float $vyKmS = null,
        public ?float $vzKmS = null,
    ) {}

    public function distanceKm(): float
    {
        return sqrt($this->xKm ** 2 + $this->yKm ** 2 + $this->zKm ** 2);
    }

    public function finite(): bool
    {
        return is_finite($this->jd) && is_finite($this->xKm) && is_finite($this->yKm) && is_finite($this->zKm);
    }
}

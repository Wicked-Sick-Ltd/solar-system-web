<?php

declare(strict_types=1);

namespace App\Services\SolarApi\Data;

use App\Services\SolarApi\Data\Concerns\CastsValues;
use App\Support\ObjectType;

/**
 * A lightweight catalogue row. Deliberately tolerant: the various list
 * endpoints (/objects, /planets/{}/moons, /neos, /comets/periodic, /tnos,
 * /dwarf-planets) each return a different subset of these columns, so every
 * field is optional and `fromArray` simply takes what is present.
 */
final readonly class ObjectSummary
{
    use CastsValues;

    public function __construct(
        public string $id,
        public string $name,
        public ?string $designation,
        public ?string $objectType,
        public ?string $parentId,
        public ?string $discoverer,
        public ?string $discoveryDate,
        public ?string $wikipediaUrl,
        public ?float $radiusKm,
        public ?float $massKg,
        public ?float $densityGCm3,
        public ?float $semiMajorAxisAu,
        public ?float $perihelionAu,
        public ?float $aphelionAu,
        public ?float $eccentricity,
        public ?float $inclinationDeg,
        public ?float $orbitalPeriodDays,
        public ?float $geometricAlbedo,
        public ?float $absoluteMagnitudeH,
        public bool $isPha,
    ) {}

    /** @param array<string,mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            id: (string) ($d['id'] ?? ''),
            name: self::str($d, 'name') ?? (self::str($d, 'designation') ?? 'Unnamed object'),
            designation: self::str($d, 'designation'),
            objectType: self::str($d, 'object_type'),
            parentId: self::str($d, 'parent_id'),
            discoverer: self::str($d, 'discoverer'),
            discoveryDate: self::str($d, 'discovery_date'),
            wikipediaUrl: self::str($d, 'wikipedia_url'),
            radiusKm: self::float($d, 'radius_km'),
            massKg: self::float($d, 'mass_kg'),
            densityGCm3: self::float($d, 'density_g_cm3'),
            semiMajorAxisAu: self::float($d, 'semi_major_axis_au'),
            perihelionAu: self::float($d, 'perihelion_au'),
            aphelionAu: self::float($d, 'aphelion_au'),
            eccentricity: self::float($d, 'eccentricity'),
            inclinationDeg: self::float($d, 'inclination_deg'),
            orbitalPeriodDays: self::float($d, 'orbital_period_days'),
            geometricAlbedo: self::float($d, 'geometric_albedo'),
            absoluteMagnitudeH: self::float($d, 'absolute_magnitude_h'),
            isPha: self::bool($d, 'is_pha'),
        );
    }

    /** Stable, URL-safe permalink slug — the backend resolves detail by id. */
    public function slug(): string
    {
        return $this->id;
    }

    /** Human label for the object's type, e.g. "Dwarf planet". */
    public function typeLabel(): ?string
    {
        return $this->objectType ? ObjectType::label($this->objectType) : null;
    }

    public function diameterKm(): ?float
    {
        return $this->radiusKm !== null ? $this->radiusKm * 2 : null;
    }
}

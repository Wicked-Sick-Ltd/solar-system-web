<?php

declare(strict_types=1);

namespace App\Services\SolarApi\Data;

use App\Services\SolarApi\Data\Concerns\CastsValues;

/** Atmosphere block from the NASA fact sheets. */
final readonly class Atmosphere
{
    use CastsValues;

    /**
     * @param  list<array{species:string,fraction:float,unit:string}>  $composition
     */
    public function __construct(
        public ?float $surfacePressureBar,
        public ?string $pressureNote,
        public ?float $temperatureK,
        public ?string $temperatureNote,
        public ?float $densityKgM3,
        public ?float $scaleHeightKm,
        public ?float $meanMolecularWeight,
        public ?string $windNote,
        public array $composition,
    ) {}

    /** @param array<string,mixed> $d */
    public static function fromArray(array $d): self
    {
        $composition = [];
        foreach ((array) ($d['composition'] ?? []) as $c) {
            if (is_array($c) && isset($c['species']) && is_numeric($c['fraction'] ?? null)) {
                $composition[] = ['species' => (string) $c['species'], 'fraction' => (float) $c['fraction'], 'unit' => (string) ($c['unit'] ?? '%')];
            }
        }

        return new self(
            surfacePressureBar: self::float($d, 'surface_pressure_bar'),
            pressureNote: self::str($d, 'pressure_note'),
            temperatureK: self::float($d, 'temperature_k'),
            temperatureNote: self::str($d, 'temperature_note'),
            densityKgM3: self::float($d, 'density_kg_m3'),
            scaleHeightKm: self::float($d, 'scale_height_km'),
            meanMolecularWeight: self::float($d, 'mean_molecular_weight'),
            windNote: self::str($d, 'wind_note'),
            composition: $composition,
        );
    }

    public function hasAny(): bool
    {
        return $this->surfacePressureBar !== null || $this->temperatureK !== null || $this->composition !== [];
    }
}

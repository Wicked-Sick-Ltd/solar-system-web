<?php

declare(strict_types=1);

namespace App\Services\SolarApi\Data;

use App\Services\SolarApi\Data\Concerns\CastsValues;

final readonly class PhysicalProperties
{
    use CastsValues;

    public function __construct(
        public ?float $radiusKm,
        public ?float $equatorialRadiusKm,
        public ?float $polarRadiusKm,
        public ?float $massKg,
        public ?float $densityGCm3,
        public ?float $rotationPeriodHours,
        public ?float $axialTiltDeg,
        public ?float $surfaceGravityMS2,
        public ?float $escapeVelocityKmS,
        public ?float $gmKm3S2 = null,
        public ?float $volumeKm3 = null,
        public ?float $ellipticity = null,
        public ?float $momentOfInertia = null,
        public ?float $j2 = null,
        public ?string $magneticField = null,
        public ?float $lengthOfDayHours = null,
        public ?float $synodicPeriodDays = null,
        public ?float $meanOrbitalVelocityKmS = null,
        public ?float $meanTemperatureK = null,
        public ?float $blackBodyTemperatureK = null,
        public ?float $solarIrradianceWM2 = null,
        public ?string $extentKm = null,
        public ?string $poleRaDec = null,
    ) {}

    /** @param array<string,mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            radiusKm: self::float($d, 'radius_km'),
            equatorialRadiusKm: self::float($d, 'equatorial_radius_km'),
            polarRadiusKm: self::float($d, 'polar_radius_km'),
            massKg: self::float($d, 'mass_kg'),
            densityGCm3: self::float($d, 'density_g_cm3'),
            rotationPeriodHours: self::float($d, 'rotation_period_hours'),
            axialTiltDeg: self::float($d, 'axial_tilt_deg'),
            surfaceGravityMS2: self::float($d, 'surface_gravity_m_s2'),
            escapeVelocityKmS: self::float($d, 'escape_velocity_km_s'),
            gmKm3S2: self::float($d, 'gm_km3_s2'),
            volumeKm3: self::float($d, 'volume_km3'),
            ellipticity: self::float($d, 'ellipticity'),
            momentOfInertia: self::float($d, 'moment_of_inertia'),
            j2: self::float($d, 'j2'),
            magneticField: self::str($d, 'magnetic_field'),
            lengthOfDayHours: self::float($d, 'length_of_day_hours'),
            synodicPeriodDays: self::float($d, 'synodic_period_days'),
            meanOrbitalVelocityKmS: self::float($d, 'mean_orbital_velocity_km_s'),
            meanTemperatureK: self::float($d, 'mean_temperature_k'),
            blackBodyTemperatureK: self::float($d, 'black_body_temperature_k'),
            solarIrradianceWM2: self::float($d, 'solar_irradiance_w_m2'),
            extentKm: self::str($d, 'extent_km'),
            poleRaDec: self::str($d, 'pole_ra_dec'),
        );
    }

    public function hasAny(): bool
    {
        return $this->radiusKm !== null
            || $this->massKg !== null
            || $this->densityGCm3 !== null
            || $this->rotationPeriodHours !== null;
    }

    /** Mean diameter in km, where a radius is known. */
    public function diameterKm(): ?float
    {
        return $this->radiusKm !== null ? $this->radiusKm * 2 : null;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\SolarApi\Data;

use App\Services\SolarApi\Data\Concerns\CastsValues;

/**
 * Keplerian orbital elements for a single object. Any field may be null.
 */
final readonly class OrbitalElements
{
    use CastsValues;

    public function __construct(
        public ?string $epoch,
        public ?float $epochJd,
        public ?string $frame,
        public ?string $centre,
        public ?float $semiMajorAxisAu,
        public ?float $eccentricity,
        public ?float $inclinationDeg,
        public ?float $longitudeAscendingNodeDeg,
        public ?float $argumentPeriapsisDeg,
        public ?float $meanAnomalyDeg,
        public ?float $orbitalPeriodDays,
        public ?float $perihelionAu,
        public ?float $aphelionAu,
        public ?float $meanMotionDegPerDay,
        public ?string $orbitClassCode = null,
        public ?string $orbitClassName = null,
        public ?float $moidAu = null,
        public ?float $moidJupiterAu = null,
        public ?float $tisserandJupiter = null,
        public ?int $conditionCode = null,
        public ?float $dataArcDays = null,
        public ?string $firstObs = null,
        public ?string $lastObs = null,
        public ?int $nObsUsed = null,
        public ?float $rmsArcsec = null,
        public ?string $solutionDate = null,
        public ?string $producer = null,
        public ?float $perihelionTimeJd = null,
    ) {}

    /** @param array<string,mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            epoch: self::str($d, 'epoch'),
            epochJd: self::float($d, 'epoch_jd'),
            frame: self::str($d, 'frame'),
            centre: self::str($d, 'centre'),
            semiMajorAxisAu: self::float($d, 'semi_major_axis_au'),
            eccentricity: self::float($d, 'eccentricity'),
            inclinationDeg: self::float($d, 'inclination_deg'),
            longitudeAscendingNodeDeg: self::float($d, 'longitude_ascending_node_deg'),
            argumentPeriapsisDeg: self::float($d, 'argument_periapsis_deg'),
            meanAnomalyDeg: self::float($d, 'mean_anomaly_deg'),
            orbitalPeriodDays: self::float($d, 'orbital_period_days'),
            perihelionAu: self::float($d, 'perihelion_au'),
            aphelionAu: self::float($d, 'aphelion_au'),
            meanMotionDegPerDay: self::float($d, 'mean_motion_deg_per_day'),
            orbitClassCode: self::str($d, 'orbit_class_code'),
            orbitClassName: self::str($d, 'orbit_class_name'),
            moidAu: self::float($d, 'moid_au'),
            moidJupiterAu: self::float($d, 'moid_jupiter_au'),
            tisserandJupiter: self::float($d, 'tisserand_jupiter'),
            conditionCode: self::int($d, 'condition_code'),
            dataArcDays: self::float($d, 'data_arc_days'),
            firstObs: self::str($d, 'first_obs'),
            lastObs: self::str($d, 'last_obs'),
            nObsUsed: self::int($d, 'n_obs_used'),
            rmsArcsec: self::float($d, 'rms_arcsec'),
            solutionDate: self::str($d, 'solution_date'),
            producer: self::str($d, 'producer'),
            perihelionTimeJd: self::float($d, 'perihelion_time_jd'),
        );
    }

    /** Does this record carry enough to be worth rendering / propagating? */
    public function hasAny(): bool
    {
        return $this->semiMajorAxisAu !== null
            || $this->eccentricity !== null
            || $this->orbitalPeriodDays !== null
            || $this->perihelionAu !== null;
    }

    /** Enough elements present to compute a position via /positions? */
    /** Plain-English reading of the MPC uncertainty parameter U (0 best … 9 worst). */
    public function conditionReading(): ?string
    {
        return match (true) {
            $this->conditionCode === null => null,
            $this->conditionCode <= 2 => __('very well determined'),
            $this->conditionCode <= 5 => __('reasonably well determined'),
            $this->conditionCode <= 7 => __('poorly determined'),
            default => __('very uncertain — the object may be effectively lost'),
        };
    }

    /** Anything the "Orbit quality" card renders (MOID lives on the orbital card, not here). */
    public function hasQualityData(): bool
    {
        return $this->conditionCode !== null || $this->dataArcDays !== null || $this->nObsUsed !== null
            || $this->firstObs !== null || $this->lastObs !== null || $this->rmsArcsec !== null || $this->solutionDate !== null;
    }

    public function isPropagatable(): bool
    {
        return $this->semiMajorAxisAu !== null
            && $this->eccentricity !== null
            && $this->orbitalPeriodDays !== null;
    }
}

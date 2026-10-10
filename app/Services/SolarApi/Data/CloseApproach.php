<?php

declare(strict_types=1);

namespace App\Services\SolarApi\Data;

use App\Services\SolarApi\Data\Concerns\CastsValues;

/** One close approach of a small body to a planet or the Moon (JPL CAD / SBDB). */
final readonly class CloseApproach
{
    use CastsValues;

    public const float AU_PER_LUNAR_DISTANCE = 0.002569555;

    /** The catalogue footnote uses this mean Earth–Moon distance. */
    public const int KM_PER_LUNAR_DISTANCE = 384_400;

    public function __construct(
        public ?string $body,
        public ?string $cdIso,
        public ?float $distAu,
        public ?float $distMinAu,
        public ?float $distMaxAu,
        public ?float $vRelKmS,
        public ?string $tSigma,
        // Only set on the date-window listing, which spans many objects.
        public ?string $objectId = null,
        public ?string $name = null,
        public ?float $massKg = null,
        public ?string $designation = null,
        public ?float $radiusKm = null,
        public ?float $absoluteMagnitudeH = null,
    ) {}

    /** @param array<string,mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            body: self::str($d, 'body'),
            cdIso: self::str($d, 'cd_iso'),
            distAu: self::float($d, 'dist_au'),
            distMinAu: self::float($d, 'dist_min_au'),
            distMaxAu: self::float($d, 'dist_max_au'),
            vRelKmS: self::float($d, 'v_rel_km_s'),
            tSigma: self::str($d, 't_sigma'),
            objectId: self::str($d, 'object_id'),
            name: self::str($d, 'name') ?? self::str($d, 'designation'),
            massKg: self::float($d, 'mass_kg'),
            designation: self::str($d, 'designation'),
            radiusKm: self::positive($d, 'radius_km'),
            absoluteMagnitudeH: self::finite($d, 'absolute_magnitude_h'),
        );
    }

    public function lunarDistances(): ?float
    {
        return $this->distAu === null ? null : $this->distAu / self::AU_PER_LUNAR_DISTANCE;
    }

    public function distanceKm(): ?float
    {
        $lunar = $this->lunarDistances();

        return $lunar === null ? null : $lunar * self::KM_PER_LUNAR_DISTANCE;
    }

    /** Catalogue diameter, twice a recorded radius. */
    public function diameterKm(): ?float
    {
        return $this->radiusKm === null ? null : $this->radiusKm * 2;
    }

    /** @param array<string,mixed> $data */
    private static function finite(array $data, string $key): ?float
    {
        $value = self::float($data, $key);

        return $value !== null && is_finite($value) ? $value : null;
    }

    /** @param array<string,mixed> $data */
    private static function positive(array $data, string $key): ?float
    {
        $value = self::finite($data, $key);

        return $value !== null && $value > 0 ? $value : null;
    }
}

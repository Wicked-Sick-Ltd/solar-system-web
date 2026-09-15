<?php

declare(strict_types=1);

namespace App\Services\SolarApi\Data;

use App\Services\SolarApi\Data\Concerns\CastsValues;

/** One close approach of a small body to a planet or the Moon (JPL CAD / SBDB). */
final readonly class CloseApproach
{
    use CastsValues;

    public const float AU_PER_LUNAR_DISTANCE = 0.002569555;

    public function __construct(
        public ?string $body,
        public ?string $cdIso,
        public ?float $distAu,
        public ?float $distMinAu,
        public ?float $distMaxAu,
        public ?float $vRelKmS,
        public ?string $tSigma,
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
        );
    }

    public function lunarDistances(): ?float
    {
        return $this->distAu === null ? null : $this->distAu / self::AU_PER_LUNAR_DISTANCE;
    }
}

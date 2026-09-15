<?php

declare(strict_types=1);

namespace App\Services\SolarApi\Data;

use App\Services\SolarApi\Data\Concerns\CastsValues;

final readonly class VisualProperties
{
    use CastsValues;

    public function __construct(
        public ?float $geometricAlbedo,
        public ?float $bondAlbedo,
        public ?float $absoluteMagnitudeH,
        public ?float $colourBV,
        public ?string $spectralType,
        public ?string $dominantColourHex,
        public ?string $spectralTypeTholen = null,
        public ?float $colourUB = null,
        public ?float $magnitudeV10 = null,
        public ?float $cometM1 = null,
        public ?float $cometK1 = null,
    ) {}

    /** @param array<string,mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            geometricAlbedo: self::float($d, 'geometric_albedo'),
            bondAlbedo: self::float($d, 'bond_albedo'),
            absoluteMagnitudeH: self::float($d, 'absolute_magnitude_h'),
            colourBV: self::float($d, 'colour_b_v'),
            spectralType: self::str($d, 'spectral_type'),
            dominantColourHex: self::str($d, 'dominant_colour_hex'),
            spectralTypeTholen: self::str($d, 'spectral_type_tholen'),
            colourUB: self::float($d, 'colour_u_b'),
            magnitudeV10: self::float($d, 'magnitude_v10'),
            cometM1: self::float($d, 'comet_m1'),
            cometK1: self::float($d, 'comet_k1'),
        );
    }

    public function hasAny(): bool
    {
        return $this->geometricAlbedo !== null
            || $this->absoluteMagnitudeH !== null
            || $this->spectralType !== null;
    }

    /** A safe, validated hex colour for rendering, or null. */
    public function safeColourHex(): ?string
    {
        if ($this->dominantColourHex !== null
            && preg_match('/^#[0-9a-fA-F]{6}$/', $this->dominantColourHex)) {
            return $this->dominantColourHex;
        }

        return null;
    }
}

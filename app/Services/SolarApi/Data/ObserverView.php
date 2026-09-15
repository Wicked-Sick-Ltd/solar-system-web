<?php

declare(strict_types=1);

namespace App\Services\SolarApi\Data;

use App\Services\SolarApi\Data\Concerns\CastsValues;

/** The observer-specific half of a /sky response: the view from one lat/lon. */
final readonly class ObserverView
{
    use CastsValues;

    public function __construct(
        public ?float $lat,
        public ?float $lon,
        public ?float $altitudeDeg,
        public ?float $azimuthDeg,
        public bool $isUp,
        public ?float $sunAltitudeDeg,
        public bool $isDark,
        public ?string $riseUtc,
        public ?string $transitUtc,
        public ?string $setUtc,
        public bool $circumpolar,
        public bool $neverRises,
    ) {}

    /** @param array<string,mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            lat: self::float($d, 'lat'),
            lon: self::float($d, 'lon'),
            altitudeDeg: self::float($d, 'altitude_deg'),
            azimuthDeg: self::float($d, 'azimuth_deg'),
            isUp: self::bool($d, 'is_up'),
            sunAltitudeDeg: self::float($d, 'sun_altitude_deg'),
            isDark: self::bool($d, 'is_dark'),
            riseUtc: self::str($d, 'rise_utc'),
            transitUtc: self::str($d, 'transit_utc'),
            setUtc: self::str($d, 'set_utc'),
            circumpolar: self::bool($d, 'circumpolar'),
            neverRises: self::bool($d, 'never_rises'),
        );
    }

    /** One-line verdict for the panel headline. */
    public function status(): string
    {
        if ($this->neverRises) {
            return __('Never rises from here');
        }
        if ($this->isUp && $this->isDark) {
            return __('Up now, and it\'s dark');
        }
        if ($this->isUp) {
            return __('Up now, but it\'s daylight');
        }

        return __('Below the horizon');
    }
}

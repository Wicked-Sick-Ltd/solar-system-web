<?php

declare(strict_types=1);

namespace App\Services\SolarApi\Data;

use App\Services\SolarApi\Data\Concerns\CastsValues;

/**
 * Where an object appears in Earth's sky, from /sky/{id}. Geocentric RA/Dec
 * (J2000) plus constellation and hemisphere; `observer` is present only when
 * the request carried a lat/lon.
 */
final readonly class SkyPosition
{
    use CastsValues;

    public function __construct(
        public ?string $name,
        public ?string $inputDatetime,
        public ?string $resolvedFrom,
        public ?float $raDeg,
        public ?string $raHms,
        public ?float $decDeg,
        public ?string $decDms,
        public ?float $distanceFromEarthAu,
        public ?float $distanceFromSunAu,
        public ?float $elongationDeg,
        public ?string $constellationAbbr,
        public ?string $constellationName,
        public ?string $hemisphere,
        public ?string $visibleFrom,
        public ?string $accuracyNote,
        public ?ObserverView $observer,
    ) {}

    /** @param array<string,mixed> $d */
    public static function fromArray(array $d): self
    {
        $constellation = is_array($d['constellation'] ?? null) ? $d['constellation'] : [];

        return new self(
            name: self::str($d, 'name'),
            inputDatetime: self::str($d, 'input_datetime'),
            resolvedFrom: self::str($d, 'resolved_from'),
            raDeg: self::float($d, 'ra_deg'),
            raHms: self::str($d, 'ra_hms'),
            decDeg: self::float($d, 'dec_deg'),
            decDms: self::str($d, 'dec_dms'),
            distanceFromEarthAu: self::float($d, 'distance_from_earth_au'),
            distanceFromSunAu: self::float($d, 'distance_from_sun_au'),
            elongationDeg: self::float($d, 'elongation_deg'),
            constellationAbbr: self::str($constellation, 'abbr'),
            constellationName: self::str($constellation, 'name'),
            hemisphere: self::str($d, 'hemisphere'),
            visibleFrom: self::str($d, 'visible_from'),
            accuracyNote: self::str($d, 'accuracy_note'),
            observer: is_array($d['observer'] ?? null) ? ObserverView::fromArray($d['observer']) : null,
        );
    }

    /** Plain-English reading of the elongation from the Sun. */
    public function elongationReading(): ?string
    {
        if ($this->elongationDeg === null) {
            return null;
        }

        return match (true) {
            $this->elongationDeg < 20 => __('too close to the Sun to observe'),
            $this->elongationDeg < 60 => __('near the Sun — look low at dusk or dawn'),
            $this->elongationDeg > 160 => __('near opposition — up most of the night'),
            default => __('well clear of the Sun'),
        };
    }

    public function constellationUrl(): ?string
    {
        return $this->constellationName
            ? 'https://en.wikipedia.org/wiki/'.str_replace(' ', '_', $this->constellationName).'_(constellation)'
            : null;
    }
}

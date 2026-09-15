<?php

declare(strict_types=1);

namespace App\Services\SolarApi\Data;

use App\Services\SolarApi\Data\Concerns\CastsValues;

/** Discovery circumstances (MPC numbered list, SBDB lookup, or the curated seed). */
final readonly class Discovery
{
    use CastsValues;

    public function __construct(
        public ?string $discoveredOn,
        public ?string $discoverer,
        public ?string $site,
        public ?string $location,
        public ?string $citation,
        public ?string $reference,
        public ?string $source,
    ) {}

    /** @param array<string,mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            discoveredOn: self::str($d, 'discovered_on'),
            discoverer: self::str($d, 'discoverer'),
            site: self::str($d, 'site'),
            location: self::str($d, 'location'),
            citation: self::str($d, 'citation') !== null ? html_entity_decode((string) self::str($d, 'citation'), ENT_QUOTES | ENT_HTML5) : null,
            reference: self::str($d, 'reference'),
            source: self::str($d, 'source'),
        );
    }

    public function hasAny(): bool
    {
        return $this->discoveredOn !== null || $this->discoverer !== null || $this->citation !== null;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\SolarApi\Data;

use App\Services\SolarApi\Data\Concerns\CastsValues;

final readonly class Exoplanet
{
    use CastsValues;

    /** @param array<string,mixed> $measurements */
    public function __construct(
        public string $id, public string $name, public string $hostId, public string $hostName,
        public ?float $distancePc, public ?string $discoveryMethod, public ?int $discoveryYear,
        public ?string $massProvenance, public bool $controversial,
        public array $measurements, public ?string $retrievedAt,
    ) {}

    /** @param array<string,mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self((string) $d['id'], (string) $d['name'], (string) $d['host_id'],
            (string) ($d['host_name'] ?? ''), self::float($d, 'distance_pc'),
            self::str($d, 'discovery_method'), self::int($d, 'discovery_year'),
            self::str($d, 'mass_provenance'), self::bool($d, 'controversial'),
            (array) ($d['source_data'] ?? []), self::str($d, 'retrieved_at'));
    }

    public function archiveUrl(): string
    {
        return 'https://exoplanetarchive.ipac.caltech.edu/overview/'.rawurlencode($this->name);
    }
}

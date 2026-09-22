<?php

declare(strict_types=1);

namespace App\Services\SolarApi\Data;

final readonly class GalaxyMap
{
    /** @param list<array<string,mixed>> $hosts */
    public function __construct(public array $hosts, public int $unmappedHosts, public bool $truncated) {}

    /** @param array<string,mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(array_values($d['results'] ?? []), (int) ($d['unmapped_hosts'] ?? 0),
            (bool) ($d['truncated'] ?? false));
    }
}

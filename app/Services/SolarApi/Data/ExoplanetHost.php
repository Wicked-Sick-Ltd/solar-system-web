<?php

declare(strict_types=1);

namespace App\Services\SolarApi\Data;

use App\Services\SolarApi\Data\Concerns\CastsValues;

final readonly class ExoplanetHost
{
    use CastsValues;

    /** @param list<Exoplanet> $planets */
    public function __construct(public string $id, public string $name, public ?float $distancePc,
        public ?float $distancePlusPc, public ?float $distanceMinusPc,
        public bool $mapped, public array $planets) {}

    /** @param array<string,mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self((string) $d['id'], (string) $d['name'], self::float($d, 'distance_pc'),
            self::float($d, 'distance_error_plus_pc'), self::float($d, 'distance_error_minus_pc'),
            isset($d['x_pc']), array_map(Exoplanet::fromArray(...), $d['planets'] ?? []));
    }
}

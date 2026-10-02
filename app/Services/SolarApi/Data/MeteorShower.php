<?php

declare(strict_types=1);

namespace App\Services\SolarApi\Data;

final readonly class MeteorShower
{
    /** @param list<MeteorParameterSet> $parameterSets */
    public function __construct(
        public int $iauNo,
        public string $code,
        public string $name,
        public array $parameterSets,
    ) {}

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self((int) $data['iau_no'], (string) $data['code'], (string) $data['name'],
            array_map(MeteorParameterSet::fromArray(...), array_values($data['parameter_sets'])));
    }
}

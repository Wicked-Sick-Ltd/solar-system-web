<?php

declare(strict_types=1);

namespace App\Services\SolarApi\Data;

final readonly class MeteorCatalogue
{
    public const LIMIT = 1000;

    /** @param list<MeteorShower> $showers */
    public function __construct(
        public array $showers,
        public int $parameterSetCount,
        public bool $possiblyTruncated,
    ) {}

    /** @param list<array<string,mixed>> $rows */
    public static function fromRows(array $rows): self
    {
        $groups = [];
        foreach ($rows as $row) {
            $set = MeteorParameterSet::fromArray($row);
            $groups[$set->iauNo][] = $set;
        }
        $showers = [];
        foreach ($groups as $sets) {
            $first = $sets[0];
            $showers[] = new MeteorShower($first->iauNo, $first->code, $first->name, $sets);
        }

        return new self($showers, count($rows), count($rows) >= self::LIMIT);
    }
}

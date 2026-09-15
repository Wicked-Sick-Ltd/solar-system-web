<?php

declare(strict_types=1);

namespace App\Services\SolarApi\Data;

use App\Services\SolarApi\Data\Concerns\CastsValues;
use App\Support\ObjectType;

/**
 * The full record for a single object: the base row plus its orbital,
 * physical and visual blocks, classification labels and provenance.
 */
final readonly class ObjectDetail
{
    use CastsValues;

    /**
     * @param  list<string>  $classifications
     * @param  list<Source>  $sources
     * @param  list<array{designation:string,kind:string}>  $designations
     * @param  list<CloseApproach>  $closeApproaches
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $designation,
        public ?string $objectType,
        public ?string $parentId,
        public ?string $discoverer,
        public ?string $discoveryDate,
        public ?string $wikipediaUrl,
        public ?string $notes,
        public ?string $updatedAt,
        public ?OrbitalElements $orbital,
        public ?PhysicalProperties $physical,
        public ?VisualProperties $visual,
        public array $classifications,
        public array $sources,
        public ?Discovery $discovery = null,
        public array $designations = [],
        public array $closeApproaches = [],
        public ?int $closeApproachCount = null,
        public ?Atmosphere $atmosphere = null,
        public ?bool $impactMonitored = null,
    ) {}

    /** @param array<string,mixed> $d */
    public static function fromArray(array $d): self
    {
        $orbital = is_array($d['orbital'] ?? null) ? OrbitalElements::fromArray($d['orbital']) : null;
        $physical = is_array($d['physical'] ?? null) ? PhysicalProperties::fromArray($d['physical']) : null;
        $visual = is_array($d['visual'] ?? null) ? VisualProperties::fromArray($d['visual']) : null;

        return new self(
            id: (string) ($d['id'] ?? ''),
            name: self::str($d, 'name') ?? (self::str($d, 'designation') ?? 'Unnamed object'),
            designation: self::str($d, 'designation'),
            objectType: self::str($d, 'object_type'),
            parentId: self::str($d, 'parent_id'),
            discoverer: self::str($d, 'discoverer'),
            discoveryDate: self::str($d, 'discovery_date'),
            wikipediaUrl: self::str($d, 'wikipedia_url'),
            notes: self::str($d, 'notes'),
            updatedAt: self::str($d, 'updated_at'),
            orbital: $orbital,
            physical: $physical,
            visual: $visual,
            classifications: array_values(array_map('strval', (array) ($d['classifications'] ?? []))),
            sources: array_values(array_map(
                static fn (array $s) => Source::fromArray($s),
                array_filter((array) ($d['sources'] ?? []), 'is_array'),
            )),
            discovery: is_array($d['discovery'] ?? null) && $d['discovery'] !== [] ? Discovery::fromArray($d['discovery']) : null,
            designations: array_values(array_map(
                static fn (array $x) => ['designation' => (string) $x['designation'], 'kind' => (string) ($x['kind'] ?? 'alternate')],
                array_filter((array) ($d['designations'] ?? []), static fn ($x) => is_array($x) && isset($x['designation'])),
            )),
            closeApproaches: array_values(array_map(
                static fn (array $c) => CloseApproach::fromArray($c),
                array_filter((array) ($d['close_approaches'] ?? []), 'is_array'),
            )),
            closeApproachCount: self::int($d, 'close_approach_count'),
            atmosphere: is_array($d['atmosphere'] ?? null) && $d['atmosphere'] !== [] ? Atmosphere::fromArray($d['atmosphere']) : null,
            impactMonitored: is_array($d['impact_monitoring'] ?? null) ? (bool) ($d['impact_monitoring']['flagged'] ?? false) : null,
        );
    }

    /**
     * Designations other than the display name and the id, for an "also known as" line.
     *
     * @return list<string>
     */
    public function aliases(): array
    {
        $out = [];
        foreach ($this->designations as $x) {
            $v = $x['designation'];
            if ($v === $this->name || $v === $this->id || $v === $this->designation || str_starts_with($v, 'ast-') || str_starts_with($v, 'NAIF ') || str_starts_with($v, 'satellite: ')) {
                continue;
            }
            $out[$v] = true;
        }

        return array_keys($out);
    }

    public function slug(): string
    {
        return $this->id;
    }

    public function typeLabel(): ?string
    {
        return $this->objectType ? ObjectType::label($this->objectType) : null;
    }

    /** Does the parent link point at a real catalogue object (not the Sun root)? */
    public function hasParent(): bool
    {
        return $this->parentId !== null && $this->parentId !== '' && $this->parentId !== 'sun';
    }

    public function isNeo(): bool
    {
        return in_array('NEO', $this->classifications, true);
    }

    public function isPha(): bool
    {
        return in_array('PHA', $this->classifications, true);
    }

    /** This object can host moons/rings sub-listings. */
    public function isPlanetLike(): bool
    {
        return in_array($this->objectType, ['planet', 'dwarf_planet', 'dwarf_planet_candidate'], true);
    }
}

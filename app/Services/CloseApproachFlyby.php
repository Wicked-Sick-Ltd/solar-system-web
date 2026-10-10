<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\SolarApi\Data\CloseApproach;
use App\Services\SolarApi\Data\ObjectDetail;
use App\Support\Flyby\FlybyFrame;
use App\Support\Flyby\LowPrecisionEphemeris;
use App\Support\Flyby\TwoBody;
use App\Support\Flyby\VectorSample;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Builds the ecliptic flyby figure for one catalogued Earth encounter.
 *
 * Prefers cached JPL Horizons geocentric vectors. When those are missing,
 * propagates the catalogue orbital elements (and a standard lunar ephemeris)
 * and marks that path approximate. Never invents a straight line.
 */
class CloseApproachFlyby
{
    public function __construct(private HorizonsClient $horizons) {}

    public function forObject(ObjectDetail $object, ?CarbonImmutable $now = null): ?FlybyFrame
    {
        $approach = $this->select($object->closeApproaches, $now);
        if ($approach === null || $approach->cdIso === null || $approach->distAu === null || $approach->distAu < 0.0) {
            return null;
        }

        try {
            $at = CarbonImmutable::parse($approach->cdIso)->utc();
        } catch (Throwable) {
            return null;
        }
        if ($at->year < 1) {
            return null;
        }

        $samples = null;
        foreach (array_slice($this->commands($object), 0, 2) as $command) {
            $samples = $this->horizons->arc($command, $at->subDays(3), $at->addDays(3));
            if ($samples !== null) {
                break;
            }
        }

        $trajectoryApproximate = false;
        if ($samples === null) {
            if ($object->orbital === null) {
                return null;
            }
            $samples = TwoBody::geocentricArc($object->orbital, $at);
            if ($samples === []) {
                return null;
            }
            $trajectoryApproximate = true;
        }

        $moon = $trajectoryApproximate ? null : $this->horizons->instant('301', $at);
        $moonApproximate = $moon === null;
        if ($moon === null) {
            $moon = LowPrecisionEphemeris::moon($at);
        }
        $sun = $trajectoryApproximate ? null : $this->horizons->instant('10', $at);
        if (! $sun instanceof VectorSample) {
            $sun = LowPrecisionEphemeris::sun($at);
        }

        return FlybyFrame::compose(
            $object->name,
            $at,
            $approach->distAu,
            $samples,
            $moon,
            $sun,
            $trajectoryApproximate,
            $moonApproximate,
            $object->orbital,
        );
    }

    /**
     * Compact diagram for a catalogue row that has no orbital elements.
     * One Horizons request; the Moon and Sun use the low-precision ephemeris.
     * Returns null rather than a straight line when the ephemeris is missing.
     */
    public function forListing(CloseApproach $approach): ?FlybyFrame
    {
        if ($approach->cdIso === null || $approach->distAu === null || $approach->distAu < 0.0) {
            return null;
        }
        if ($approach->body !== null && strcasecmp($approach->body, 'Earth') !== 0) {
            return null;
        }

        try {
            $at = CarbonImmutable::parse($approach->cdIso)->utc();
        } catch (Throwable) {
            return null;
        }

        $command = $this->commandsFrom($approach->objectId, $approach->designation, $approach->name)[0] ?? null;
        $samples = $command !== null ? $this->horizons->arc($command, $at->subDays(3), $at->addDays(3)) : null;
        if ($samples === null) {
            return null;
        }

        return FlybyFrame::compose(
            $approach->name ?? $approach->designation ?? __('Object'),
            $at,
            $approach->distAu,
            $samples,
            LowPrecisionEphemeris::moon($at),
            LowPrecisionEphemeris::sun($at),
            trajectoryApproximate: false,
            moonApproximate: true,
            objectElements: null,
            compact: true,
        );
    }

    /**
     * The next Earth encounter, or the most recent one when none lie ahead.
     *
     * @param  list<CloseApproach>  $approaches
     */
    public function select(array $approaches, ?CarbonImmutable $now = null): ?CloseApproach
    {
        $now ??= CarbonImmutable::now('UTC');
        /** @var list<array{0: CarbonImmutable, 1: CloseApproach}> $earth */
        $earth = [];
        foreach ($approaches as $approach) {
            if (strcasecmp((string) $approach->body, 'Earth') !== 0 || $approach->cdIso === null) {
                continue;
            }
            try {
                $when = CarbonImmutable::parse($approach->cdIso)->utc();
            } catch (Throwable) {
                continue;
            }
            $earth[] = [$when, $approach];
        }
        if ($earth === []) {
            return null;
        }
        usort($earth, fn (array $a, array $b): int => $a[0] <=> $b[0]);
        foreach ($earth as [$when, $approach]) {
            if ($when->greaterThanOrEqualTo($now)) {
                return $approach;
            }
        }

        return $earth[array_key_last($earth)][1];
    }

    /**
     * Horizons target names, most specific first. At most the first two are requested.
     *
     * @return list<string>
     */
    public function commands(ObjectDetail $object): array
    {
        return $this->commandsFrom($object->id, $object->designation, $object->name);
    }

    /**
     * @return list<string>
     */
    public function commandsFrom(?string $id, ?string $designation, ?string $name): array
    {
        $candidates = [];
        if ($id !== null && preg_match('/^ast-(\d+)$/', $id, $match) === 1) {
            $candidates[] = $match[1];
        }
        $designation = (string) $designation;
        if (preg_match('/^(\d+)\b/', $designation, $match) === 1) {
            $candidates[] = $match[1];
        }
        if (preg_match('/\(([^)]+)\)/', $designation, $match) === 1) {
            $candidates[] = trim($match[1]);
        }
        if (preg_match('/^(\d+P)\b/i', $designation, $match) === 1) {
            $candidates[] = strtoupper($match[1]);
        }
        if ($name !== null && $name !== '') {
            $candidates[] = $name;
        }

        $commands = [];
        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '' || strlen($candidate) > 40 || preg_match('/^[A-Za-z0-9][A-Za-z0-9 .\/+_()-]*$/', $candidate) !== 1) {
                continue;
            }
            $commands[$candidate] = $candidate;
        }

        return array_slice(array_values($commands), 0, 2);
    }
}

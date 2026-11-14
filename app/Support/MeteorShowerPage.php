<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\SolarApi\Data\MeteorParameterSet;
use App\Services\SolarApi\Data\MeteorShower;
use Carbon\CarbonImmutable;

/**
 * Canonical meteor-shower URLs and the copy derived from a catalogue record.
 *
 * The hub links the uppercase IAU code (LEO, GEM), so that spelling is the
 * one URL. Activity text is "annual" or a campaign year such as "2001"; it is
 * not a calendar date. An annual shower is titled for the current UTC year. A
 * reported peak stays a solar longitude, which is how the catalogue records it.
 */
final class MeteorShowerPage
{
    public static function canonicalCode(string $code): string
    {
        return mb_strtoupper(trim($code), 'UTF-8');
    }

    public static function title(MeteorShower $shower, ?CarbonImmutable $now = null): string
    {
        return __(':name :year: Peak Dates, Best Time to See Them and Where to Look', [
            'name' => $shower->name,
            'year' => (string) self::year($shower, $now),
        ]);
    }

    public static function description(MeteorShower $shower, ?CarbonImmutable $now = null): string
    {
        $text = __(':name :year: peak dates, the best time to see them and where to look.', [
            'name' => $shower->name,
            'year' => (string) self::year($shower, $now),
        ]);
        $peak = self::peakSolarLongitude($shower);
        if ($peak !== null) {
            $formatted = Format::number($peak, 4);
            if ($formatted !== null) {
                $text .= ' '.__('Reported peak near solar longitude :peak°.', ['peak' => $formatted]);
            }
        }

        return $text.' '.__('IAU Meteor Data Center measurements.');
    }

    public static function year(MeteorShower $shower, ?CarbonImmutable $now = null): int
    {
        if (self::isAnnual($shower)) {
            return self::clock($now)->year;
        }

        $years = self::activityYears($shower);

        return $years === [] ? self::clock($now)->year : max($years);
    }

    public static function peakSolarLongitude(MeteorShower $shower): ?float
    {
        /** @var list<array{index:int, set:MeteorParameterSet, longitude:float}> $withPeak */
        $withPeak = [];
        foreach ($shower->parameterSets as $index => $set) {
            $longitude = $set->measurements['solar_longitude_deg'] ?? null;
            if ($longitude !== null) {
                $withPeak[] = ['index' => $index, 'set' => $set, 'longitude' => $longitude];
            }
        }
        if ($withPeak === []) {
            return null;
        }

        $annual = array_values(array_filter(
            $withPeak,
            static fn (array $candidate): bool => self::activityIsAnnual($candidate['set']->activity),
        ));
        $pool = $annual !== [] ? $annual : $withPeak;
        usort($pool, static function (array $left, array $right): int {
            /** @var array{index:int, set:MeteorParameterSet, longitude:float} $left */
            /** @var array{index:int, set:MeteorParameterSet, longitude:float} $right */
            $byMembers = ($right['set']->members ?? -1) <=> ($left['set']->members ?? -1);

            return $byMembers !== 0 ? $byMembers : $left['index'] <=> $right['index'];
        });

        return $pool[0]['longitude'];
    }

    private static function isAnnual(MeteorShower $shower): bool
    {
        foreach ($shower->parameterSets as $set) {
            if (self::activityIsAnnual($set->activity)) {
                return true;
            }
        }

        return false;
    }

    private static function activityIsAnnual(?string $activity): bool
    {
        return $activity !== null && mb_strtolower(trim($activity), 'UTF-8') === 'annual';
    }

    /** @return list<int> */
    private static function activityYears(MeteorShower $shower): array
    {
        $years = [];
        foreach ($shower->parameterSets as $set) {
            if ($set->activity === null || preg_match_all('/\b(\d{4})\b/', $set->activity, $matches) === 0) {
                continue;
            }
            foreach ($matches[1] as $year) {
                $value = (int) $year;
                if ($value >= 1 && $value <= 9999) {
                    $years[] = $value;
                }
            }
        }

        return $years;
    }

    private static function clock(?CarbonImmutable $now): CarbonImmutable
    {
        return ($now ?? CarbonImmutable::now())->utc();
    }
}

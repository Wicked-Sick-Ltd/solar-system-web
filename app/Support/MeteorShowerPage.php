<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\SolarApi\Data\MeteorParameterSet;
use App\Services\SolarApi\Data\MeteorShower;
use App\Support\Sky\UkMeteorViewing;
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
        $adjective = self::adjective($shower->name);
        if ($adjective !== null && self::isAnnual($shower) && self::peakSolarLongitude($shower) !== null) {
            return __(':adjective Meteor Shower :year: Peak Date, Best Time to See It and Where to Look', [
                'adjective' => $adjective,
                'year' => (string) self::year($shower, $now),
            ]);
        }

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
            // An annual shower is titled for its next peak, so a January
            // shower read in November already carries the coming year.
            $peak = self::peakSolarLongitude($shower);
            if ($peak !== null) {
                return (int) UkMeteorViewing::nextSolarLongitude($peak, self::clock($now)->subDay())->format('Y');
            }

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

    /** "Geminids" becomes "Geminid", for "Geminid meteor shower". Null when the name is not an -ids plural. */
    public static function adjective(string $name): ?string
    {
        $name = trim($name);

        return preg_match('/\A(\p{Lu}[\p{L}\-\x{2019}\' ]*id)s\z/u', $name, $match) === 1 ? $match[1] : null;
    }

    /**
     * Calculated UK questions for the FAQ section and FAQPage data, then any hand-written ones from config.
     *
     * @param  array<string, mixed>  $viewing  from UkMeteorViewing::forShower()
     * @param  array<mixed>  $extra  hand-written pairs from config; malformed entries are skipped
     * @return list<array{question: string, answer: string}>
     */
    public static function ukFaq(MeteorShower $shower, array $viewing, array $extra = []): array
    {
        $label = self::adjective($shower->name);
        $label = $label !== null ? __(':adjective meteor shower', ['adjective' => $label]) : $shower->name;
        $time = static fn (\DateTimeInterface $t): string => $t->format('H:i');
        $night = __('the night of :dates', ['dates' => UkMeteorViewing::nightLabel($viewing['night_start_local'], $viewing['night_end_local'])]);

        $when = $viewing['best_start_local'] === null
            ? __('On :night the radiant stays below 30° while the sky is dark from the UK, so rates will be low. It is highest at about :high.', ['night' => $night, 'high' => $time($viewing['highest_local'])])
            : __('On :night, the best time from the UK is :start to :end (UK time), when the sky is dark and the radiant is at least 30° up. The peak itself is at about :peak on :date.', [
                'night' => $night,
                'start' => $time($viewing['best_start_local']),
                'end' => $time($viewing['best_end_local']),
                'peak' => $time($viewing['peak_local']),
                'date' => $viewing['peak_local']->format('j F'),
            ]);

        $faq = [
            ['question' => __('What time is the :label tonight in the UK?', ['label' => $label]), 'answer' => $when],
            ['question' => __('Where should I look for the :label from the UK?', ['label' => $label]), 'answer' => $viewing['never_rises']
                ? __('The radiant stays below the UK horizon during darkness.')
                : __('The radiant is highest at about :time, :alt° up in the :dir. Meteors can appear anywhere in the sky, so face a dark, open part of the sky rather than the radiant itself.', [
                    'time' => $time($viewing['highest_local']),
                    'alt' => (int) round($viewing['highest_altitude_deg']),
                    'dir' => UkMeteorViewing::direction($viewing['highest_azimuth_deg']),
                ])],
            ['question' => __('Will the Moon spoil the :label?', ['label' => $label]), 'answer' => __('At the peak the Moon is a :phase, :pct% lit. :up', [
                'pct' => (int) round($viewing['moon_illumination'] * 100),
                'phase' => UkMeteorViewing::moonPhaseName($viewing['moon_illumination'], $viewing['moon_waxing']),
                'up' => $viewing['moon_up_fraction'] <= 0.0
                    ? __('It is below the horizon for the whole best viewing time.')
                    : __('It is up for about :share% of the best viewing time.', ['share' => (int) round($viewing['moon_up_fraction'] * 100)]),
            ])],
        ];

        foreach ($extra as $item) {
            if (is_array($item) && filled($item['question'] ?? null) && filled($item['answer'] ?? null)) {
                $faq[] = ['question' => (string) $item['question'], 'answer' => (string) $item['answer']];
            }
        }

        return $faq;
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

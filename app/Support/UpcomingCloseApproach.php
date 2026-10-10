<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\SolarApi\Data\CloseApproach;
use Carbon\CarbonImmutable;

/** The soonest future Earth pass within a lunar-distance limit. */
final class UpcomingCloseApproach
{
    public const int MAX_LUNAR_DISTANCES = 10;

    public const int WINDOW_DAYS = 60;

    public const int LIMIT = 1000;

    public static function maxDistanceAu(): float
    {
        return self::MAX_LUNAR_DISTANCES * CloseApproach::AU_PER_LUNAR_DISTANCE;
    }

    /**
     * Collapse duplicate object/time rows, then keep the soonest future pass
     * still within the lunar-distance limit. The list page has its own dedupe.
     *
     * @param  list<CloseApproach>  $approaches
     */
    public static function select(array $approaches, CarbonImmutable $now): ?CloseApproach
    {
        $maxAu = self::maxDistanceAu();
        $chosen = [];

        foreach ($approaches as $approach) {
            if ($approach->body !== null && strcasecmp($approach->body, 'Earth') !== 0) {
                continue;
            }
            $when = self::instant($approach->cdIso);
            if ($when === null || $when->lessThan($now)) {
                continue;
            }
            if ($approach->distAu === null || $approach->distAu > $maxAu + 1e-9) {
                continue;
            }

            $key = ($approach->objectId ?? $approach->name ?? '').'|'.$approach->cdIso;
            $existing = $chosen[$key] ?? null;
            if ($existing === null || ($approach->distAu <=> $existing->distAu) < 0) {
                $chosen[$key] = $approach;
            }
        }

        $rows = array_values($chosen);
        usort($rows, function (CloseApproach $a, CloseApproach $b): int {
            $byTime = strcmp((string) $a->cdIso, (string) $b->cdIso);

            return $byTime !== 0 ? $byTime : ($a->distAu <=> $b->distAu);
        });

        return $rows[0] ?? null;
    }

    public static function instant(?string $cdIso): ?CarbonImmutable
    {
        if ($cdIso === null) {
            return null;
        }

        try {
            $when = CarbonImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $cdIso, 'UTC');
        } catch (\Throwable) {
            return null;
        }

        if (! $when || $when->format('Y-m-d\TH:i:s\Z') !== $cdIso) {
            return null;
        }

        return $when;
    }

    public static function utcLabel(CarbonImmutable $at): string
    {
        return $at->utc()->isoFormat('D MMMM YYYY, HH:mm').' UTC';
    }

    /** @return array{passed:string,soon:string,template:string,day:string,days:string,hour:string,hours:string,minute:string,minutes:string} */
    public static function clockStrings(): array
    {
        return [
            'passed' => __('This pass time has been reached.'),
            'soon' => __('Passes in less than a minute'),
            'template' => __('Passes in :when'),
            'day' => __(':count day'),
            'days' => __(':count days'),
            'hour' => __(':count hour'),
            'hours' => __(':count hours'),
            'minute' => __(':count minute'),
            'minutes' => __(':count minutes'),
        ];
    }

    /**
     * Whole minutes at most, so a polite live region is not rewritten every second.
     * Days-away passes omit minutes and only change when the hour changes.
     */
    public static function countdown(CarbonImmutable $now, CarbonImmutable $at): string
    {
        $strings = self::clockStrings();
        $seconds = $at->getTimestamp() - $now->getTimestamp();
        if ($seconds <= 0) {
            return $strings['passed'];
        }
        if ($seconds < 60) {
            return $strings['soon'];
        }

        $minutes = intdiv($seconds, 60);
        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $mins = $minutes % 60;
        $parts = [];
        if ($days > 0) {
            $parts[] = self::unit($days, $strings['day'], $strings['days']);
        }
        if ($hours > 0) {
            $parts[] = self::unit($hours, $strings['hour'], $strings['hours']);
        }
        if ($days === 0 && $mins > 0) {
            $parts[] = self::unit($mins, $strings['minute'], $strings['minutes']);
        }

        return str_replace(':when', implode(' ', $parts), $strings['template']);
    }

    private static function unit(int $count, string $one, string $many): string
    {
        return str_replace(':count', (string) $count, $count === 1 ? $one : $many);
    }
}

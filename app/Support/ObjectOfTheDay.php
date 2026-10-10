<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The "featured today" pick, shared by the homepage panel and the dated
 * /today/YYYY-MM-DD permalink. Deterministic per UTC day, so a shared link
 * always shows the object that was featured on that date.
 */
final class ObjectOfTheDay
{
    /** Earliest dated permalink served; older dates 404 rather than open an endless archive. */
    public const FIRST_DATE = '2026-01-01';

    /** A curated pool of well-known bodies. Reordering it changes every past permalink. */
    public const POOL = [
        'planet-mercury', 'planet-venus', 'planet-earth', 'planet-mars',
        'planet-jupiter', 'planet-saturn', 'planet-uranus', 'planet-neptune',
        'dwarf-ceres', 'dwarf-pluto', 'dwarf-eris', 'dwarf-makemake', 'dwarf-haumea',
        'moon-luna', 'moon-titan', 'moon-europa', 'moon-io', 'moon-ganymede',
        'moon-triton', 'moon-enceladus', 'moon-phobos', 'comet-1p-halley',
    ];

    /** Today's date on the application clock, in UTC. */
    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::instance(now())->utc()->startOfDay();
    }

    public static function slugFor(CarbonInterface $date): string
    {
        // Year + zero-based day-of-year: the key the homepage has always used.
        $day = $date->copy()->utc()->format('Y-z');

        return self::POOL[crc32($day) % count(self::POOL)];
    }

    /** A YYYY-MM-DD path segment, if it is a real date between FIRST_DATE and today. */
    public static function parse(string $value): ?CarbonImmutable
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $m)
            || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC');
        if ($date === null || $date->lt(CarbonImmutable::parse(self::FIRST_DATE, 'UTC')) || $date->gt(self::today())) {
            return null;
        }

        return $date;
    }

    public static function url(CarbonInterface $date): string
    {
        return route('today.show', ['date' => $date->format('Y-m-d')]);
    }

    public static function previous(CarbonImmutable $date): ?CarbonImmutable
    {
        return self::parse($date->subDay()->format('Y-m-d'));
    }

    public static function next(CarbonImmutable $date): ?CarbonImmutable
    {
        return self::parse($date->addDay()->format('Y-m-d'));
    }
}

<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Query policy for /orrery. The page's date is view state, not a new document.
 * Step links used to point at every neighbouring day, so a crawler could walk
 * the calendar from year 0001 to 9999. Only a real date inside that window is
 * kept; every other key or value is dropped instead of becoming its own URL.
 */
final class OrreryQuery
{
    /**
     * @param  array<string, mixed>  $query
     * @return array<string, string>
     */
    public static function clamp(array $query): array
    {
        $date = self::validDate($query['date'] ?? null);

        return $date === null ? [] : ['date' => $date];
    }

    /**
     * The clamped query when this request is not already that query, or null
     * when the raw query string is exactly the canonical form.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, string>|null
     */
    public static function redirectQuery(array $query, string $rawQueryString): ?array
    {
        $clamped = self::clamp($query);
        $canonical = http_build_query($clamped, '', '&', PHP_QUERY_RFC3986);
        if ($query === $clamped && $rawQueryString === $canonical) {
            return null;
        }

        return $clamped;
    }

    public static function validDate(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value)
            || (int) substr($value, 0, 4) < 1) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC');
        } catch (\Throwable) {
            return null;
        }

        return $date && $date->year <= 9999 && $date->toDateString() === $value ? $value : null;
    }
}

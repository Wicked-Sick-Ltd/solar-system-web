<?php

declare(strict_types=1);

namespace App\Services\Observing;

/** Optional refined diagnostics, scoped to one selected interval rather than chart samples. */
final class NightConstraintCoverage
{
    /** @param array<string,mixed> $constraints
     * @param  array<string,mixed>  $darkness
     * @param  list<array<string,mixed>>  $windows
     * @return array<string,mixed>
     */
    public static function validate(mixed $value, array $constraints, array $darkness, array $windows): array
    {
        $row = NightMetadata::shape($value, ['scope', 'start_utc', 'end_utc', 'altitude', 'darkness']);
        NightMetadata::require($row['scope'] === 'selected_interval'
            && $row['start_utc'] === $constraints['window_start_utc']
            && $row['end_utc'] === $constraints['window_end_utc']);
        foreach (['altitude', 'darkness'] as $key) {
            NightMetadata::require(in_array($row[$key], ['always_satisfied', 'never_satisfied', 'partial', 'unresolved'], true));
        }
        if ($row['altitude'] === 'never_satisfied' || $row['darkness'] === 'never_satisfied') {
            NightMetadata::require($windows === []);
        }
        $fullDarkness = count($darkness['intervals']) === 1
            && $darkness['intervals'][0]['start_utc'] === $row['start_utc']
            && $darkness['intervals'][0]['end_utc'] === $row['end_utc'];
        if ($row['darkness'] === 'never_satisfied') {
            NightMetadata::require($darkness['intervals'] === []);
        } elseif ($row['darkness'] === 'always_satisfied') {
            NightMetadata::require($fullDarkness);
        } elseif ($row['darkness'] === 'partial') {
            NightMetadata::require($darkness['intervals'] !== [] && ! $fullDarkness);
        }

        // An unresolved sub-second band need not change the older overall status.
        // Other constraints can also make a target unresolved while these are known.
        return $row;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Observing;

use Illuminate\Support\Arr;

/** Compact export of an already validated calculation; never recalculates it. */
final class NightSession
{
    /** @param array<string,mixed> $plan
     * @param  array<string,mixed>  $query
     * @return array<string,mixed>
     */
    public static function summary(array $plan, array $query): array
    {
        $targets = array_map(static function (array $target) use ($plan): array {
            $out = Arr::only($target, ['id', 'name', 'status']);
            $out['windows'] = array_map(static fn (array $window): array => Arr::only($window, ['start_utc', 'end_utc']), $target['windows']);
            if (array_key_exists('constraint_coverage', $target)) {
                $out['constraint_coverage'] = NightConstraintCoverage::validate($target['constraint_coverage'], $plan['constraints'], $plan['darkness'], $target['windows']);
            }
            if (isset($target['catalogue'])) {
                $out['catalogue'] = NightCatalogueProvenance::validate($target['catalogue'], $target['id']);
            }

            return $out;
        }, $plan['targets']);

        return [
            'export_schema_version' => 1,
            'kind' => 'public-universe-observing-session',
            'calculated_at_utc' => now('UTC')->format('Y-m-d\TH:i:s\Z'),
            'scope' => 'Validated calculation summary; chart samples, weather, equipment and actual observations are not included.',
            'reproducibility' => [
                'note' => 'Repeatability requires the same inputs, source snapshots, ephemeris, Earth-orientation table and software versions. A moving API is not an immutable archive.',
                'catalogue_identity' => 'Per-target source snapshots are recorded when supplied. No global database identity was attached atomically to this calculation.',
            ],
            'units' => ['angles' => 'degrees', 'elevation' => 'metres', 'times' => 'UTC ISO 8601 seconds', 'duration' => 'hours', 'proper_motion' => 'arcseconds per year', 'reference_epoch' => 'Julian year'],
            'input' => Arr::only($query, ['date', 'timezone', 'lat', 'lon', 'targets', 'min_altitude_deg', 'sun_altitude_deg', 'min_moon_separation_deg', 'window_start_utc', 'window_end_utc', 'horizon_mask']),
            'night' => Arr::only($plan['night'], ['date', 'start_utc', 'end_utc', 'duration_hours']),
            'constraints' => Arr::only($plan['constraints'], ['min_altitude_deg', 'sun_altitude_deg', 'min_moon_separation_deg', 'min_sun_separation_deg', 'moon_separation_rule', 'horizon_rule', 'window_start_utc', 'window_end_utc', 'horizon_mask']),
            'darkness' => ['status' => $plan['darkness']['status'], 'intervals' => array_map(static fn (array $window): array => Arr::only($window, ['start_utc', 'end_utc']), $plan['darkness']['intervals'])],
            'moon' => Arr::only($plan['moon'], ['illumination_fraction', 'phase_angle_deg', 'elongation_deg', 'definition', 'reference_utc']),
            'method' => NightProviderProvenance::validate($plan['method']),
            'targets' => $targets,
        ];
    }
}

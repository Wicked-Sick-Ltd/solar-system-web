<?php

declare(strict_types=1);

namespace App\Services\Observing;

/** Binds bounded screening explanations to the requested geometry and validated refined plan. */
final class ShortlistPlan
{
    public const REASONS = [
        'naked_eye' => 'naked_eye_moon_then_bright_star_preference',
        'binocular' => 'binocular_known_field_extent_then_deep_sky_preference',
        'telescope' => 'telescope_solar_system_then_double_star_preference',
    ];

    /** @param array<string,mixed> $request
     * @return array<string,mixed>
     */
    public static function validate(mixed $value, array $request): array
    {
        $data = NightMetadata::shape($value, ['schema_version', 'request', 'discovery', 'candidates', 'plan', 'method']);
        NightMetadata::require($data['schema_version'] === 1);
        $geometry = array_diff_key($request, array_flip(ShortlistRequest::OPTION_FIELDS));
        [$geometry['window_start_utc'], $geometry['window_end_utc']] = NightConstraints::window($request);
        $returned = NightMetadata::shape($data['request'], array_keys($geometry));
        foreach ($geometry as $key => $expected) {
            if (is_float($expected) || is_int($expected)) {
                NightMetadata::require(NightMetadata::number($returned[$key]) === (float) $expected);
            } else {
                NightMetadata::require($returned[$key] === $expected || ($key === 'horizon_mask' && self::sameMask($returned[$key], $expected)));
            }
        }
        $method = NightProviderProvenance::validate($data['method']);
        NightMetadata::require(isset($method['calculation']));
        $night = $request;
        $night['window_start_utc'] = $night['window_end_utc'] = null;
        [$start, $end] = NightConstraints::window($night);
        NightMetadata::require(NightMetadata::utc($method['iers']['start_utc']) <= NightMetadata::utc($start)
            && NightMetadata::utc($method['iers']['end_utc']) >= NightMetadata::utc($end));

        $screen = NightMetadata::shape($data['discovery'], ['options', 'scope', 'catalogue_records', 'solar_system_targets',
            'unsupported_catalogue_records', 'coarse_step_seconds', 'sample_count', 'incomplete_between_samples',
            'coarse_matching_candidates', 'refined_candidates', 'selected_without_refined_window', 'brightness_excluded',
            'unknown_or_non_v_brightness_excluded', 'ranking_policy', 'limitations', 'source_snapshots', 'calculation', 'planner_calculation']);
        $options = NightMetadata::shape($screen['options'], ShortlistRequest::OPTION_FIELDS);
        foreach ($options as $key => $option) {
            $expected = $request[$key];
            if (in_array($key, ['true_field_deg', 'max_catalogue_v_magnitude'], true) && $expected !== null) {
                NightMetadata::require(NightMetadata::number($option) === (float) $expected);
            } else {
                NightMetadata::require($option === $expected);
            }
        }
        NightMetadata::require($screen['incomplete_between_samples'] === true && $screen['coarse_step_seconds'] === 1200);
        self::integer($screen['catalogue_records'], 1, 158);
        NightMetadata::require($screen['solar_system_targets'] === 8);
        self::integer($screen['unsupported_catalogue_records'], 0, 158);
        NightMetadata::require($screen['catalogue_records'] + $screen['unsupported_catalogue_records'] <= 158);
        $pool = $screen['catalogue_records'] + 8;
        foreach (['coarse_matching_candidates', 'brightness_excluded', 'unknown_or_non_v_brightness_excluded'] as $key) {
            self::integer($screen[$key], 0, $pool);
        }
        NightMetadata::require($pool >= $screen['coarse_matching_candidates'] + $screen['brightness_excluded'] + $screen['unknown_or_non_v_brightness_excluded']);
        if ($options['max_catalogue_v_magnitude'] === null) {
            NightMetadata::require($screen['brightness_excluded'] === 0 && $screen['unknown_or_non_v_brightness_excluded'] === 0);
        }
        $samples = max(3, (int) ceil((NightMetadata::utc($geometry['window_end_utc']) - NightMetadata::utc($geometry['window_start_utc'])) / 1200) + 1);
        NightMetadata::require($screen['sample_count'] === $samples);
        self::integer($screen['refined_candidates'], 0, 8);
        self::integer($screen['selected_without_refined_window'], 0, $screen['refined_candidates']);
        NightMetadata::require($screen['refined_candidates'] === min($screen['coarse_matching_candidates'], $options['shortlist_limit']));
        NightMetadata::text($screen['scope']);
        NightMetadata::text($screen['ranking_policy']);
        self::texts($screen['limitations'], 1, 10);
        $sources = self::sources($screen['source_snapshots']);
        $identity = NightMetadata::shape($screen['calculation'], ['algorithm', 'source_sha256', 'files']);
        NightMetadata::require($identity['algorithm'] === 'observing-discovery-sources-sha256-v1'
            && $identity['files'] === ['observing/discovery.py', 'observing/discovery_worker.py']);
        NightMetadata::hash($identity['source_sha256']);
        NightMetadata::require($screen['planner_calculation'] === $method['calculation']);

        $candidates = $data['candidates'];
        NightMetadata::require(is_array($candidates) && array_is_list($candidates)
            && count($candidates) === $screen['refined_candidates'] - $screen['selected_without_refined_window']);
        if ($screen['refined_candidates'] === 0) {
            NightMetadata::require($data['plan'] === null);

            return $data;
        }
        NightMetadata::require(is_array($data['plan']) && is_array($data['plan']['targets'] ?? null)
            && array_is_list($data['plan']['targets']) && count($data['plan']['targets']) === $screen['refined_candidates']);
        $ids = [];
        foreach ($data['plan']['targets'] as $target) {
            NightMetadata::require(is_array($target) && NightTargets::isSupportedId($target['id'] ?? null)
                && ! in_array($target['id'], $ids, true));
            $ids[] = $target['id'];
        }
        $plan = NightPlan::validate($data['plan'], $geometry + ['targets' => implode(',', $ids)]);
        NightMetadata::require($plan['method'] === $method);
        $useful = [];
        foreach ($plan['targets'] as $target) {
            if (isset($target['catalogue'])) {
                $source = $sources[$target['catalogue']['source']] ?? null;
                NightMetadata::require(is_array($source));
                foreach ($source as $key => $field) {
                    NightMetadata::require($target['catalogue'][$key] === $field);
                }
            }
            if ($target['windows'] !== []) {
                $useful[] = $target;
            }
        }
        NightMetadata::require(count($useful) === count($candidates));
        foreach ($candidates as $index => $candidate) {
            $candidate = NightMetadata::shape($candidate, ['id', 'name', 'aliases', 'preference_reasons', 'field_context',
                'coarse_matching_samples', 'sampled_peak_altitude_deg', 'appearance', 'brightness_status', 'refined_status']);
            $target = $useful[$index];
            NightMetadata::require($candidate['id'] === $target['id'] && $candidate['name'] === $target['name'] && $candidate['refined_status'] === $target['status']);
            self::texts($candidate['aliases'], 0, 64, 300);
            NightMetadata::require($candidate['preference_reasons'] === ['requested_preference_'.$options['preference'], self::REASONS[$options['equipment_mode']], 'more_matching_sample_instants_then_stable_id']);
            self::integer($candidate['coarse_matching_samples'], 1, $samples);
            NightMetadata::number($candidate['sampled_peak_altitude_deg'], $request['min_altitude_deg'], 90);
            $appearance = $target['catalogue']['appearance'] ?? null;
            NightMetadata::require($candidate['appearance'] === $appearance);
            $magnitude = $appearance['magnitude'] ?? null;
            NightMetadata::require($candidate['brightness_status'] === ($magnitude === null ? 'unknown' : 'catalogue_value'));
            if ($options['max_catalogue_v_magnitude'] !== null) {
                $band = $appearance['magnitude_band'] ?? null;
                NightMetadata::require($magnitude !== null && $magnitude <= $options['max_catalogue_v_magnitude']
                    && is_string($band) && ($band === 'V' || str_starts_with($band, 'V (')));
            }
            $extent = $appearance !== null && in_array('deep_sky', $appearance['families'], true) ? $appearance['major_axis_arcmin'] : null;
            $context = $extent === null || $extent <= 0 ? 'unknown_angular_extent'
                : ($options['true_field_deg'] === null ? 'field_not_supplied'
                    : ($extent / 60 <= $options['true_field_deg'] ? 'catalogue_extent_within_field' : 'catalogue_extent_exceeds_field'));
            NightMetadata::require($candidate['field_context'] === $context);
        }

        return $data;
    }

    private static function integer(mixed $value, int $min, int $max): void
    {
        NightMetadata::require(is_int($value) && $value >= $min && $value <= $max);
    }

    private static function texts(mixed $value, int $min, int $max, int $length = 2000): void
    {
        NightMetadata::require(is_array($value) && array_is_list($value) && count($value) >= $min && count($value) <= $max);
        foreach ($value as $text) {
            NightMetadata::text($text, $length);
        }
    }

    private static function sameMask(mixed $value, mixed $expected): bool
    {
        if (! is_array($value) || ! is_array($expected) || ! array_is_list($value) || count($value) !== count($expected)) {
            return false;
        }
        foreach ($value as $index => $point) {
            $point = NightMetadata::shape($point, ['azimuth_deg', 'min_altitude_deg']);
            foreach ($point as $key => $number) {
                if (NightMetadata::number($number) !== (float) $expected[$index][$key]) {
                    return false;
                }
            }
        }

        return true;
    }

    /** @return array<string,array<string,mixed>> */
    private static function sources(mixed $value): array
    {
        NightMetadata::require(is_array($value) && array_is_list($value) && count($value) === 2);
        $sources = [];
        foreach ($value as $row) {
            $row = NightMetadata::shape($row, ['source', 'snapshot_sha256', 'upstream_sha256', 'source_url', 'retrieved_at', 'license', 'attribution', 'license_url']);
            NightMetadata::require(in_array($row['source'], ['bsc5p', 'openngc'], true) && ! isset($sources[$row['source']]));
            NightMetadata::hash($row['snapshot_sha256']);
            $hashes = NightMetadata::shape($row['upstream_sha256'], $row['source'] === 'bsc5p' ? ['bsc5p.tdat'] : ['NGC.csv', 'addendum.csv']);
            foreach ($hashes as $hash) {
                NightMetadata::hash($hash);
            }
            NightMetadata::url($row['source_url']);
            NightMetadata::url($row['license_url']);
            NightMetadata::retrieval($row['retrieved_at']);
            NightMetadata::text($row['license']);
            NightMetadata::text($row['attribution']);
            $sources[$row['source']] = $row;
        }

        return $sources;
    }
}

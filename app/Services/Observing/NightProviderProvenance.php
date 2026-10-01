<?php

declare(strict_types=1);

namespace App\Services\Observing;

/** Preserve explicit offline provider identity without pinning library releases in the frontend. */
final class NightProviderProvenance
{
    /** @return array<string,mixed> */
    public static function validate(mixed $value): array
    {
        NightMetadata::require(is_array($value) && in_array($value['provider'] ?? null, ['astropy-builtin', 'jpl-de440s'], true));
        $jpl = $value['provider'] === 'jpl-de440s';
        $keys = ['provider', 'ephemeris', 'astropy_version', 'erfa_version', 'frame', 'refraction', 'accuracy_note',
            'iers', 'sample_minutes', 'root_tolerance_seconds', 'window_note', ...(array_key_exists('calculation', $value) ? ['calculation'] : [])];
        $data = NightMetadata::shape($value, $jpl ? [...$keys, 'jplephem_version', 'kernel'] : $keys);
        NightMetadata::require($data['ephemeris'] === ($jpl ? 'JPL DE440s' : 'ERFA builtin'));
        foreach (['astropy_version', 'erfa_version'] as $field) {
            NightMetadata::version($data[$field]);
        }
        foreach (['frame', 'refraction', 'accuracy_note', 'window_note'] as $field) {
            NightMetadata::text($data[$field]);
        }
        NightMetadata::number($data['sample_minutes'], 0.1, 5);
        NightMetadata::number($data['root_tolerance_seconds'], 0.001, 60);
        $iers = NightMetadata::shape($data['iers'], ['start_utc', 'end_utc', 'data_version', 'snapshot', 'status']);
        NightMetadata::require(NightMetadata::utc($iers['start_utc']) < NightMetadata::utc($iers['end_utc']));
        NightMetadata::version($iers['data_version']);
        NightMetadata::require(in_array($iers['status'], ['measured', 'predicted'], true));
        $snapshot = NightMetadata::shape($iers['snapshot'], ['sha256', 'algorithm', 'columns']);
        NightMetadata::hash($snapshot['sha256']);
        NightMetadata::require($snapshot['algorithm'] === 'effective-iers-columns-le-f64-v1'
            && $snapshot['columns'] === ['MJD', 'UT1_UTC', 'PM_x', 'PM_y', 'UT1Flag', 'PolPMFlag']);
        if ($jpl) {
            NightMetadata::version($data['jplephem_version']);
            $kernel = NightMetadata::shape($data['kernel'], ['name', 'sha256', 'size_bytes', 'source_url', 'start_tdb', 'end_tdb']);
            NightMetadata::require($kernel['name'] === 'de440s.bsp' && is_int($kernel['size_bytes']) && $kernel['size_bytes'] > 0 && $kernel['size_bytes'] <= 1000000000);
            NightMetadata::hash($kernel['sha256']);
            NightMetadata::url($kernel['source_url']);
            NightMetadata::require(NightMetadata::tdb($kernel['start_tdb']) < NightMetadata::tdb($kernel['end_tdb']));
        }

        if (array_key_exists('calculation', $data)) {
            $calculation = NightMetadata::shape($data['calculation'], ['algorithm', 'source_sha256', 'files', 'python_version', 'numpy_version', 'catalogue_scope']);
            NightMetadata::require($calculation['algorithm'] === 'observing-source-files-sha256-v1'
                && $calculation['catalogue_scope'] === 'packaged-target-snapshots; SQLite catalogue not consulted');
            NightMetadata::hash($calculation['source_sha256']);
            NightMetadata::version($calculation['python_version']);
            NightMetadata::version($calculation['numpy_version']);
            NightMetadata::require(is_array($calculation['files']) && array_is_list($calculation['files'])
                && count($calculation['files']) >= 1 && count($calculation['files']) <= 32);
            $previous = null;
            foreach ($calculation['files'] as $file) {
                NightMetadata::require(is_string($file) && strlen($file) <= 100
                    && preg_match('/^(?:observing\/[a-z_]+|starter_astrometry|starter_catalogues)\.py$/D', $file) === 1
                    && ($previous === null || strcmp($previous, $file) < 0));
                $previous = $file;
            }
        }

        return $data;
    }
}

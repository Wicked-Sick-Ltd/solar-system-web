<?php

declare(strict_types=1);

namespace App\Services\SolarApi\Data;

use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Support\Format;

final readonly class StarterAstrometry
{
    /** @param array<string,mixed> $data */
    private function __construct(public array $data) {}

    /** @param array<string,mixed> $row */
    public static function fromTarget(array $row, StarterSource $source): ?self
    {
        $evidence = $source->data['astrometry_evidence'] ?? null;
        if (! array_key_exists('astrometry', $row)) {
            if ($evidence !== null) {
                throw new SolarApiException('Missing starter coordinate interpretation.');
            }

            return null; // Older catalogues remain browsable without inferred frames.
        }
        $data = $row['astrometry'];
        if (! is_array($data) || $evidence === null || ! in_array($data['status'] ?? null, ['verified', 'unsupported'], true)
            || ($data['coordinates_propagated'] ?? null) !== false || ($data['evidence_source'] ?? null) !== $row['source']) {
            throw new SolarApiException('Malformed starter coordinate interpretation.');
        }
        foreach (['frame', 'equinox', 'reference_epoch_jyear', 'observation_epoch_jyear', 'pm_ra_cosdec_arcsec_per_year', 'pm_dec_arcsec_per_year', 'motion_model', 'matched_identifier', 'unsupported_reason'] as $field) {
            if (! array_key_exists($field, $data)) {
                throw new SolarApiException('Incomplete starter coordinate interpretation.');
            }
        }
        $bright = $row['source'] === 'bsc5p';
        $identity = $row['source_data'][$bright ? 'hr' : 'Name'] ?? null;
        if (! is_string($identity) || $row['id'] !== $row['source'].':'.($bright ? 'hr' : '').$identity) {
            throw new SolarApiException('Inconsistent coordinate evidence identity.');
        }
        if ($data['status'] === 'unsupported') {
            foreach (['frame', 'equinox', 'reference_epoch_jyear', 'observation_epoch_jyear', 'pm_ra_cosdec_arcsec_per_year', 'pm_dec_arcsec_per_year', 'motion_model', 'matched_identifier'] as $field) {
                if ($data[$field] !== null) {
                    throw new SolarApiException('Unsupported coordinate interpretation contains measurements.');
                }
            }
            if (! is_string($data['unsupported_reason']) || trim($data['unsupported_reason']) === ''
                || ! in_array($identity, $evidence['unsupported_identifiers'], true)) {
                throw new SolarApiException('Missing unsupported-coordinate explanation.');
            }

            return new self($data);
        }
        if ($data['frame'] !== ($bright ? 'FK5' : 'ICRS') || $data['equinox'] !== ($bright ? 'J2000.0' : null)
            || (! is_int($data['reference_epoch_jyear']) && ! is_float($data['reference_epoch_jyear']))
            || (float) $data['reference_epoch_jyear'] !== 2000.0 || $data['observation_epoch_jyear'] !== null
            || $data['matched_identifier'] !== $identity || $data['unsupported_reason'] !== null
            || in_array($identity, $evidence['unsupported_identifiers'], true)) {
            throw new SolarApiException('Inconsistent starter coordinate frame or epoch.');
        }
        foreach (['pm_ra_cosdec_arcsec_per_year', 'pm_dec_arcsec_per_year'] as $field) {
            $value = $data[$field];
            if ($value !== null && ((! is_int($value) && ! is_float($value)) || ! is_finite($value))) {
                throw new SolarApiException('Invalid proper motion.');
            }
            if ($bright) {
                $raw = $row['source_data'][$field === 'pm_ra_cosdec_arcsec_per_year' ? 'pmra' : 'pmdec'] ?? null;
                if (! is_string($raw) || ($raw === '' ? $value !== null
                    : (! is_numeric($raw) || ! is_finite((float) $raw) || $value === null || (float) $raw !== (float) $value))) {
                    throw new SolarApiException('Proper motion disagrees with the original source field.');
                }
            }
            if (! $bright && $value !== null) {
                throw new SolarApiException('Unsupported deep-sky motion interpretation.');
            }
        }
        $motion = $bright && $data['pm_ra_cosdec_arcsec_per_year'] !== null && $data['pm_dec_arcsec_per_year'] !== null;
        if ($data['motion_model'] !== ($motion ? 'linear_angular_proper_motion' : 'static_catalogue_direction')) {
            throw new SolarApiException('Inconsistent starter motion interpretation.');
        }

        return new self($data);
    }

    public function properMotion(string $field): string
    {
        $value = $this->data[$field];

        return $value === null ? __('Not reported') : Format::scientific((float) $value, 6).' arcsec/year';
    }
}

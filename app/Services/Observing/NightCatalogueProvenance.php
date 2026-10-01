<?php

declare(strict_types=1);

namespace App\Services\Observing;

/** A calculated direction's provenance, distinct from an unpropagated catalogue row. */
final class NightCatalogueProvenance
{
    /** @return array<string,mixed> */
    public static function validate(mixed $value, string $targetId): array
    {
        NightMetadata::require(NightTargets::isCatalogueId($targetId));
        $data = NightMetadata::shape($value, ['source', 'snapshot_sha256', 'upstream_sha256', 'source_url', 'retrieved_at',
            'license', 'attribution', 'license_url', 'astrometry_evidence', 'input_coordinates', 'motion_model',
            'proper_motion_applied', 'frame_transform', 'pm_ra_cosdec_arcsec_per_year', 'pm_dec_arcsec_per_year',
            'distance_au', 'refraction', 'accuracy_note']);
        $bright = str_starts_with($targetId, 'bsc5p:');
        NightMetadata::require($data['source'] === ($bright ? 'bsc5p' : 'openngc'));
        NightMetadata::hash($data['snapshot_sha256']);
        $hashes = NightMetadata::shape($data['upstream_sha256'], $bright ? ['bsc5p.tdat'] : ['NGC.csv', 'addendum.csv']);
        foreach ($hashes as $hash) {
            NightMetadata::hash($hash);
        }
        foreach (['source_url', 'license_url'] as $field) {
            NightMetadata::url($data[$field]);
        }
        foreach (['license', 'attribution', 'frame_transform', 'refraction', 'accuracy_note'] as $field) {
            NightMetadata::text($data[$field]);
        }
        NightMetadata::retrieval($data['retrieved_at']);
        $evidence = NightMetadata::shape($data['astrometry_evidence'], ['file', 'authority', 'dataset', 'query_url',
            'response_sha256', 'matched_records', 'unsupported_identifiers', 'retrieved_at']);
        NightMetadata::text($evidence['authority'], 300);
        NightMetadata::require($evidence['dataset'] === ($bright ? 'V/50/catalog' : 'openngc.data'));
        NightMetadata::require(is_string($evidence['file']) && preg_match('/^[A-Za-z0-9_.-]{1,100}$/D', $evidence['file']) === 1);
        NightMetadata::url($evidence['query_url']);
        NightMetadata::hash($evidence['response_sha256']);
        NightMetadata::date($evidence['retrieved_at']);
        NightMetadata::require(is_int($evidence['matched_records']) && $evidence['matched_records'] > 0 && $evidence['matched_records'] <= 10000);
        NightMetadata::require(is_array($evidence['unsupported_identifiers']) && array_is_list($evidence['unsupported_identifiers']) && count($evidence['unsupported_identifiers']) <= 1000);
        $seen = [];
        $sourceId = substr($targetId, $bright ? strlen('bsc5p:hr') : strlen('openngc:'));
        foreach ($evidence['unsupported_identifiers'] as $id) {
            $id = NightMetadata::text($id, 50);
            NightMetadata::require(! isset($seen[$id]) && $sourceId !== $id);
            $seen[$id] = true;
        }
        $coordinates = NightMetadata::shape($data['input_coordinates'], ['ra_deg', 'dec_deg', 'frame', 'equinox', 'reference_epoch_jyear', 'observation_epoch_jyear']);
        NightMetadata::number($coordinates['ra_deg'], 0, 360);
        NightMetadata::require($coordinates['ra_deg'] < 360);
        NightMetadata::number($coordinates['dec_deg'], -90, 90);
        NightMetadata::require($coordinates['frame'] === ($bright ? 'FK5' : 'ICRS') && $coordinates['equinox'] === ($bright ? 'J2000.0' : null));
        NightMetadata::number($coordinates['reference_epoch_jyear'], 2000, 2000);
        NightMetadata::require($coordinates['observation_epoch_jyear'] === null && $data['distance_au'] === null);
        NightMetadata::require($data['motion_model'] === ($bright ? 'linear_angular_proper_motion' : 'static_catalogue_direction')
            && $data['proper_motion_applied'] === $bright);
        foreach (['pm_ra_cosdec_arcsec_per_year', 'pm_dec_arcsec_per_year'] as $field) {
            if ($bright) {
                NightMetadata::number($data[$field]);
            } else {
                NightMetadata::require($data[$field] === null);
            }
        }

        return $data;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Observing;

use App\Services\SolarApi\Data\StarterTarget;
use App\Services\SolarApi\Exceptions\SolarApiException;
use Illuminate\Validation\ValidationException;

/** Exact request syntax; membership of the pinned sample remains authoritative upstream. */
final class NightTargets
{
    public const array BODIES = ['moon', 'mercury', 'venus', 'mars', 'jupiter', 'saturn', 'uranus', 'neptune'];

    public static function isCatalogueId(mixed $id): bool
    {
        return is_string($id) && preg_match('/^(?:bsc5p:hr[1-9][0-9]{0,3}|openngc:[A-Za-z0-9]{1,30})$/D', $id) === 1;
    }

    public static function isSupportedId(mixed $id): bool
    {
        return in_array($id, self::BODIES, true) || self::isCatalogueId($id);
    }

    /** @return list<string> */
    public static function parse(mixed $targets): array
    {
        if (! is_array($targets) || ! array_is_list($targets) || count($targets) < 1 || count($targets) > 8) {
            throw ValidationException::withMessages(['targets' => __('Choose between one and eight Moon, planet or exact catalogue identifiers.')]);
        }
        $seen = [];
        foreach ($targets as $index => $id) {
            if (! self::isSupportedId($id) || isset($seen[$id])) {
                throw ValidationException::withMessages(['targets.'.$index => __('Use distinct supported body names or exact bsc5p:/openngc: identifiers. Names are case-sensitive; Earth and the Sun are excluded.')]);
            }
            $seen[$id] = true;
        }

        return $targets;
    }

    /** GET hints select fields only: never fetch coordinates, calculate or submit.
     * @param  array<string,mixed>  $query
     * @return list<string>
     */
    public static function hints(array $query): array
    {
        return array_key_exists('targets', $query) ? self::parse($query['targets']) : [];
    }

    /** @return array{catalogue:string,id:string} */
    public static function journalIdentity(string $id): array
    {
        if (! self::isSupportedId($id)) {
            throw new SolarApiException('The calculated target identity is unsupported.');
        }

        return self::isCatalogueId($id)
            ? ['catalogue' => 'starter', 'id' => $id]
            : ['catalogue' => 'solar', 'id' => $id === 'moon' ? 'moon-luna' : 'planet-'.$id];
    }

    /** Requires an explicit null for catalogue distances; absence is not null.
     * @param  array<string,mixed>  $sample
     */
    public static function distance(string $id, array $sample): ?float
    {
        if (! self::isSupportedId($id) || ! array_key_exists('distance_au', $sample)) {
            throw new SolarApiException('The calculated distance field is missing.');
        }
        if (self::isCatalogueId($id)) {
            if ($sample['distance_au'] !== null) {
                throw new SolarApiException('A physical distance was supplied for an angular-only target.');
            }

            return null;
        }

        return NightMetadata::number($sample['distance_au'], 0.000001, 1000);
    }

    /** Older or incomplete catalogue records stay browsable without a planning promise. */
    public static function canPlan(StarterTarget $target): bool
    {
        $data = $target->astrometry?->data;
        if (! self::isCatalogueId($target->id) || $data === null || $data['status'] !== 'verified') {
            return false;
        }
        if ($target->source === 'bsc5p') {
            return $data['frame'] === 'FK5' && $data['motion_model'] === 'linear_angular_proper_motion'
                && $data['pm_ra_cosdec_arcsec_per_year'] !== null && $data['pm_dec_arcsec_per_year'] !== null;
        }

        return $data['frame'] === 'ICRS' && $data['motion_model'] === 'static_catalogue_direction';
    }
}

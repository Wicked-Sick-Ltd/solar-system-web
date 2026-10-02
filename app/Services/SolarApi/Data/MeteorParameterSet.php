<?php

declare(strict_types=1);

namespace App\Services\SolarApi\Data;

use App\Services\SolarApi\Data\Concerns\CastsValues;

/** One observation campaign/parameter set, not one distinct shower. */
final readonly class MeteorParameterSet
{
    use CastsValues;

    /** @param array<string, float|null> $measurements */
    public function __construct(
        public int $iauNo,
        public int $adNo,
        public string $code,
        public string $name,
        public ?int $statusCode,
        public ?string $statusLabel,
        public ?string $activity,
        public ?string $parentBody,
        public ?string $parentObjectId,
        public ?string $showerGroup,
        public ?int $members,
        public ?string $technique,
        public ?string $reference,
        public ?string $submittedOn,
        public ?string $source,
        public array $measurements,
    ) {}

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        $measurements = [];
        foreach (['solar_longitude_deg', 'ra_deg', 'dec_deg', 'dra_deg_per_day', 'ddec_deg_per_day',
            'vg_km_s', 'a_au', 'q_au', 'e', 'peri_deg', 'node_deg', 'incl_deg'] as $field) {
            $measurements[$field] = self::float($data, $field);
        }

        return new self((int) $data['iau_no'], (int) $data['ad_no'], (string) $data['code'],
            (string) $data['name'], self::int($data, 'status_code'), self::str($data, 'status_label'),
            self::str($data, 'activity'), self::str($data, 'parent_body'), self::str($data, 'parent_object_id'),
            self::str($data, 'shower_group'), self::int($data, 'n_members'), self::str($data, 'technique'),
            self::str($data, 'reference'), self::str($data, 'submitted_on'), self::str($data, 'source'), $measurements);
    }
}

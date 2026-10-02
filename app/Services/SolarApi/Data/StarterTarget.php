<?php

declare(strict_types=1);

namespace App\Services\SolarApi\Data;

use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Support\Format;

final readonly class StarterTarget
{
    public const array FAMILIES = ['bright_star' => 'Bright stars', 'double_star' => 'Double stars', 'deep_sky' => 'Deep sky'];

    /**
     * @param  list<string>  $aliases
     * @param  list<string>  $families
     * @param  array<string,mixed>  $data
     */
    private function __construct(public string $id, public string $name, public array $aliases, public array $families,
        public string $source, public array $data, public StarterSource $provenance) {}

    public static function fromArray(mixed $row, StarterSource $provenance): self
    {
        if (! is_array($row)) {
            throw new SolarApiException('Malformed starter target.');
        }
        foreach (['id', 'name', 'source', 'object_type', 'magnitude_band'] as $field) {
            if (! is_string($row[$field] ?? null) || trim($row[$field]) === '') {
                throw new SolarApiException('Missing starter target identity.');
            }
        }
        if (! preg_match('/^(bsc5p:hr[1-9][0-9]*|openngc:[A-Za-z0-9-]+)$/D', $row['id'])
            || ! str_starts_with($row['id'], $row['source'].':') || $row['source'] !== $provenance->data['source']) {
            throw new SolarApiException('Inconsistent starter target identity.');
        }
        foreach (['families', 'aliases'] as $field) {
            if (! is_array($row[$field] ?? null) || ! array_is_list($row[$field])) {
                throw new SolarApiException('Malformed starter target labels.');
            }
            foreach ($row[$field] as $label) {
                if (! is_string($label) || trim($label) === '') {
                    throw new SolarApiException('Malformed starter target label.');
                }
            }
        }
        if ($row['families'] === [] || array_diff($row['families'], array_keys(self::FAMILIES)) !== []) {
            throw new SolarApiException('Unsupported starter target family.');
        }
        foreach (['ra_deg', 'dec_deg', 'magnitude', 'major_axis_arcmin', 'minor_axis_arcmin', 'separation_arcsec', 'position_angle_deg'] as $field) {
            if (! array_key_exists($field, $row)) {
                throw new SolarApiException('Missing starter measurement field.');
            }
            $value = $row[$field];
            if ($value !== null && ((! is_int($value) && ! is_float($value)) || ! is_finite($value))) {
                throw new SolarApiException('Malformed starter measurement.');
            }
        }
        if ($row['ra_deg'] === null || $row['ra_deg'] < 0 || $row['ra_deg'] >= 360
            || $row['dec_deg'] === null || abs($row['dec_deg']) > 90) {
            throw new SolarApiException('Invalid starter catalogue coordinates.');
        }
        foreach (['major_axis_arcmin', 'minor_axis_arcmin', 'separation_arcsec', 'position_angle_deg'] as $field) {
            if ($row[$field] !== null && $row[$field] < 0) {
                throw new SolarApiException('Invalid starter measurement range.');
            }
        }
        if ($row['position_angle_deg'] !== null && $row['position_angle_deg'] >= 360) {
            throw new SolarApiException('Invalid starter position angle.');
        }
        foreach (['coordinate_equinox', 'coordinate_epoch', 'magnitude_flag', 'magnitude_code', 'components', 'separation_epoch'] as $field) {
            if (! array_key_exists($field, $row) || ($row[$field] !== null && ! is_string($row[$field]))) {
                throw new SolarApiException('Malformed starter measurement context.');
            }
        }
        if (in_array('double_star', $row['families'], true)
            && (empty($row['components']) || $row['separation_arcsec'] === null || $row['separation_arcsec'] <= 0)) {
            throw new SolarApiException('Missing double-star component measurement.');
        }
        if (! is_array($row['source_data'] ?? null) || $row['source_data'] === [] || array_is_list($row['source_data'])) {
            throw new SolarApiException('Missing original starter catalogue row.');
        }
        foreach ($row['source_data'] as $key => $value) {
            if (! is_string($key) || ! is_string($value)) {
                throw new SolarApiException('Malformed original starter catalogue value.');
            }
        }

        return new self($row['id'], $row['name'], $row['aliases'], $row['families'], $row['source'], $row, $provenance);
    }

    public function measurement(string $field, string $unit = ''): string
    {
        $value = $this->data[$field] ?? null;

        return $value === null ? __('Not reported') : trim(Format::scientific((float) $value, 6).' '.$unit);
    }
}

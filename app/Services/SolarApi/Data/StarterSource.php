<?php

declare(strict_types=1);

namespace App\Services\SolarApi\Data;

use App\Services\SolarApi\Exceptions\SolarApiException;

final readonly class StarterSource
{
    /** @param array<string,mixed> $data */
    public function __construct(public array $data) {}

    public static function fromArray(mixed $data, string $source): self
    {
        if (! is_array($data) || ($data['source'] ?? null) !== $source) {
            throw new SolarApiException('Missing starter catalogue provenance.');
        }
        foreach (['license', 'attribution', 'selection', 'retrieved_at', 'snapshot_sha256'] as $field) {
            if (! is_string($data[$field] ?? null) || trim($data[$field]) === '') {
                throw new SolarApiException('Malformed starter catalogue provenance.');
            }
        }
        foreach (['source_url', 'license_url'] as $field) {
            $value = $data[$field] ?? null;
            if (! is_string($value) || ! filter_var($value, FILTER_VALIDATE_URL)
                || parse_url($value, PHP_URL_SCHEME) !== 'https' || parse_url($value, PHP_URL_USER) !== null
                || parse_url($value, PHP_URL_PASS) !== null) {
                throw new SolarApiException('Malformed starter catalogue source link.');
            }
        }
        if (! preg_match('/^[a-f0-9]{64}$/D', $data['snapshot_sha256'])
            || ! is_array($data['upstream_sha256'] ?? null) || $data['upstream_sha256'] === []) {
            throw new SolarApiException('Missing starter catalogue snapshot identity.');
        }
        foreach ($data['upstream_sha256'] as $name => $hash) {
            if (! is_string($name) || ! is_string($hash) || ! preg_match('/^[a-f0-9]{64}$/D', $hash)) {
                throw new SolarApiException('Malformed upstream snapshot identity.');
            }
        }

        if (array_key_exists('astrometry_evidence', $data)) {
            self::validateEvidence($data['astrometry_evidence'], $source);
        }

        return new self($data);
    }

    private static function validateEvidence(mixed $evidence, string $source): void
    {
        if (! is_array($evidence)) {
            throw new SolarApiException('Malformed coordinate-frame evidence.');
        }
        foreach (['authority', 'dataset', 'file', 'retrieved_at'] as $field) {
            if (! is_string($evidence[$field] ?? null) || trim($evidence[$field]) === '') {
                throw new SolarApiException('Missing coordinate-frame evidence.');
            }
        }
        $url = $evidence['query_url'] ?? null;
        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)
            || parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_USER) !== null
            || parse_url($url, PHP_URL_PASS) !== null
            || ! is_string($evidence['response_sha256'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/D', $evidence['response_sha256'])
            || ! is_int($evidence['matched_records'] ?? null) || $evidence['matched_records'] !== ($source === 'bsc5p' ? 50 : 107)
            || ! is_array($evidence['unsupported_identifiers'] ?? null) || ! array_is_list($evidence['unsupported_identifiers'])) {
            throw new SolarApiException('Invalid coordinate-frame evidence identity.');
        }
        if ($evidence['dataset'] !== ($source === 'bsc5p' ? 'V/50/catalog' : 'openngc.data')
            || $evidence['unsupported_identifiers'] !== ($source === 'bsc5p' ? [] : ['Mel022'])) {
            throw new SolarApiException('Unsupported coordinate-frame evidence coverage.');
        }
    }
}

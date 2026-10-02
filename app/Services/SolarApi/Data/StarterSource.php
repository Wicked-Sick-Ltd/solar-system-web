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

        return new self($data);
    }
}

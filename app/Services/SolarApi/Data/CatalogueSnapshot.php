<?php

declare(strict_types=1);

namespace App\Services\SolarApi\Data;

use App\Services\SolarApi\Exceptions\SolarApiException;

/** Identity reported alongside one page's rows in the backend read transaction. */
final readonly class CatalogueSnapshot
{
    /** @param array<string,mixed> $data */
    private function __construct(public array $data) {}

    public static function fromArray(mixed $data): self
    {
        $keys = ['schema_version', 'association', 'status', 'reason', 'catalogue_id', 'build_identifier', 'hash_policy'];
        if (! is_array($data) || count($data) !== count($keys) || array_diff($keys, array_keys($data)) !== []
            || $data['schema_version'] !== 1 || $data['association'] !== 'same-read-transaction') {
            throw new SolarApiException('Malformed page catalogue association.');
        }
        if ($data['status'] === 'known') {
            if ($data['reason'] !== null || ! CatalogueIdentity::isId($data['catalogue_id'])
                || ! CatalogueIdentity::isId($data['build_identifier']) || $data['hash_policy'] !== 'catalogue-logical-v1') {
                throw new SolarApiException('Malformed known page catalogue identity.');
            }
        } elseif ($data['status'] === 'unknown') {
            if ($data['catalogue_id'] !== null || $data['build_identifier'] !== null || $data['hash_policy'] !== null
                || ! in_array($data['reason'], ['not_recorded', 'not_finalized_or_changed', 'invalid_metadata', 'unsupported_metadata', 'schema_changed'], true)) {
                throw new SolarApiException('Malformed unknown page catalogue identity.');
            }
        } else {
            throw new SolarApiException('Invalid page catalogue identity status.');
        }

        return new self($data);
    }

    public function catalogueId(): ?string
    {
        return $this->data['catalogue_id'];
    }
}

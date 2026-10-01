<?php

declare(strict_types=1);

namespace App\Services\SolarApi\Data;

use Carbon\CarbonImmutable;
use Throwable;

/** Reported identity, not certification that a separate response belongs to it. */
final readonly class CatalogueIdentity
{
    public function __construct(
        public string $status,
        public ?string $catalogueId = null,
        public ?string $buildIdentifier = null,
        public ?string $builtAt = null,
        public ?string $hashPolicy = null,
    ) {}

    public static function fromArray(mixed $data): self
    {
        if (! is_array($data) || ($data['schema_version'] ?? null) !== 1) {
            return new self('unavailable');
        }
        if (($data['status'] ?? null) === 'unknown') {
            return new self('unknown');
        }
        if (($data['status'] ?? null) !== 'known'
            || ! self::isId($data['catalogue_id'] ?? null) || ! self::isId($data['build_identifier'] ?? null)
            || ($data['hash_policy'] ?? null) !== 'catalogue-logical-v1'
            || ! self::isTimestamp($data['built_at'] ?? null)) {
            return new self('unavailable');
        }

        return new self('known', $data['catalogue_id'], $data['build_identifier'], $data['built_at'], $data['hash_policy']);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['schema_version' => 1, 'status' => $this->status, 'catalogue_id' => $this->catalogueId,
            'build_identifier' => $this->buildIdentifier, 'built_at' => $this->builtAt, 'hash_policy' => $this->hashPolicy];
    }

    public function known(): bool
    {
        return $this->status === 'known';
    }

    public static function isId(mixed $value): bool
    {
        return is_string($value) && preg_match('/\Asha256:[a-f0-9]{64}\z/D', $value) === 1;
    }

    public static function isTimestamp(mixed $value): bool
    {
        if (! is_string($value) || strlen($value) > 40
            || preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(?:\.[0-9]{1,6})?(?:Z|[+-](?:[01][0-9]|2[0-3]):[0-5][0-9])\z/D', $value) !== 1) {
            return false;
        }
        try {
            $date = CarbonImmutable::parse($value);

            return $date->year >= 1 && $date->format('Y-m-d\TH:i:s') === substr($value, 0, 19);
        } catch (Throwable) {
            return false;
        }
    }
}

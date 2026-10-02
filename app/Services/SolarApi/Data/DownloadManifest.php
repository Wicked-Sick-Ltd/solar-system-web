<?php

declare(strict_types=1);

namespace App\Services\SolarApi\Data;

final readonly class DownloadManifest
{
    public function __construct(
        public string $artifact,
        public string $compressedSha256,
        public ?string $sqliteSha256,
        public CatalogueIdentity $identity,
    ) {}

    public static function fromArray(mixed $data): ?self
    {
        if (! is_array($data) || ! is_string($data['artefact'] ?? null)
            || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,127}\.sqlite\.zst\z/D', $data['artefact']) !== 1
            || ! CatalogueIdentity::isId('sha256:'.(is_string($data['sha256'] ?? null) ? $data['sha256'] : ''))) {
            return null;
        }
        $sqlite = $data['sqlite_sha256'] ?? null;
        if ($sqlite !== null && (! is_string($sqlite) || ! CatalogueIdentity::isId('sha256:'.$sqlite))) {
            return null;
        }

        return new self($data['artefact'], $data['sha256'], $sqlite,
            isset($data['catalogue_identity']) ? CatalogueIdentity::fromArray($data['catalogue_identity']) : new CatalogueIdentity('unknown'));
    }
}

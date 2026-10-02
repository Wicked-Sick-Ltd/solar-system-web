<?php

declare(strict_types=1);

namespace Tests;

use App\Services\SolarApi\CatalogueContext;
use Illuminate\Support\Facades\Cache;

/** Existing endpoint tests run inside a stable, already observed fixture build. */
final class CatalogueObservation
{
    public static function payload(string $catalogue = 'a', string $build = 'b'): array
    {
        return ['schema_version' => 1, 'status' => 'known',
            'catalogue_id' => 'sha256:'.str_repeat($catalogue, 64),
            'build_identifier' => 'sha256:'.str_repeat($build, 64),
            'hash_policy' => 'catalogue-logical-v1', 'built_at' => '2026-10-01T12:00:00Z'];
    }

    public static function prime(): void
    {
        Cache::put(CatalogueContext::storageKey(), [
            'identity' => self::payload(),
            'token' => 'test-fixture', 'checked' => now()->timestamp + 86400,
        ], 172800);
    }
}

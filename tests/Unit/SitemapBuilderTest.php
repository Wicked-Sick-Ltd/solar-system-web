<?php

declare(strict_types=1);

use App\Services\Sitemap\SitemapBuilder;
use App\Services\SolarApi\CatalogueContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('tolerates a missing stats payload when catalogue identity is unknown', function () {
    config(['cache.default' => 'array']);
    Cache::flush();
    Cache::put(CatalogueContext::storageKey(), [
        'identity' => ['status' => 'unknown', 'schema_version' => 1],
        'token' => null,
        'checked' => time() + 999,
    ], 3600);
    Cache::forget(SitemapBuilder::CACHE_KEY);

    Http::fake([
        '*/stats' => Http::response(null, 404),
        '*' => Http::response(['results' => []]),
    ]);

    expect(fn () => app(SitemapBuilder::class)->build())->not->toThrow(Throwable::class);
});

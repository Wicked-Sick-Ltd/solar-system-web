<?php

declare(strict_types=1);

use App\Services\SolarApi\CatalogueContext;
use App\Services\SolarApi\SolarApiClient;
use App\Support\Links;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'cache.default' => 'array',
        'services.solar.base_url' => 'http://127.0.0.1:8003/api/v1',
        'services.solar.public_url' => 'https://api.publicuniverse.net/api/v1',
    ]);
    Cache::flush();
});

it('fetches from the internal base URL, not the public one', function () {
    Http::fake(['*' => Http::response(['total_objects' => 1, 'by_object_type' => [], 'last_build' => null])]);

    app(SolarApiClient::class)->stats();

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'http://127.0.0.1:8003/api/v1/stats'));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.publicuniverse.net'));
});

it('shows visitors the public catalogue root for links and redirects', function () {
    expect(Links::cataloguePublicBase())->toBe('https://api.publicuniverse.net/api/v1')
        ->and(Links::catalogueOrigin())->toBe('https://api.publicuniverse.net')
        ->and(Links::mcp())->toBe('https://api.publicuniverse.net/mcp')
        ->and(Links::openApi())->toBe('https://api.publicuniverse.net/openapi.json');

    $this->get('/api/v1/search?q=Halley')->assertRedirect()
        ->assertHeader('Location', 'https://api.publicuniverse.net/api/v1/search?q=Halley');
});

it('falls back to the base URL for display when no public URL is configured', function () {
    config(['services.solar.public_url' => '']);

    expect(Links::cataloguePublicBase())->toBe('http://127.0.0.1:8003/api/v1');
});

it('re-checks the catalogue every five minutes rather than every minute', function () {
    expect(CatalogueContext::PROBE_SECONDS)->toBe(300);
});

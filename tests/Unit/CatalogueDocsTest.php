<?php

declare(strict_types=1);

use App\Services\CatalogueDocs\CatalogueCall;
use App\Services\CatalogueDocs\InlineText;
use App\Services\CatalogueDocs\OpenApiCatalogue;
use App\Services\CatalogueDocs\Snippets;

it('escapes specification text and keeps the published bold markup', function () {
    $html = InlineText::html('NASA/JPL. **For astronomy, not astrology.** <script>alert(1)</script>');

    expect($html)->toContain('<strong>For astronomy, not astrology.</strong>')
        ->and($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->and($html)->not->toContain('<script>');
});

it('builds curl, javascript and python snippets without credentials', function () {
    $url = 'https://catalogue.test/api/v1/search?q=Halley&limit=3';

    expect(Snippets::curl($url))->toBe("curl -sS '{$url}'")
        ->and(Snippets::javascript($url, true))->toContain('fetch("'.$url.'")')->toContain('response.json()')
        ->and(Snippets::python($url, true))->toContain('urlopen("'.$url.'")')->toContain('json.load')
        ->and(Snippets::javascript($url, false))->toContain('response.text()')
        ->and(Snippets::mcp('solar-system-db', 'https://catalogue.test/mcp')['cursor'])->toContain('"url": "https://catalogue.test/mcp"')
        ->and(Snippets::mcp('solar-system-db', 'https://catalogue.test/mcp')['cursor'])->not->toContain('Authorization')
        ->and(Snippets::mcp('solar-system-db', 'https://catalogue.test/mcp')['grok'])->toContain('[mcp_servers.solar-system-db]');
});

it('parses public GET operations and drops writes and credentialed routes', function () {
    $document = app(OpenApiCatalogue::class)->parse([
        'openapi' => '3.1.0',
        'info' => [
            'title' => 'solar-system-db REST API',
            'version' => '0.1.0',
            'description' => 'Sourced from NASA/JPL.',
            'license' => ['name' => 'MIT', 'url' => 'https://opensource.org/licenses/MIT'],
            'contact' => ['name' => 'solar-system-db', 'url' => 'http://insecure.test'],
        ],
        'paths' => [
            '/api/v1/search' => ['get' => [
                'operationId' => 'search_objects',
                'tags' => ['catalog'],
                'summary' => 'Fuzzy text search',
                'parameters' => [[
                    'name' => 'q', 'in' => 'query', 'required' => true,
                    'schema' => ['anyOf' => [['type' => 'string'], ['type' => 'null']], 'description' => 'Search query'],
                ]],
                'responses' => ['200' => ['content' => ['application/json' => ['schema' => []]]]],
            ]],
            '/api/v1/admin/rebuild' => ['post' => [
                'operationId' => 'rebuild_catalogue',
                'summary' => 'Rebuild the catalogue',
            ]],
            '/api/v1/secret' => ['get' => [
                'operationId' => 'secret_read',
                'summary' => 'Secret read',
                'security' => [['apiKey' => []]],
            ]],
        ],
    ], 'https://catalogue.test');

    expect($document)->not->toBeNull()
        ->and($document['contact_url'])->toBeNull()
        ->and($document['license_name'])->toBe('MIT')
        ->and(collect($document['operations'])->pluck('id')->all())->toBe(['search_objects'])
        ->and($document['operations'][0]['parameters'][0]['type'])->toBe('string')
        ->and($document['operations'][0]['parameters'][0]['description'])->toBe('Search query');
});

it('waits longer for night planning than for a catalogue read', function () {
    config(['services.solar.timeout' => 8, 'services.solar.planner_timeout' => 40]);
    $call = app(CatalogueCall::class);

    expect($call->timeoutFor('https://catalogue.test/api/v1/search?q=Halley'))->toBe(8)
        ->and($call->timeoutFor('https://catalogue.test/api/v1/observing/night?date=2026-10-10'))->toBe(40);
});

it('keeps read-only calls on the configured origin and caps limit', function () {
    $call = app(CatalogueCall::class);
    $operation = [
        'path' => '/api/v1/search',
        'parameters' => [
            ['name' => 'q', 'in' => 'query', 'required' => true, 'type' => 'string', 'minimum' => null, 'maximum' => null, 'min_length' => 1, 'max_length' => 200],
            ['name' => 'limit', 'in' => 'query', 'required' => false, 'type' => 'integer', 'minimum' => '1', 'maximum' => '100', 'min_length' => null, 'max_length' => null],
        ],
    ];

    $built = $call->build('https://catalogue.test', $operation, ['q' => 'Halley', 'limit' => '50']);

    expect($built['url'])->toBe('https://catalogue.test/api/v1/search?q=Halley&limit=3')
        ->and($call->build('https://catalogue.test', [
            'path' => '/api/v1/objects/{name_or_designation}',
            'parameters' => [
                ['name' => 'name_or_designation', 'in' => 'path', 'required' => true, 'type' => 'string', 'minimum' => null, 'maximum' => null, 'min_length' => null, 'max_length' => null],
            ],
        ], ['name_or_designation' => '../admin'])['url'])->toBeNull()
        ->and($call->build('https://catalogue.test', $operation, ['q' => "Halley\nAuthorization: Bearer secret"])['url'])->toBeNull();
});

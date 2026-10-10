<?php

declare(strict_types=1);

use App\Support\Links;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    config(['cache.default' => 'array']);
    Cache::flush();
    RateLimiter::clear('catalogue-docs:try:'.hash('sha256', '127.0.0.1'));
});

it('shows the configured endpoints when the live specification cannot be read', function () {
    fakeSolar();

    $this->get('/api')
        ->assertOk()
        ->assertSee(config('services.solar.base_url'), false)
        ->assertSee(Links::mcp(), false)
        ->assertSee(Links::apiDocs(), false)
        ->assertSee('The endpoint reference is unavailable')
        ->assertSee('The live tool list could not be loaded')
        ->assertSee('solar-mcp')
        ->assertSee('60 requests per minute')
        ->assertSee('1,000 per day')
        ->assertSee('10 requests per minute')
        ->assertSee(route('plugin'), false)
        ->assertDontSee('find_objects')
        ->assertDontSee('Authorization')
        ->assertDontSee('Bearer');

    labelledCodeBlocks($this->get('/api')->assertOk()->getContent());
});

it('renders the reference, snippets and read-only MCP tools from the fetched documents', function () {
    fakeDocs();

    $response = $this->get('/api')->assertOk();
    $html = $response->getContent();

    $response
        ->assertSee('Fuzzy text search')
        ->assertSee('Get full record for one object')
        ->assertSee('SQLite schema DDL')
        ->assertSee('curl -sS', false)
        ->assertSee('fetch(', false)
        ->assertSee('urlopen(', false)
        ->assertSee('response.text()', false)
        ->assertSee('Halley')
        ->assertSee('"mcpServers"')
        ->assertSee('"solar-system-db"')
        ->assertSee('[mcp_servers.solar-system-db]')
        ->assertSee('grok mcp add --transport http solar-system-db')
        ->assertSee('search')
        ->assertSee('Search the catalogue for Halley')
        ->assertSee('solar-system://schema')
        ->assertSee('plan_observing_night is limited to 10')
        ->assertSee('discover_observing_targets is limited to 5')
        ->assertSee('MIT')
        ->assertSee('&lt;script&gt;', false)
        ->assertDontSee('Rebuild the catalogue')
        ->assertDontSee('Secret read')
        ->assertDontSee('wipe_catalogue')
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('Authorization')
        ->assertHeader('Cache-Control', 'max-age=0, public, s-maxage=600, stale-while-revalidate=86400');

    labelledCodeBlocks($html);
    expect($response->headers->getCookies())->toBe([]);
});

it('runs a read-only GET and refuses path traversal, writes and oversized limits', function () {
    fakeDocs();

    $response = $this->get('/api?try=search_objects&p[q]=Halley&p[limit]=50')
        ->assertOk()
        ->assertSee('HTTP 200')
        ->assertSee('limit=3')
        ->assertSee('Halley')
        ->assertDontSee('limit=50');

    expect($response->headers->get('Cache-Control'))->toContain('private')->toContain('no-store');

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'limit=50'));

    $this->get('/api?try=get_object&p[name_or_designation]=../secret')
        ->assertOk()
        ->assertSee('That value cannot be used in the path.');

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '..'));

    $this->get('/api?try=rebuild_catalogue')
        ->assertOk()
        ->assertSee('That operation is not published as a read-only GET.');

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/admin/rebuild'));
});

it('stops a visitor from making unlimited interactive calls', function () {
    fakeDocs();

    foreach (range(1, 10) as $index) {
        $this->get('/api?try=search_objects&p[q]=Query'.$index.'&p[limit]=1')->assertOk()->assertSee('HTTP 200');
    }

    $this->get('/api?try=search_objects&p[q]=Query11&p[limit]=1')
        ->assertOk()
        ->assertSee('Too many interactive calls');

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'Query11'));
});

function fakeDocs(): void
{
    config([
        'cache.default' => 'array',
        'services.solar.base_url' => 'https://catalogue.test/api/v1',
    ]);
    Cache::flush();

    Http::fake(function ($request) {
        $path = parse_url($request->url(), PHP_URL_PATH) ?? '';
        if (str_ends_with($path, '/openapi.json')) {
            return Http::response(docsSpec());
        }
        if (str_ends_with($path, '/mcp')) {
            return fakeMcp($request);
        }
        if ($request->method() !== 'GET' || ! str_starts_with($request->url(), 'https://catalogue.test/')) {
            return Http::response('unexpected', 500);
        }
        if (str_ends_with($path, '/schema')) {
            return Http::response("create table objects (id text);\n", 200, ['Content-Type' => 'text/plain']);
        }

        return Http::response(['name' => 'Halley']);
    });
}

function fakeMcp($request)
{
    $body = json_decode($request->body(), true);
    $method = is_array($body) ? ($body['method'] ?? '') : '';

    return match ($method) {
        'initialize' => Http::response(
            'event: message'."\n".'data: '.json_encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => [
                    'protocolVersion' => '2025-03-26',
                    'serverInfo' => ['name' => 'solar-system-db', 'version' => '1.30.0'],
                    'instructions' => 'Queryable catalogue for astronomy. Not astrology.',
                ],
            ]),
            200,
            ['mcp-session-id' => 'test-session', 'Content-Type' => 'text/event-stream'],
        ),
        'notifications/initialized' => $request->hasHeader('mcp-session-id', 'test-session')
            ? Http::response('', 202)
            : Http::response('missing session', 400),
        'tools/list' => Http::response(
            'event: message'."\n".'data: '.json_encode(['jsonrpc' => '2.0', 'id' => 2, 'result' => ['tools' => [
                ['name' => 'search', 'description' => "Free-text search.\nUse Halley as an example.", 'annotations' => ['readOnlyHint' => true, 'destructiveHint' => false], 'inputSchema' => ['properties' => ['query' => ['type' => 'string', 'description' => 'Search text']], 'required' => ['query']]],
                ['name' => 'plan_observing_night', 'description' => 'Plan one night.', 'annotations' => ['readOnlyHint' => true, 'destructiveHint' => false], 'inputSchema' => ['properties' => []]],
                ['name' => 'discover_observing_targets', 'description' => 'Bounded discovery.', 'annotations' => ['readOnlyHint' => true, 'destructiveHint' => false], 'inputSchema' => ['properties' => []]],
                ['name' => 'wipe_catalogue', 'description' => 'Destroy the database.', 'annotations' => ['readOnlyHint' => false, 'destructiveHint' => true], 'inputSchema' => ['properties' => []]],
            ]]]),
            200,
            ['Content-Type' => 'text/event-stream'],
        ),
        'resources/list' => Http::response(
            'event: message'."\n".'data: '.json_encode(['jsonrpc' => '2.0', 'id' => 3, 'result' => ['resources' => [
                ['uri' => 'solar-system://schema', 'name' => 'schema', 'description' => 'The SQLite schema.'],
                ['uri' => 'javascript:alert(1)', 'name' => 'bad', 'description' => 'nope'],
            ]]]),
            200,
            ['Content-Type' => 'text/event-stream'],
        ),
        default => Http::response('unexpected mcp', 500),
    };
}

function docsSpec(): array
{
    return [
        'openapi' => '3.1.0',
        'info' => [
            'title' => 'solar-system-db REST API',
            'version' => '0.1.0',
            'description' => 'Sourced from NASA/JPL and the IAU Minor Planet Center. **For astronomy, not astrology.** <script>alert(1)</script>',
            'contact' => ['name' => 'solar-system-db', 'url' => 'https://github.com/Wicked-Sick-Ltd/solar-system-db'],
            'license' => ['name' => 'MIT', 'url' => 'https://opensource.org/licenses/MIT'],
        ],
        'paths' => [
            '/api/v1/search' => ['get' => [
                'tags' => ['catalog'],
                'summary' => 'Fuzzy text search',
                'operationId' => 'search_objects',
                'parameters' => [
                    ['name' => 'q', 'in' => 'query', 'required' => true, 'description' => 'Search query', 'schema' => ['type' => 'string', 'minLength' => 1]],
                    ['name' => 'limit', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20]],
                ],
                'responses' => ['200' => ['description' => 'ok', 'content' => ['application/json' => ['schema' => []]]]],
            ]],
            '/api/v1/objects/{name_or_designation}' => ['get' => [
                'tags' => ['catalog'],
                'summary' => 'Get full record for one object',
                'operationId' => 'get_object',
                'parameters' => [
                    ['name' => 'name_or_designation', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                ],
                'responses' => ['200' => ['description' => 'ok', 'content' => ['application/json' => ['schema' => []]]]],
            ]],
            '/api/v1/schema' => ['get' => [
                'tags' => ['reference'],
                'summary' => 'SQLite schema DDL',
                'operationId' => 'get_schema',
                'responses' => ['200' => ['description' => 'ok', 'content' => ['text/plain' => ['schema' => ['type' => 'string']]]]],
            ]],
            '/api/v1/admin/rebuild' => ['post' => [
                'summary' => 'Rebuild the catalogue',
                'operationId' => 'rebuild_catalogue',
                'responses' => ['200' => ['description' => 'ok']],
            ]],
            '/api/v1/secret' => ['get' => [
                'summary' => 'Secret read',
                'operationId' => 'secret_read',
                'security' => [['apiKey' => []]],
                'responses' => ['200' => ['description' => 'ok']],
            ]],
        ],
    ];
}

function labelledCodeBlocks(string $html): void
{
    $document = new DOMDocument;
    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $blocks = $xpath->query('//pre[contains(@class, "overflow-x-auto")]');

    expect($blocks->length)->toBeGreaterThan(0);
    foreach ($blocks as $block) {
        expect($block->getAttribute('tabindex'))->toBe('0');
        $labelId = $block->getAttribute('aria-labelledby');
        expect($labelId)->not->toBe('')
            ->and(trim($xpath->evaluate("string(//*[@id='$labelId'])")))->not->toBe('');
    }
}

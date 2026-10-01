<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Tests\Support\RouteWorkload;

/** @var array<string,mixed> */
$performanceResults = [];

it('keeps representative request-kernel work within measured structural budgets', function (string $scenario) use (&$performanceResults) {
    if (! is_file(public_path('build/manifest.json'))) {
        if (getenv('PERFORMANCE_REQUIRED') === '1') {
            $this->fail('Production assets are required: run npm ci && npm run build.');
        }
        $this->markTestSkipped('Production performance workload runs in its dedicated built-assets CI job.');
    }
    $this->withVite();
    $this->travelTo(new DateTimeImmutable('2026-10-01T12:00:00Z'));
    config(['app.debug' => false, 'app.url' => 'https://performance.example.test', 'queue.default' => 'sync', 'services.mailchimp.api_key' => null, 'services.mailchimp.audience_id' => null]);
    [$method, $path, $input] = RouteWorkload::scenarios()[$scenario];
    $budget = json_decode(file_get_contents(base_path('tests/fixtures/performance/route-budgets.json')), true, 512, JSON_THROW_ON_ERROR)[$scenario];
    $iterations = getenv('PERFORMANCE_REPORT') ? 7 : 2;
    $measurements = ['cold_application_cache' => [], 'fresh_application_cache' => []];
    $initialStructure = null;
    for ($iteration = 0; $iteration < $iterations; $iteration++) {
        RouteWorkload::fake();
        foreach (array_keys($measurements) as $temperature) {
            // A test process survives requests; production PHP requests start with fresh
            // Livewire render state. Preserve the application cache, not asset flags.
            app()->forgetScopedInstances();
            app('livewire')->flushState();
            app('view')->flushState();
            $before = Http::recorded()->count();
            gc_collect_cycles();
            $baseline = memory_get_usage(false);
            memory_reset_peak_usage();
            $started = hrtime(true);
            $response = $method === 'POST' ? $this->post($path, $input) : $this->get($path);
            $milliseconds = (hrtime(true) - $started) / 1000000;
            $peak = memory_get_peak_usage(false);
            $response->assertOk();
            $body = $response->getContent();
            $calls = Http::recorded()->slice($before);
            $upstreamBytes = $calls->sum(fn ($pair) => strlen($pair[1]->body()));
            $elements = 0;
            $scripts = [];
            if (str_contains($response->headers->get('Content-Type', ''), 'text/html')) {
                $document = new DOMDocument;
                $previous = libxml_use_internal_errors(true);
                $document->loadHTML($body);
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
                $elements = $document->getElementsByTagName('*')->length;
                foreach ($document->getElementsByTagName('script') as $script) {
                    if ($script->hasAttribute('src')) {
                        $scripts[] = $script->getAttribute('src');
                    }
                }
                expect(count(array_filter($scripts, fn ($src) => str_contains($src, '/livewire.min.js'))))->toBe(1);
            }
            $structure = ['elements' => $elements, 'scripts' => $scripts];
            $initialStructure ??= $structure;
            expect($structure)->toBe($initialStructure);
            $measurements[$temperature][] = [
                'kernel_ms' => round($milliseconds, 3), 'php_peak_used_bytes' => $peak,
                'php_peak_increment_bytes' => max(0, $peak - $baseline),
                'upstream_calls' => $calls->count(), 'upstream_body_bytes' => $upstreamBytes,
                'response_bytes' => strlen($body), 'response_gzip_bytes' => strlen(gzencode($body, 9)),
                'dom_elements' => $elements, 'script_sources' => $scripts,
            ];
            $callLimit = $temperature === 'cold_application_cache' ? $budget['cold_calls'] : $budget['warm_calls'];
            expect($calls->count())->toBeLessThanOrEqual($callLimit);
            expect($upstreamBytes)->toBeLessThanOrEqual($budget['upstream_bytes']);
            expect(strlen($body))->toBeLessThanOrEqual($budget['response_bytes']);
            expect(strlen(gzencode($body, 9)))->toBeLessThanOrEqual($budget['gzip_bytes']);
            expect($elements)->toBeLessThanOrEqual($budget['dom_elements']);
            if ($scenario === 'home') {
                $response->assertSee('15,546');
            } elseif ($scenario === 'saturn') {
                $response->assertSee('A Ring');
            } elseif ($scenario === 'search') {
                $response->assertSee('Ceres')->assertSee('Synthetic planet 0');
            } elseif ($scenario === 'orrery') {
                expect($document->getElementsByTagName('circle')->length)->toBeGreaterThanOrEqual(10);
            } elseif ($scenario === 'night_form') {
                $response->assertSee('Calculate this night')->assertSee('name="timezone"', false);
            } elseif ($scenario === 'galaxy_data_5000') {
                $response->assertJsonCount(5000, 'hosts');
            } elseif (in_array($scenario, ['systems_5000', 'galaxy_5000'], true)) {
                $response->assertSee('Synthetic host 0000');
            } elseif ($scenario === 'exoplanets_page') {
                $response->assertSee('Synthetic planet 23');
            } elseif ($scenario === 'night_eight') {
                $response->assertSee('Your night')->assertSee('NGC6720')->assertSee('Altitude and direction sample table');
                expect($document->getElementsByTagName('tr')->length)->toBeGreaterThanOrEqual(8 * 289);
            }

            unset($script, $document, $response, $body, $calls);
        }
    }
    $performanceResults[$scenario] = ['method' => $method, 'path' => $path, 'measurements' => $measurements];
})->with(array_keys(RouteWorkload::scenarios()));

afterAll(function () use (&$performanceResults) {
    $file = getenv('PERFORMANCE_REPORT');
    if ($file === false || $file === '') {
        return;
    }
    $report = [
        'schema_version' => 1,
        'measurement' => 'Laravel test request-kernel with deterministic HTTP fakes; warm OS and framework process; no browser, sockets or backend computation in route timings.',
        'source_head' => trim((string) shell_exec('git rev-parse HEAD')),
        'source_dirty' => trim((string) shell_exec('git status --porcelain')) !== '',
        'composer_lock_sha256' => hash_file('sha256', dirname(__DIR__, 2).'/composer.lock'),
        'package_lock_sha256' => hash_file('sha256', dirname(__DIR__, 2).'/package-lock.json'),
        'fixture_helpers_sha256' => hash_file('sha256', dirname(__DIR__).'/Pest.php'),
        'machine_label' => getenv('PERFORMANCE_MACHINE') ?: 'unspecified; see OS and architecture',
        'php_version' => PHP_VERSION, 'os' => PHP_OS_FAMILY, 'architecture' => php_uname('m'),
        'workload_sha256' => hash_file('sha256', dirname(__DIR__).'/Support/RouteWorkload.php'),
        'night_fixture_sha256' => hash('sha256', gzdecode(file_get_contents(dirname(__DIR__).'/fixtures/performance/catalogue-night-eight.json.gz'))),
        'routes' => $performanceResults,
    ];
    file_put_contents($file, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
});

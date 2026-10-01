<?php

use App\Jobs\RefreshSolarCache;
use App\Services\SolarApi\CatalogueContext;
use App\Services\SolarApi\SolarApiClient;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\CatalogueObservation;

function pageSnapshot(string $id = 'c'): array
{
    return ['schema_version' => 1, 'association' => 'same-read-transaction', 'status' => 'known', 'reason' => null,
        'catalogue_id' => 'sha256:'.str_repeat($id, 64), 'build_identifier' => 'sha256:'.str_repeat('d', 64), 'hash_policy' => 'catalogue-logical-v1'];
}

beforeEach(function () {
    config(['cache.default' => 'array']);
    Cache::flush();
    CatalogueObservation::prime();
    Http::swap(new Factory);
    Http::preventStrayRequests();
});

it('retains only the identity associated with the actual page in JSON and CSV', function () {
    $snapshot = pageSnapshot(); // Separately observed global fixture identity is A, not this page's C.
    Http::fake(['*/exoplanets*' => Http::response(['available' => true, 'results' => [exoplanetPayload()], 'catalogue_snapshot' => $snapshot])]);
    $this->get('/exoplanets/export/json')->assertOk()->assertJsonPath('metadata.snapshot_id', $snapshot['catalogue_id'])
        ->assertJsonPath('metadata.catalogue_snapshot', $snapshot)->assertJsonPath('metadata.snapshot_association', 'same-read-transaction');
    $csv = $this->get('/exoplanets/export/csv')->assertOk();
    $stream = fopen('php://temp', 'w+');
    fwrite($stream, $csv->getContent());
    rewind($stream);
    $headers = fgetcsv($stream, escape: '');
    $metadata = array_combine($headers, fgetcsv($stream, escape: ''));
    fclose($stream);
    $decoded = json_decode($metadata['metadata_json'], true, flags: JSON_THROW_ON_ERROR);
    expect($decoded['catalogue_snapshot'])->toBe($snapshot)->and($decoded['snapshot_id'])->toBe($snapshot['catalogue_id']);
    Http::assertSentCount(1);
});

it('distinguishes a legacy unassociated page from an associated unknown identity', function () {
    Http::fake(['*/exoplanets*' => Http::response(['available' => true, 'results' => []])]);
    $this->get('/exoplanets/export/json')->assertOk()->assertJsonPath('metadata.snapshot_association', 'unassociated')
        ->assertJsonPath('metadata.catalogue_snapshot', null)->assertJsonPath('metadata.snapshot_id', null);
});

it('preserves an associated unknown reason without inventing an immutable snapshot', function (string $reason) {
    $snapshot = array_replace(pageSnapshot(), ['status' => 'unknown', 'reason' => $reason, 'catalogue_id' => null, 'build_identifier' => null, 'hash_policy' => null]);
    Http::fake(['*/exoplanets*' => Http::response(['available' => true, 'results' => [], 'catalogue_snapshot' => $snapshot])]);
    $this->get('/exoplanets/export/json')->assertOk()->assertJsonPath('metadata.snapshot_association', 'same-read-transaction')
        ->assertJsonPath('metadata.catalogue_snapshot', $snapshot)->assertJsonPath('metadata.snapshot_id', null);
})->with(['not_recorded', 'not_finalized_or_changed', 'invalid_metadata', 'unsupported_metadata', 'schema_changed']);

it('rejects present malformed page identities rather than downgrading them to legacy exports', function (mixed $snapshot) {
    Http::fake(['*/exoplanets*' => Http::response(['available' => true, 'results' => [exoplanetPayload()], 'catalogue_snapshot' => $snapshot])]);
    foreach (['json', 'csv'] as $format) {
        $this->get('/exoplanets/export/'.$format)->assertStatus(503)->assertHeaderMissing('Content-Disposition')
            ->assertHeader('Cache-Control', 'no-store, private');
    }
})->with([
    'null' => [null], 'scalar' => ['known'], 'boolean' => [true], 'list' => [[pageSnapshot()]],
    'empty' => [[]], 'missing status' => [array_diff_key(pageSnapshot(), ['status' => 1])],
    'unknown field' => [pageSnapshot() + ['extra' => 'not understood']],
    'boolean version' => [array_replace(pageSnapshot(), ['schema_version' => true])],
    'string version' => [array_replace(pageSnapshot(), ['schema_version' => '1'])],
    'wrong association' => [array_replace(pageSnapshot(), ['association' => 'separate-probe'])],
    'unavailable status' => [array_replace(pageSnapshot(), ['status' => 'unavailable'])],
    'uppercase hash' => [array_replace(pageSnapshot(), ['catalogue_id' => 'sha256:'.str_repeat('A', 64)])],
    'missing build' => [array_replace(pageSnapshot(), ['build_identifier' => null])],
    'compound hash' => [array_replace(pageSnapshot(), ['catalogue_id' => ['bad']])],
    'invalid policy' => [array_replace(pageSnapshot(), ['hash_policy' => 'v2'])],
    'known with reason' => [array_replace(pageSnapshot(), ['reason' => 'not_recorded'])],
    'unknown with known fields' => [array_replace(pageSnapshot(), ['status' => 'unknown', 'reason' => 'not_recorded'])],
    'unknown without reason' => [array_replace(pageSnapshot(), ['status' => 'unknown', 'reason' => null, 'catalogue_id' => null, 'build_identifier' => null, 'hash_policy' => null])],
]);

it('keeps identity with stale cached rows even when the provider now serves another page', function () {
    Bus::fake([RefreshSolarCache::class]);
    $snapshot = pageSnapshot('a');
    $planet = exoplanetPayload();
    Http::fake(function () use (&$snapshot, &$planet) {
        return Http::response(['available' => true, 'results' => [$planet], 'catalogue_snapshot' => $snapshot]);
    });
    $api = app(SolarApiClient::class);
    expect($api->exoplanets()->catalogueSnapshot->catalogueId())->toBe(pageSnapshot('a')['catalogue_id']);
    $key = app(CatalogueContext::class)->key('/exoplanets', ['limit' => 25, 'offset' => 0]);
    $entry = Cache::get($key);
    $entry['soft'] = time() - 1;
    Cache::put($key, $entry, 600);
    $snapshot = pageSnapshot('b');
    $planet['name'] = 'New provider row';
    $response = $this->get('/exoplanets/export/json')->assertOk()->assertJsonPath('metadata.snapshot_id', pageSnapshot('a')['catalogue_id'])
        ->assertJsonPath('results.0.name', 'Proxima Cen b');
    expect($response->json('metadata.snapshot_note'))->toContain('may be cached');
    Bus::assertDispatched(RefreshSolarCache::class);
    Http::assertSentCount(1);
});

it('retains each response identity through catalogue changes and rollback without pinning pages', function () {
    Cache::flush();
    $generation = 'a';
    Http::fake(function ($request) use (&$generation) {
        if (str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/catalogue')) {
            return Http::response(CatalogueObservation::payload($generation, $generation));
        }
        $planet = exoplanetPayload();
        $planet['name'] = 'Generation '.$generation;

        return Http::response(['available' => true, 'results' => [$planet], 'catalogue_snapshot' => pageSnapshot($generation)]);
    });
    foreach (['a', 'b', 'a'] as $generation) {
        $this->get('/exoplanets/export/json')->assertOk()->assertJsonPath('metadata.snapshot_id', pageSnapshot($generation)['catalogue_id'])
            ->assertJsonPath('results.0.name', 'Generation '.$generation);
        $this->travel(61)->seconds();
    }
    Http::assertSentCount(6);
});

it('keeps the associated identity when the extra pagination row is removed', function () {
    Http::fake(['*/exoplanets*' => Http::response(['available' => true, 'results' => array_fill(0, 25, exoplanetPayload()), 'catalogue_snapshot' => pageSnapshot()])]);
    $this->get('/exoplanets/export/json')->assertOk()->assertJsonCount(24, 'results')
        ->assertJsonPath('metadata.catalogue_snapshot', pageSnapshot())->assertJsonPath('metadata.pagination.has_more', true);
});

it('shows the friendly catalogue failure without export links for malformed present identity', function () {
    Http::fake(['*/exoplanets*' => Http::response(['available' => true, 'results' => [exoplanetPayload()], 'catalogue_snapshot' => null])]);
    $this->get('/exoplanets')->assertOk()->assertSee('temporarily unavailable')->assertDontSee('Download page as CSV');
});

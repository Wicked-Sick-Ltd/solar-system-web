<?php

use App\Services\SolarApi\SolarApiClient;
use App\Support\Format;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => fakeSolar());

it('lists upcoming Earth approaches soonest first, linked to each object', function () {
    $this->travelTo('2026-09-29 12:00:00');

    $this->get('/close-approaches')
        ->assertOk()
        ->assertSeeInOrder(['2022 UP6', '2019 XF2'])
        ->assertSee(route('objects.show', 'ast-sooner'), escape: false)
        ->assertSee('2.6 LD');

    Http::assertSent(fn ($r) => str_ends_with(parse_url($r->url(), PHP_URL_PATH), '/close-approaches')
        && $r['from'] === '2026-09-29' && $r['to'] === '2026-11-28' && $r['body'] === 'Earth');
});

it('degrades when the API is down', function () {
    fakeSolarDown();

    $this->get('/close-approaches')->assertOk()->assertSee('unavailable');
});

function approachRow(array $overrides = []): array
{
    return array_replace([
        'object_id' => 'ast-example', 'name' => 'Example', 'designation' => '2026 AA',
        'body' => 'Earth', 'cd_iso' => '2026-10-15T20:59:00Z',
        'dist_au' => 0.00672, 'v_rel_km_s' => 9.02,
    ], $overrides);
}

it('reports missing or malformed close-approach catalogues as unavailable', function (mixed $payload, int $status) {
    Http::swap(new Factory);
    Http::fake(['*' => Http::response($payload, $status)]);
    $this->get('/close-approaches')->assertOk()->assertSee('unavailable')
        ->assertDontSee('No close approaches returned');
})->with([
    'older endpoint' => [[], 404],
    'missing envelope' => [[], 200],
    'wrong envelope type' => [['results' => null], 200],
    'keyed records' => [['results' => ['first' => approachRow()]], 200],
    'invalid record' => [['results' => ['invalid']], 200],
    'missing identity' => [['results' => [approachRow(['object_id' => null])]], 200],
    'missing label' => [['results' => [approachRow(['name' => null, 'designation' => null])]], 200],
    'array label' => [['results' => [approachRow(['name' => []])]], 200],
    'wrong body' => [['results' => [approachRow(['body' => 'Moon'])]], 200],
    'missing date' => [['results' => [approachRow(['cd_iso' => null])]], 200],
    'rolled date' => [['results' => [approachRow(['cd_iso' => '2026-02-30T20:59:00Z'])]], 200],
    'negative distance' => [['results' => [approachRow(['dist_au' => -0.1])]], 200],
    'infinite speed' => [['results' => [approachRow(['v_rel_km_s' => '1e309'])]], 200],
    'malformed measurement' => [['results' => [approachRow(['dist_min_au' => []])]], 200],
]);

it('describes empty results without claiming there are no physical encounters', function () {
    Http::swap(new Factory);
    Http::fake(['*' => Http::response(['results' => []])]);
    $this->get('/close-approaches')->assertOk()->assertSee('No close approaches returned')
        ->assertSee('older catalogue builds may not include close-approach data')
        ->assertDontSee('Nothing passing close');
});

it('discloses that a capped nearest subset may omit other encounters', function () {
    Http::swap(new Factory);
    Http::fake(['*' => Http::response(['results' => array_map(
        fn ($index) => approachRow(['object_id' => 'ast-'.$index, 'name' => 'Object '.$index, 'designation' => '2026 '.$index]),
        range(1, 200),
    )])]);
    $this->get('/close-approaches')->assertOk()->assertSee('limit of 200 closest encounters')
        ->assertSee('Other encounters may be omitted');
    Http::assertSent(fn ($request) => $request['limit'] === 200);
});

it('keeps missing measurements distinct from true zero and tiny positive values', function () {
    Http::swap(new Factory);
    Http::fake(['*' => Http::response(['results' => [
        approachRow(['object_id' => 'missing', 'name' => 'Missing', 'designation' => 'Missing', 'dist_au' => null, 'v_rel_km_s' => null]),
        approachRow(['object_id' => 'zero', 'name' => 'Zero', 'designation' => 'Zero', 'dist_au' => 0, 'v_rel_km_s' => 0]),
        approachRow(['object_id' => 'tiny', 'name' => 'Tiny', 'designation' => 'Tiny', 'dist_au' => 0.00001, 'v_rel_km_s' => 0.01]),
    ]])]);
    $response = $this->get('/close-approaches')->assertOk()->assertSee('<0.0001 AU')->assertSee('<0.1 LD')->assertSee('<0.1 km/s');
    $document = new DOMDocument;
    $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    foreach ([1 => ['—', '—', '—'], 2 => ['0 LD', '0 AU', '0 km/s']] as $row => $values) {
        foreach ($values as $index => $value) {
            $column = $index + 3;
            expect(trim($xpath->evaluate("string(//tbody/tr[$row]/td[$column])")))->toBe($value);
        }
    }
    $values = app(SolarApiClient::class)->closeApproaches('2026-10-01', '2026-11-30');
    expect($values[0]->distAu)->toBeNull()->and($values[1]->distAu)->toBe(0.0)->and($values[2]->distAu)->toBe(0.00001);
});

it('retains designation fallback and numeric source strings', function (?string $name) {
    Http::swap(new Factory);
    Http::fake(['*' => Http::response(['results' => [approachRow(['name' => $name, 'body' => 'earth', 'dist_au' => '0.00672'])]])]);
    $this->get('/close-approaches')->assertOk()->assertSee('2026 AA')->assertSee('2.6 LD');
})->with([null, '', '   ']);

it('shows duplicate API rows as returned without frontend deduplication', function () {
    Http::swap(new Factory);
    Http::fake(['*' => Http::response(['results' => [
        approachRow(['object_id' => 'ast-tl6', 'name' => '2026 TL6', 'designation' => '2026 TL6', 'cd_iso' => '2026-10-12T05:40:00Z', 'dist_min_au' => null, 'dist_max_au' => null, 'mass_kg' => null, 't_sigma' => null]),
        approachRow(['object_id' => 'ast-tl6', 'name' => '2026 TL6', 'designation' => '2026 TL6', 'cd_iso' => '2026-10-12T05:41:00Z', 'dist_min_au' => 0.006, 'dist_max_au' => 0.007, 'mass_kg' => 1.2e9, 't_sigma' => '< 00:01']),
        approachRow(['object_id' => 'ast-ty1', 'name' => '2026 TY1', 'designation' => '2026 TY1', 'cd_iso' => '2026-10-12T05:40:00Z']),
    ]])]);

    $html = $this->get('/close-approaches')->assertOk()->getContent();

    expect(substr_count($html, '2026-10-12 05:40:00'))->toBe(2)
        ->and(substr_count($html, '2026-10-12 05:41:00'))->toBe(1)
        ->and(substr_count($html, '2026 TL6'))->toBe(2)
        ->and(substr_count($html, '2026 TY1'))->toBe(1)
        ->and($html)->toContain(Format::massKg(1.2e9));
});

it('shows a mass column only when an approach has a mass on record', function () {
    Http::swap(new Factory);
    Http::fake(['*' => Http::response(['results' => [approachRow(['mass_kg' => 7.329e10]), approachRow(['object_id' => 'ast-other', 'designation' => '2026 BB', 'name' => 'Other'])]])]);
    $this->get('/close-approaches')->assertOk()->assertSee('>Mass<', escape: false)->assertSee(Format::massKg(7.329e10), escape: false);

    Http::swap(new Factory);
    Http::fake(['*' => Http::response(['results' => [approachRow()]])]);
    Cache::flush();
    $this->get('/close-approaches')->assertOk()->assertDontSee('>Mass<', escape: false);
});

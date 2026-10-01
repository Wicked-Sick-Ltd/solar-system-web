<?php

use App\Livewire\Exoplanets;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    config(['cache.default' => 'array']);
    Cache::flush();
    Http::swap(new Factory);
    Http::preventStrayRequests();
});

/** Parse RFC-style CSV including embedded quotes and newlines. @return list<array<string,string|null>> */
function readExoplanetCsv(string $csv): array
{
    $stream = fopen('php://temp', 'w+');
    fwrite($stream, $csv);
    rewind($stream);
    $header = fgetcsv($stream, escape: '');
    $rows = [];
    while (($row = fgetcsv($stream, escape: '')) !== false) {
        expect($row)->toHaveCount(count($header));
        $rows[] = array_combine($header, $row);
    }
    fclose($stream);

    return $rows;
}

it('downloads one filtered page as a guest with the same selection as the page', function () {
    fakeSolar();
    $query = ['q' => ' Proxima ', 'method' => 'Radial Velocity', 'distance' => '10', 'page' => 2];
    Livewire::withQueryParams($query)->test(Exoplanets::class)->assertSee(route('exoplanets.export', ['format' => 'json', 'q' => 'Proxima', 'method' => 'Radial Velocity', 'distance' => '10', 'page' => 2]));
    $this->get('/exoplanets/export/json?'.http_build_query($query))->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="exoplanets-page-2.json"')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('metadata.scope', 'current_filtered_page')
        ->assertJsonPath('metadata.filters.q', 'Proxima')->assertJsonPath('metadata.filters.discovery_method', 'Radial Velocity')
        ->assertJsonPath('metadata.filters.max_distance_pc', '10')->assertJsonPath('metadata.pagination.offset', 24)
        ->assertJsonPath('metadata.pagination.per_page', 24)->assertJsonPath('metadata.snapshot_id', null)
        ->assertJsonPath('results.0.retrieved_at', '2026-09-22T12:00:00Z');
    Http::assertSent(fn ($r) => $r['q'] === 'Proxima' && $r['discovery_method'] === 'Radial Velocity'
        && $r['max_distance_pc'] === '10' && $r['limit'] === 25 && $r['offset'] === 24);
    Http::assertSentCount(1); // Page and export reuse the same cached bounded request.
});

it('preserves raw measurements nulls zero limits references and unknown source keys in JSON', function () {
    $planet = exoplanetPayload();
    $source = ['pl_orbper' => 6.101013000035999, 'pl_orbpererr1' => 3.5e-16, 'pl_orbpererr2' => -4.000000000003e-16,
        'pl_orbperlim' => 0, 'pl_eqt' => null, 'pl_rade' => 0, 'pl_bmasselim' => -1,
        'pl_orbper_reflink' => '<a href="https://example.test/paper">Paper</a>', 'future_field' => ['x' => null]];
    $planet['source_data'] = $source;
    Http::fake(['*/exoplanets*' => Http::response(['available' => true, 'results' => [$planet]])]);
    $response = $this->get('/exoplanets/export/json')->assertOk();
    expect($response->json('results.0.source_data'))->toBe($source)
        ->and(array_key_exists('pl_eqterr1', $response->json('results.0.source_data')))->toBeFalse();
    $response->assertJsonPath('metadata.units.pl_orbper', 'days')->assertJsonPath('metadata.units.pl_bmasse', 'Earth masses')
        ->assertJsonPath('results.0.archive_url', 'https://exoplanetarchive.ipac.caltech.edu/overview/Proxima%20Cen%20b');
});

it('round trips CSV tiny errors numeric negatives quotes newlines and source null versus missing', function () {
    $planet = exoplanetPayload();
    $planet['name'] = "Planet, \"quoted\"\nsecond line";
    $planet['source_data'] = ['pl_orbper' => 6.101013000035999, 'pl_orbpererr1' => 3.5e-16,
        'pl_orbpererr2' => -4.000000000003e-16, 'pl_orbperlim' => 0, 'pl_eqt' => null, 'pl_rade' => 0];
    Http::fake(['*/exoplanets*' => Http::response(['available' => true, 'results' => [$planet]])]);
    $response = $this->get('/exoplanets/export/csv')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    [$metadata, $row] = readExoplanetCsv($response->getContent());
    expect($metadata['record_type'])->toBe('metadata')->and($row['record_type'])->toBe('planet')
        ->and($row['name'])->toBe($planet['name'])->and((float) $row['pl_orbper'])->toBe($planet['source_data']['pl_orbper'])
        ->and((float) $row['pl_orbpererr1'])->toBe(3.5e-16)->and((float) $row['pl_orbpererr2'])->toBe(-4.000000000003e-16)
        ->and($row['pl_orbpererr2'])->not->toStartWith("'")
        ->and($row['pl_rade'])->toBe('0')->and($row['pl_eqt'])->toBe('');
    expect(json_decode($row['source_data_json'], true, flags: JSON_THROW_ON_ERROR))->toBe($planet['source_data']);
    $meta = json_decode($metadata['metadata_json'], true, flags: JSON_THROW_ON_ERROR);
    expect($meta['pagination']['count'])->toBe(1)->and($meta['snapshot_id'])->toBeNull()
        ->and($meta['units']['pl_orbper'])->toBe('days')->and($meta['generated_at'])->not->toBeEmpty();
});

it('neutralises spreadsheet formulas in all string columns without changing the raw JSON', function (string $unsafe) {
    $planet = exoplanetPayload();
    $planet['name'] = $unsafe;
    $planet['source_data']['pl_orbper_reflink'] = $unsafe;
    $planet['source_data']['pl_eqt'] = '-123'; // String source value, not an actual numeric value.
    Http::fake(['*/exoplanets*' => Http::response(['available' => true, 'results' => [$planet]])]);
    $response = $this->get('/exoplanets/export/csv')->assertOk();
    [, $row] = readExoplanetCsv($response->getContent());
    expect($row['name'])->toBe("'".$unsafe)->and($row['pl_orbper_reflink'])->toBe("'".$unsafe)
        ->and($row['pl_eqt'])->toBe("'-123")->and(json_decode($row['source_data_json'], true)['pl_orbper_reflink'])->toBe($unsafe);
})->with(['=1+1', '+SUM(A1)', '-1+2', '@SUM(A1)', "\t=1+1", "\r=1+1", "\n=1+1", '  =1+1', "\u{00A0}=1+1"]);

it('caps output at the displayed 24 and records whether more results exist', function () {
    Http::fake(['*/exoplanets*' => Http::response(['available' => true, 'results' => array_fill(0, 25, exoplanetPayload())])]);
    $this->get('/exoplanets/export/json')->assertOk()->assertJsonCount(24, 'results')
        ->assertJsonPath('metadata.pagination.count', 24)->assertJsonPath('metadata.pagination.has_more', true);
    Http::assertSentCount(1);
});

it('exports genuine empty results with metadata rather than invented rows', function () {
    Http::fake(['*/exoplanets*' => Http::response(['available' => true, 'results' => []])]);
    $this->get('/exoplanets/export/json')->assertOk()->assertJsonCount(0, 'results')->assertJsonPath('metadata.pagination.has_more', false);
    $csv = $this->get('/exoplanets/export/csv')->assertOk();
    $rows = readExoplanetCsv($csv->getContent());
    expect($rows)->toHaveCount(1)->and($rows[0]['record_type'])->toBe('metadata');
});

it('returns JSON errors without download headers when the catalogue is unavailable', function (int $status, array $body) {
    Http::fake(['*/exoplanets*' => Http::response($body, $status)]);
    $this->get('/exoplanets/export/csv')->assertStatus(503)->assertJsonPath('error', 'Exoplanet export temporarily unavailable.')
        ->assertHeaderMissing('Content-Disposition')->assertHeader('Cache-Control', 'no-store, private');
})->with([[503, []], [404, []], [200, ['available' => false, 'results' => []]],
    [200, ['available' => true]], [200, ['available' => true, 'results' => 'broken']],
    [200, ['available' => true, 'results' => [null]]],
    [200, ['available' => true, 'results' => [['id' => '', 'name' => '', 'host_id' => '']]]],
    [200, ['available' => true, 'results' => [array_replace(exoplanetPayload(), ['source_data' => 'broken'])]]],
]);

it('rejects invalid export input without a catalogue request', function (array $query) {
    $this->get('/exoplanets/export/json?'.http_build_query($query))->assertStatus(422)->assertJsonPath('error', 'Invalid export filters.')
        ->assertHeaderMissing('Content-Disposition');
    Http::assertNothingSent();
})->with([[['q' => str_repeat('x', 201)]], [['q' => ['unexpected']]], [['method' => str_repeat('x', 101)]],
    [['distance' => '5']], [['page' => '']], [['page' => 4001]], [['page' => 0]], [['page' => '-3']], [['page' => 'not-a-number']]]);

it('uses the same validation on the page and hides invalid export links', function () {
    Livewire::withQueryParams(['distance' => '5'])->test(Exoplanets::class)->assertSee('selected distance is invalid')->assertDontSee('Download page as CSV');
    Http::assertNothingSent();
});

it('restricts export formats without shadowing planet detail routes', function () {
    fakeSolar();
    $this->get('/exoplanets/export/xml')->assertNotFound();
    $this->get('/exoplanets/exo-proxima-b')->assertOk()->assertSee('Proxima Cen b');
});

it('throttles repeated downloads', function () {
    fakeSolar();
    for ($i = 0; $i < 30; $i++) {
        $this->get('/exoplanets/export/json')->assertOk();
    }
    $this->get('/exoplanets/export/json')->assertStatus(429);
});

it('rejects raw page URL types before Livewire can coerce them into another query', function (array $query) {
    $this->get('/exoplanets?'.http_build_query($query))->assertOk()->assertSee('Reset filters and page')->assertDontSee('Download page as CSV');
    Http::assertNothingSent();
})->with([[['q' => ['Proxima']]], [['method' => ['Transit']]], [['distance' => ['10']]],
    [['page' => 'abc']], [['page' => '1.5']], [['page' => 'true']], [['page' => ['2']]]]);

it('preserves literal query strings which Livewire might otherwise JSON-decode', function () {
    fakeSolar();
    $this->get('/exoplanets?q=true')->assertOk();
    Http::assertSent(fn ($r) => ($r['q'] ?? null) === 'true');
});

it('rejects raw Livewire updates before typed properties can coerce them', function () {
    fakeSolar();
    Livewire::test(Exoplanets::class)->set('page', '1.5')->assertHasErrors(['page'])
        ->assertSet('page', '1.5')->assertDontSee('Download page as CSV')
        ->set('q', 'Proxima')->assertDontSee('Download page as CSV')
        ->set('page', 1)->assertSee('Download page as CSV');
});

it('keeps initial invalid fields visible until that field is corrected or reset', function () {
    Livewire::withQueryParams(['distance' => '5'])->test(Exoplanets::class)
        ->set('q', 'Proxima')->assertSee('selected distance is invalid')->assertDontSee('Download page as CSV');
    Http::assertNothingSent();
});

it('rejects booleans and floating-point page updates without loading a different page', function (mixed $page) {
    fakeSolar();
    Livewire::test(Exoplanets::class)->set('page', $page)->assertHasErrors(['page'])->assertDontSee('Download page as CSV');
    Http::assertSentCount(1);
})->with([true, false, 2.0, 1.5]);

it('preserves each valid literal filter when another initial filter is invalid', function () {
    fakeSolar();
    Livewire::withQueryParams(['q' => 'true', 'method' => 'false', 'distance' => '5'])->test(Exoplanets::class)
        ->assertSet('q', 'true')->assertSet('method', 'false')->assertDontSee('Download page as CSV')
        ->set('distance', '10')->assertSet('q', 'true')->assertSet('method', 'false')->assertSee('Download page as CSV');
    Http::assertSent(fn ($r) => $r['q'] === 'true' && $r['discovery_method'] === 'false' && $r['max_distance_pc'] === '10');
    Http::assertSentCount(1);
});

it('normalizes null text filter updates without unsetting Livewire properties', function (string $field) {
    fakeSolar();
    Livewire::test(Exoplanets::class)->set($field, null)->assertSet($field, '')->assertSee('Download page as CSV')->assertHasNoErrors();
})->with(['q', 'method', 'distance']);

it('rejects malformed text filter updates without rendering arrays or fetching different results', function (string $field, mixed $value) {
    fakeSolar();
    Livewire::test(Exoplanets::class)->set($field, $value)->assertHasErrors([$field])->assertDontSee('Download page as CSV')
        ->call('clearFilters')->assertSee('Download page as CSV');
    Http::assertSentCount(1);
})->with([
    ['q', ['unexpected']], ['method', ['Transit']], ['distance', ['10']],
    ['q', true], ['method', false], ['distance', 10],
]);

it('retains independent invalid URL fields until each has been corrected', function () {
    fakeSolar();
    Livewire::withQueryParams(['q' => ['unexpected'], 'distance' => '5'])->test(Exoplanets::class)
        ->assertDontSee('Download page as CSV')->set('distance', '10')->assertDontSee('Download page as CSV')
        ->set('q', 'true')->assertSee('Download page as CSV');
    Http::assertSent(fn ($r) => $r['q'] === 'true' && $r['max_distance_pc'] === '10');
    Http::assertSentCount(1);
});

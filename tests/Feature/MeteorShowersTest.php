<?php

declare(strict_types=1);

use App\Livewire\MeteorShowers;
use App\Services\SolarApi\SolarApiClient;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/** Mirrors solar_db/data_access.py, list_meteor_showers SELECT columns. */
function meteorParameterPayload(int $adNo = 0, int $iauNo = 4): array
{
    return [
        'iau_no' => $iauNo, 'ad_no' => $adNo, 'code' => 'GEM', 'name' => 'Geminids',
        'status_code' => 1, 'status_label' => 'single established shower, group',
        'activity' => 'annual', 'solar_longitude_deg' => 262.2, 'ra_deg' => 112.4,
        'dec_deg' => 32.0, 'dra_deg_per_day' => 0.9, 'ddec_deg_per_day' => -0.2,
        'vg_km_s' => 34.5, 'a_au' => 1.3, 'q_au' => 0.14, 'e' => 0.89,
        'peri_deg' => 324.0, 'node_deg' => 262.2, 'incl_deg' => 22.1, 'n_members' => 42,
        'shower_group' => null, 'parent_body' => 'Phaethon', 'parent_object_id' => 'ast-phaethon',
        'technique' => 'video', 'reference' => 'Observation campaign A',
        'submitted_on' => '2024-01-10', 'source' => 'IAU MDC',
    ];
}

/** The detail envelope differs from list items/count and contains ALL parameter sets. */
function meteorDetailPayload(array $sets): array
{
    return ['iau_no' => 4, 'code' => 'GEM', 'name' => 'Geminids',
        'status_label' => 'single established shower, group', 'parameter_sets' => $sets,
        'parent' => ['id' => 'ast-aggregate-parent', 'name' => 'Aggregate parent']];
}

function fakeMeteorCatalogue(array $rows = [], ?array $detail = null, int $status = 200): void
{
    Cache::flush();
    Http::preventStrayRequests();
    Http::fake(function ($request) use ($rows, $detail, $status) {
        $path = parse_url($request->url(), PHP_URL_PATH);
        if (str_ends_with($path, '/meteor-showers')) {
            return Http::response(['items' => $rows, 'count' => count($rows)], $status);
        }
        if (str_contains($path, '/meteor-showers/')) {
            return Http::response($detail ?? ['detail' => 'Not found'], $detail === null ? 404 : $status);
        }

        return Http::response(['status' => 'ok']);
    });
}

it('groups real items/count rows by IAU identity and keeps all matched parameter sets', function () {
    fakeMeteorCatalogue([meteorParameterPayload(), meteorParameterPayload(1), meteorParameterPayload(0, 5)]);
    $catalogue = app(SolarApiClient::class)->meteorShowers();
    expect($catalogue->parameterSetCount)->toBe(3)
        ->and($catalogue->showers)->toHaveCount(2)
        ->and($catalogue->showers[0]->parameterSets)->toHaveCount(2)
        ->and($catalogue->showers[0]->parameterSets[0]->measurements['vg_km_s'])->toBe(34.5)
        ->and($catalogue->possiblyTruncated)->toBeFalse();
});

it('shows grouped guest results and URL-bound filters using the backend contract', function () {
    fakeMeteorCatalogue([meteorParameterPayload(), meteorParameterPayload(1)]);
    $this->get('/meteor-showers?active_on=2026-12-14&established_only=1')
        ->assertOk()->assertSee('Geminids')->assertSee('2 matching parameter sets grouped into 1 showers.')
        ->assertSee('/meteor-showers/GEM', escape: false)->assertSee('15°');
    Http::assertSent(fn ($request) => str_contains($request->url(), '/meteor-showers')
        && $request['active_on'] === '2026-12-14'
        && $request['established_only'] === 'true' && $request['limit'] === 1000);
});

it('rejects invalid or non-exact dates without making a meteor query', function (string $date) {
    fakeMeteorCatalogue();
    Livewire::test(MeteorShowers::class)->set('activeOn', $date)
        ->assertSee('Enter a real date in YYYY-MM-DD format');
    Http::assertNotSent(fn ($request) => isset($request['active_on']));
})->with(['2026-02-30', '2026-2-03', '20261214', '2026-12-14T00:00:00Z', '0000-01-01', "2026-12-14\0"]);

it('accepts leap days and resets both filters', function () {
    fakeMeteorCatalogue([meteorParameterPayload()]);
    Livewire::test(MeteorShowers::class)->set('activeOn', '2028-02-29')->set('establishedOnly', true)
        ->assertDontSee('Enter a real date in YYYY-MM-DD format')->call('clearFilters')
        ->assertSet('activeOn', '')->assertSet('establishedOnly', false);
    Http::assertSent(fn ($request) => ($request['active_on'] ?? '') === '2028-02-29');
});

it('warns when the parameter-set cap may hide showers or split a group', function () {
    fakeMeteorCatalogue(array_map(fn ($n) => meteorParameterPayload($n), range(0, 999)));
    $this->get('/meteor-showers')->assertOk()->assertSee('1,000 parameter-set limit was reached')
        ->assertSee('may be incomplete')->assertSee('1000 matching parameter sets');
});

it('does not misrepresent an empty or absent-table response as an empty universe', function () {
    fakeMeteorCatalogue();
    $this->get('/meteor-showers')->assertOk()->assertSee('No shower parameter sets returned')
        ->assertSee('backend has not yet loaded');
});

it('degrades for older or unavailable list endpoints', function (int $status) {
    fakeMeteorCatalogue(status: $status);
    $this->get('/meteor-showers')->assertOk()->assertSee('reach the catalogue')
        ->assertDontSee('No shower parameter sets returned');
})->with([404, 503]);

it('does not silently treat a different envelope as an empty list', function () {
    Http::fake(['*/meteor-showers*' => Http::response(['results' => [], 'total' => 0]), '*' => Http::response(['status' => 'ok'])]);
    $this->get('/meteor-showers')->assertOk()->assertSee('reach the catalogue');
});

it('shows every detail parameter set and its own parent without trusting the aggregate parent', function () {
    $second = array_replace(meteorParameterPayload(1), ['parent_body' => 'Second parent', 'parent_object_id' => 'comet-second', 'status_code' => 2,
        'reference' => '<script>alert("source")</script>', 'solar_longitude_deg' => null, 'n_members' => 0]);
    fakeMeteorCatalogue(detail: meteorDetailPayload([meteorParameterPayload(), $second]));
    $this->get('/meteor-showers/GEM?active_on=2026-01-01&established_only=1')->assertOk()
        ->assertSee('2 parameter sets returned')->assertSee('Parameter set 0')->assertSee('Parameter set 1')
        ->assertSee('/objects/ast-phaethon', escape: false)->assertSee('/objects/comet-second', escape: false)
        ->assertDontSee('/objects/ast-aggregate-parent', escape: false)
        ->assertSee('alert("source")')->assertDontSee('<script>alert("source")</script>', escape: false)
        ->assertDontSee('&lt;script&gt;', escape: false)
        ->assertSee('Not reported')->assertSee('km/s')->assertSee('°/day')->assertSee('Observation campaign A');
});

it('does not invent a parent link or parameter values', function () {
    $row = array_replace(meteorParameterPayload(), ['parent_object_id' => null, 'parent_body' => 'Unlinked candidate']);
    fakeMeteorCatalogue(detail: meteorDetailPayload([$row]));
    $this->get('/meteor-showers/GEM')->assertOk()->assertSee('Unlinked candidate')
        ->assertDontSee('/objects/ast-aggregate-parent', escape: false)->assertDontSee('/objects/ast-phaethon', escape: false);
});

it('returns 404 for a shower code with no catalogue record and does not canonicalise that url', function (string $path) {
    fakeMeteorCatalogue();

    $this->get($path)
        ->assertNotFound()
        ->assertSee('Lost in space')
        ->assertDontSee('No shower record available')
        ->assertDontSee('rel="canonical"', false);
})->with([
    'unknown code' => ['/meteor-showers/ZZZZ'],
    'whitespace' => ['/meteor-showers/%20'],
]);

it('redirects a valid lowercase shower code once and serves the uppercase page with its own canonical', function (string $lower, string $upper) {
    $set = array_replace(meteorParameterPayload(), ['code' => $upper]);
    fakeMeteorCatalogue(detail: array_replace(meteorDetailPayload([$set]), ['code' => $upper]));

    $this->get('/meteor-showers/'.$lower)
        ->assertStatus(301)
        ->assertRedirect('/meteor-showers/'.$upper);

    $this->get('/meteor-showers/'.$upper)
        ->assertOk()
        ->assertSee('<link rel="canonical" href="'.url('/meteor-showers/'.$upper).'">', false);
})->with([
    'leonids' => ['leo', 'LEO'],
    'perseids' => ['per', 'PER'],
    'geminids' => ['gem', 'GEM'],
]);

it('handles an empty detail honestly', function () {
    fakeMeteorCatalogue(detail: meteorDetailPayload([]));
    $this->get('/meteor-showers/GEM')->assertOk()->assertSee('No parameter sets supplied');
});

it('handles a temporarily unavailable detail honestly', function () {
    fakeMeteorCatalogue(detail: meteorDetailPayload([]), status: 503);
    $this->get('/meteor-showers/GEM')->assertOk()->assertSee('reach the catalogue');
});

it('treats a successful non-record shower body as unavailable rather than missing', function (string $body) {
    Cache::flush();
    Http::preventStrayRequests();
    Http::fake(function ($request) use ($body) {
        $path = parse_url($request->url(), PHP_URL_PATH);
        if (str_contains((string) $path, '/meteor-showers/')) {
            return Http::response($body, 200, ['Content-Type' => 'application/json']);
        }

        return Http::response(['status' => 'ok']);
    });

    $this->get('/meteor-showers/GEM')->assertOk()
        ->assertSee('reach the catalogue')
        ->assertDontSee('Lost in space');
})->with([
    'invalid json' => ['{'],
    'json null' => ['null'],
    'json string' => ['"not-a-record"'],
]);

it('degrades rather than dropping or rendering a malformed campaign', function (string $field, mixed $value) {
    fakeMeteorCatalogue([array_replace(meteorParameterPayload(), [$field => $value])]);
    $this->get('/meteor-showers')->assertOk()->assertSee('reach the catalogue')
        ->assertDontSee('matching parameter sets grouped');
})->with([
    'missing name' => ['name', null], 'array name' => ['name', ['Geminids']],
    'empty code' => ['code', ''], 'array identifier' => ['iau_no', [4]],
    'fractional campaign number' => ['ad_no', 1.2], 'array reference' => ['reference', ['unsafe']],
    'array source' => ['source', ['MDC']], 'numeric parent ID' => ['parent_object_id', 42],
    'array measurement' => ['ra_deg', [112.4]], 'text measurement' => ['vg_km_s', 'unknown'],
    'fractional member count' => ['n_members', 4.5],
]);

it('rejects malformed detail identity and inconsistent campaign identities', function (array $overrides) {
    fakeMeteorCatalogue(detail: array_replace(meteorDetailPayload([meteorParameterPayload()]), $overrides));
    $this->get('/meteor-showers/GEM')->assertOk()->assertSee('reach the catalogue');
})->with([
    'missing title' => [['name' => null]],
    'array code' => [['code' => ['GEM']]],
    'invalid identity' => [['iau_no' => 'unknown']],
    'non-list campaigns' => [['parameter_sets' => ['first' => meteorParameterPayload()]]],
    'scalar campaign' => [['parameter_sets' => ['unknown']]],
    'inconsistent IAU identity' => [['parameter_sets' => [meteorParameterPayload(0, 5)]]],
    'malformed campaign reference' => [['parameter_sets' => [array_replace(meteorParameterPayload(), ['reference' => ['unexpected']])]]],
]);

it('rejects malformed list envelopes without changing the solar-system client', function (array $payload) {
    Http::fake([
        '*/meteor-showers*' => Http::response($payload),
        '*/objects/planet-saturn' => Http::response(saturnDetail()),
        '*' => Http::response(['status' => 'ok']),
    ]);
    $this->get('/meteor-showers')->assertOk()->assertSee('reach the catalogue');
    expect(app(SolarApiClient::class)->object('planet-saturn')->name)->toBe('Saturn');
})->with([
    'non-list items' => [['items' => ['first' => meteorParameterPayload()], 'count' => 1]],
    'incorrect returned count' => [['items' => [meteorParameterPayload()], 'count' => 2]],
    'missing count' => [['items' => []]],
    'scalar item' => [['items' => ['invalid'], 'count' => 1]],
]);
it('rejects malformed raw meteor query types without looking up another selection', function (array $query) {
    fakeMeteorCatalogue();
    $this->get('/meteor-showers?'.http_build_query($query))->assertOk()->assertSee('Reset filters');
    Http::assertNotSent(fn ($r) => str_contains($r->url(), '/meteor-showers'));
})->with([
    [['active_on' => ['2026-12-14']]], [['established_only' => ['1']]], [['established_only' => 'potato']],
]);

it('keeps malformed date errors until the date is corrected or filters reset', function () {
    fakeMeteorCatalogue([meteorParameterPayload()]);
    $component = Livewire::withQueryParams(['active_on' => ['2026-12-14']])->test(MeteorShowers::class);
    $component->set('establishedOnly', true)->assertSee('Choose a single activity date');
    Http::assertNotSent(fn ($r) => str_contains($r->url(), '/meteor-showers'));

    $component->set('activeOn', '2026-12-14')->assertDontSee('Choose a single activity date');
    Http::assertSent(fn ($r) => ($r['active_on'] ?? null) === '2026-12-14' && ($r['established_only'] ?? null) === 'true');
});

it('rejects invalid meteor Livewire update types before coercion', function (string $field, mixed $value) {
    fakeMeteorCatalogue([meteorParameterPayload()]);
    $component = Livewire::test(MeteorShowers::class);
    Cache::flush();
    Http::swap(new Factory);
    Http::preventStrayRequests();
    $component->set($field, $value)->assertHasErrors($field);
    Http::assertNothingSent();
})->with([['activeOn', ['2026-12-14']], ['activeOn', null], ['establishedOnly', 'potato']]);

it('displays readable citation text while preserving the raw source reference', function () {
    $reference = '<A href="javascript:alert(1)">Jopek &amp; colleagues</A> &lt;em&gt;(2024)&lt;/em&gt;';
    fakeMeteorCatalogue(detail: meteorDetailPayload([array_replace(meteorParameterPayload(), ['reference' => $reference])]));
    expect(app(SolarApiClient::class)->meteorShower('GEM')->parameterSets[0]->reference)->toBe($reference);
    $this->get('/meteor-showers/GEM')->assertOk()->assertSee('Jopek & colleagues (2024)')
        ->assertDontSee('javascript:alert(1)', escape: false)->assertDontSee('&lt;A', escape: false);
});

it('redirects any other shower-code case to the uppercase url in one hop', function (string $code, string $query) {
    fakeMeteorCatalogue(detail: meteorDetailPayload([meteorParameterPayload()]));
    $suffix = $query === '' ? '' : '?'.$query;
    $response = $this->get('/meteor-showers/'.$code.$suffix)->assertStatus(301);
    $response->assertRedirect('/meteor-showers/GEM'.$suffix);

    $this->get('/meteor-showers/GEM'.$suffix)->assertOk();
})->with([
    'lowercase' => ['gem', ''],
    'mixed case keeps the query string' => ['gEm', 'active_on=2026-12-14&established_only=1'],
]);

it('titles the uppercase shower page, keeps one h1, and self-canonicalises without the query', function () {
    $this->travelTo(new DateTimeImmutable('2026-11-14T18:20:00Z'));
    $annual = array_replace(meteorParameterPayload(), [
        'iau_no' => 13, 'code' => 'LEO', 'name' => 'Leonids', 'activity' => 'annual',
        'solar_longitude_deg' => 235.1, 'n_members' => 268,
    ]);
    $campaign = array_replace(meteorParameterPayload(1), [
        'iau_no' => 13, 'code' => 'leo', 'name' => 'Leonids', 'activity' => '2001',
        'solar_longitude_deg' => 236.5, 'n_members' => 9,
    ]);
    fakeMeteorCatalogue(detail: [
        'iau_no' => 13, 'code' => 'LEO', 'name' => 'Leonids',
        'status_label' => 'single established shower, group',
        'parameter_sets' => [$annual, $campaign],
        'parent' => ['id' => 'comet-55p-tempel-tuttle', 'name' => 'Tempel-Tuttle'],
    ]);

    $this->get('/meteor-showers/leo?active_on=2026-11-17')
        ->assertStatus(301)
        ->assertRedirect('/meteor-showers/LEO?active_on=2026-11-17');

    $html = $this->get('/meteor-showers/LEO?active_on=2026-11-17')->assertOk()->getContent();
    $title = 'Leonid Meteor Shower 2026: Peak Date, Best Time to See It and Where to Look';
    $description = 'Leonids 2026: peak dates, the best time to see them and where to look. Reported peak near solar longitude 235.1°. IAU Meteor Data Center measurements.';

    expect($html)->toContain('<title>'.$title.' · '.config('site.name').'</title>')
        ->toContain('<meta name="description" content="'.$description.'">')
        ->toContain('<link rel="canonical" href="'.url('/meteor-showers/LEO').'">')
        ->and(substr_count($html, '<h1'))->toBe(1)
        ->and($html)->toContain('>Leonids</h1>');
});

it('links the shower index at the uppercase code', function () {
    fakeMeteorCatalogue([array_replace(meteorParameterPayload(), [
        'code' => 'leo', 'name' => 'Leonids', 'iau_no' => 13,
    ])]);

    $this->get('/meteor-showers')->assertOk()
        ->assertSee('/meteor-showers/LEO', false)
        ->assertDontSee('/meteor-showers/leo', false);
});

it('normalizes accepted checkbox update values before querying the strict API client', function (mixed $value, bool $expected) {
    fakeMeteorCatalogue([meteorParameterPayload()]);
    Livewire::test(MeteorShowers::class)->set('establishedOnly', $value)
        ->assertSet('establishedOnly', $expected)->assertSee('Geminids');
    Http::assertSent(fn ($request) => str_contains($request->url(), '/meteor-showers')
        && $request['established_only'] === ($expected ? 'true' : 'false'));
})->with([[1, true], ['1', true], [0, false], ['0', false]]);

it('shows the UK viewing section above the parameter sets with FAQPage data', function () {
    $this->travelTo(new DateTimeImmutable('2026-11-26T10:00:00Z'));
    fakeMeteorCatalogue(detail: meteorDetailPayload([array_replace(meteorParameterPayload(5), [
        'solar_longitude_deg' => 262.0, 'ra_deg' => 113.5, 'dec_deg' => 32.3, 'n_members' => 4895,
    ])]));

    $html = $this->get('/meteor-showers/GEM')->assertOk()->getContent();

    expect($html)->toContain('When and where to see it from the UK')
        ->toContain('What time is the Geminid meteor shower tonight in the UK?')
        ->toContain('"@type":"FAQPage"')
        ->toContain('<title>Geminid Meteor Shower 2026: Peak Date, Best Time to See It and Where to Look')
        ->and(strpos($html, 'data-testid="meteor-uk-viewing"'))->toBeLessThan(strpos($html, 'Parameter set 5'));
});

it('renders configured copy notes for the shower', function () {
    $this->travelTo(new DateTimeImmutable('2026-11-26T10:00:00Z'));
    config(['meteor_viewing.showers.GEM.notes' => ['A hand-written note.']]);
    fakeMeteorCatalogue(detail: meteorDetailPayload([meteorParameterPayload()]));

    $this->get('/meteor-showers/GEM')->assertOk()->assertSee('A hand-written note.');
});

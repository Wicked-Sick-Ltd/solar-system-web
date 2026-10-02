<?php

use App\Http\Middleware\PrivateObservingShortlist;
use App\Services\Observing\ShortlistPlan;
use App\Services\Observing\ShortlistRequest;
use App\Services\SolarApi\Exceptions\SolarApiException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

function shortlistInput(): array
{
    return ['date' => '2026-10-01', 'timezone' => 'Europe/London', 'lat' => 51.5001, 'lon' => -0.1201,
        'min_altitude_deg' => 20, 'sun_altitude_deg' => -12, 'min_moon_separation_deg' => 0,
        'equipment_mode' => 'naked_eye', 'preference' => 'balanced', 'true_field_deg' => '', 'max_catalogue_v_magnitude' => '', 'shortlist_limit' => 3];
}

function shortlistFixture(): array
{
    return json_decode(gzdecode(file_get_contents(base_path('tests/fixtures/observing/shortlist.json.gz'))), true, 512, JSON_THROW_ON_ERROR);
}

it('offers a native private shortlist form without calling providers or requiring target identifiers', function () {
    Http::fake();
    $response = $this->get('/observe/shortlist')->assertOk()->assertSee('Find targets for a night')
        ->assertSee('method="POST"', false)->assertSee('name="equipment_mode"', false)->assertSee('name="_token"', false)
        ->assertDontSee('name="targets', false)->assertDontSee('name="catalogue_targets"', false)
        ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer');
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $forms = $dom->getElementsByTagName('form');
    $native = array_values(array_filter(iterator_to_array($forms), fn ($form) => $form->hasAttribute('data-shortlist-form')));
    expect($native)->toHaveCount(1);
    expect($native[0]->getElementsByTagName('form')->length)->toBe(0);
    Http::assertNothingSent();
});

it('renders actual bounded candidates and refined windows from one rounded private POST', function () {
    Http::fake(['*' => Http::response(shortlistFixture())]);
    $this->post('/observe/shortlist', shortlistInput())->assertOk()->assertSee('Your explained shortlist')
        ->assertSee('Moon')->assertSee('HR 1017')->assertSee('Altitude and direction sample table')
        ->assertSee('not promises of visibility')->assertSee('Unknown / not supplied')->assertSee('SHA-256')
        ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer')->assertSessionMissing('_old_input');
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->method() === 'POST' && str_ends_with($request->url(), '/observing/discover')
        && $request['lat'] === 51.5 && $request['lon'] === -0.12 && ! array_key_exists('targets', $request->data())
        && $request['equipment_mode'] === 'naked_eye' && $request['shortlist_limit'] === 3);
});

it('does not infer optional brightness or angular field values and retains valid zero magnitude', function () {
    $request = ShortlistRequest::parse(shortlistInput());
    expect($request['true_field_deg'])->toBeNull()->and($request['max_catalogue_v_magnitude'])->toBeNull();
    $request = ShortlistRequest::parse(array_replace(shortlistInput(), ['max_catalogue_v_magnitude' => '0', 'true_field_deg' => '0.01']));
    expect($request['max_catalogue_v_magnitude'])->toBe(0.0)->and($request['true_field_deg'])->toBe(0.01);
});

it('rejects invalid request types and unsupported constraints before any astronomy call', function (string $key, mixed $value) {
    Http::fake();
    $this->post('/observe/shortlist', array_replace(shortlistInput(), [$key => $value]))->assertStatus(422)
        ->assertDontSee('Your explained shortlist')->assertSessionMissing('_old_input')->assertHeader('Cache-Control', 'no-store, private');
    Http::assertNothingSent();
})->with([
    ['equipment_mode', true], ['equipment_mode', 'camera'], ['equipment_mode', ['telescope']],
    ['preference', []], ['preference', 'detectable'], ['shortlist_limit', 0], ['shortlist_limit', 9], ['shortlist_limit', true], ['shortlist_limit', 3.5], ['shortlist_limit', '03'],
    ['true_field_deg', 0], ['true_field_deg', 181], ['true_field_deg', true], ['true_field_deg', []], ['true_field_deg', '1e309'],
    ['max_catalogue_v_magnitude', false], ['max_catalogue_v_magnitude', []], ['max_catalogue_v_magnitude', 31],
    ['lat', true], ['lat', 'NaN'], ['lon', -181], ['date', '2026-02-30'], ['timezone', 'Bad/Zone'],
    ['targets', ['moon']], ['weather', true], ['window_start_utc', '2026-10-01T20:00:00Z'], ['horizon', '0 10'],
]);

it('rejects malformed and mismatched candidate science rather than suggesting it', function (string $path, mixed $value) {
    $fixture = shortlistFixture();
    data_set($fixture, $path, $value);
    Http::fake(['*' => Http::response($fixture)]);
    $this->post('/observe/shortlist', shortlistInput())->assertStatus(503)->assertDontSee('Your explained shortlist')
        ->assertSee('No substitute candidates')->assertHeader('Cache-Control', 'no-store, private');
})->with([
    ['schema_version', true], ['request.lat', true], ['request.lat', 52], ['request.date', '2026-10-02'], ['request.timezone', 'UTC'], ['request.horizon_mask', [[0, 10]]],
    ['discovery.options.equipment_mode', 'telescope'], ['discovery.options.true_field_deg', 0], ['discovery.options.shortlist_limit', true],
    ['discovery.sample_count', 74], ['discovery.incomplete_between_samples', false], ['discovery.coarse_step_seconds', 600],
    ['discovery.catalogue_records', []], ['discovery.catalogue_records', 159], ['discovery.unsupported_catalogue_records', 2], ['discovery.refined_candidates', 9], ['discovery.selected_without_refined_window', 1],
    ['discovery.brightness_excluded', 3], ['discovery.source_snapshots', []], ['discovery.calculation.source_sha256', 'invalid'],
    ['method.provider', 'made-up'], ['method.calculation', null], ['method.iers.end_utc', '2020-01-01T00:00:00Z'],
    ['candidates', []], ['candidates.0.id', 'sun'], ['candidates.0.name', 'Wrong target'], ['candidates.0.aliases', [false]],
    ['candidates.0.refined_status', 'no_matching_window'], ['candidates.0.coarse_matching_samples', true], ['candidates.0.coarse_matching_samples', 74],
    ['candidates.0.sampled_peak_altitude_deg', '65'], ['candidates.0.field_context', 'catalogue_extent_within_field'], ['candidates.0.brightness_status', 'catalogue_value'],
    ['candidates.1.appearance.magnitude', 0], ['plan.targets.0.samples.0.altitude_deg', 91], ['plan.targets.0.windows', []],
]);

it('keeps no-candidate results distinct from unavailable service and binds their empty response geometry', function () {
    $fixture = json_decode(gzdecode(file_get_contents(base_path('tests/fixtures/observing/shortlist-empty.json.gz'))), true, 512, JSON_THROW_ON_ERROR);
    $input = array_replace(shortlistInput(), ['max_catalogue_v_magnitude' => -30]);
    Http::fake(['*' => Http::response($fixture)]);
    $this->post('/observe/shortlist', $input)->assertOk()->assertSee('No confirmed shortlist from this bounded screening')
        ->assertSee('does not establish')->assertSee('Software and ephemeris identity')->assertDontSee('Altitude and direction sample table');
    $fixture['request']['date'] = '2026-10-02';
    expect(fn () => ShortlistPlan::validate($fixture, ShortlistRequest::parse($input)))->toThrow(SolarApiException::class);
});

it('refuses query locations malformed JSON and oversized bodies with private error headers', function () {
    Http::fake();
    $this->get('/observe/shortlist?lat=12.34567')->assertStatus(422)->assertDontSee('12.34567')
        ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer');
    foreach (['[false]', '{bad', str_repeat(' ', PrivateObservingShortlist::MAX_BYTES + 1)] as $raw) {
        $this->call('POST', '/observe/shortlist', [], [], [], ['CONTENT_TYPE' => 'application/json'], $raw)
            ->assertStatus(strlen($raw) > PrivateObservingShortlist::MAX_BYTES ? 413 : 422)->assertSessionMissing('_old_input')
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer');
    }
    Http::assertNothingSent();
});

it('preserves input with associated validation errors instead of flashing private coordinates', function () {
    Http::fake();
    $this->post('/observe/shortlist', array_replace(shortlistInput(), ['true_field_deg' => -1]))->assertStatus(422)
        ->assertSee('value="51.5001"', false)->assertSee('shortlist-error-field')->assertSee('aria-invalid="true"', false)->assertSessionMissing('_old_input');
    Http::assertNothingSent();
});

it('fails closed on upstream failures without exposing diagnostics or retrying per target', function (int $code) {
    Http::fake(['*' => Http::response(['error' => '/private/path'], $code)]);
    $this->post('/observe/shortlist', shortlistInput())->assertStatus(503)->assertDontSee('/private/path')->assertDontSee('Your explained shortlist');
    Http::assertSentCount(1);
})->with([302, 404, 422, 500, 503]);

it('requires CSRF and keeps throttle failures private', function () {
    Http::fake();
    $this->app->instance('env', 'production');
    $this->post('/observe/shortlist', shortlistInput())->assertStatus(419)
        ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer');
    $this->app->instance('env', 'testing');
    for ($i = 0; $i < 6; $i++) {
        $this->post('/observe/shortlist', [])->assertStatus(422);
    }
    $this->post('/observe/shortlist', [])->assertStatus(429)->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Referrer-Policy', 'no-referrer');
    Http::assertNothingSent();
});

it('escapes candidate aliases and preserves genuine unknown appearance rather than inventing it', function () {
    $fixture = shortlistFixture();
    $fixture['candidates'][0]['aliases'] = ['<script>alert("alias")</script>'];
    Http::fake(['*' => Http::response($fixture)]);
    $this->post('/observe/shortlist', shortlistInput())->assertOk()->assertDontSee('<script>alert("alias")</script>', false)
        ->assertSee('&lt;script&gt;alert', false)->assertSee('Angular extent is unknown');
});

it('offers a target-only native handoff to the manual planner without putting location or screening options in a URL', function () {
    Http::fake(['*' => Http::response(shortlistFixture())]);
    $response = $this->post('/observe/shortlist', shortlistInput())->assertOk();
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $links = (new DOMXPath($dom))->query('//a[@data-shortlist-manual-link]');
    expect($links->length)->toBe(1);
    $url = $links->item(0)->getAttribute('href');
    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    expect($query)->toBe(['targets' => array_column(shortlistFixture()['candidates'], 'id')]);
    $this->get($url)->assertOk()->assertSee('bsc5p:hr1017')->assertSee('value=""', false)->assertDontSee('Your night');
    Http::assertSentCount(1);
});

it('rejects non-POST bodies before any JSON normalization', function (string $method, string $raw, int $status) {
    $request = Request::create('/observe/shortlist', $method, [], [], [], ['CONTENT_TYPE' => 'application/json', 'CONTENT_LENGTH' => strlen($raw)], $raw);
    $called = false;
    $response = app(PrivateObservingShortlist::class)->handle($request, function () use (&$called) {
        $called = true;
        throw new RuntimeException('Must not reach normalization.');
    });
    expect($response->getStatusCode())->toBe($status)->and($called)->toBeFalse();
    $json = new ReflectionProperty($request, 'json');
    expect($json->getValue($request))->toBeNull();
})->with([
    ['GET', '{"unexpected":"body"}', 422],
    ['HEAD', '{"unexpected":"body"}', 422],
    ['GET', '{"unexpected":"'.str_repeat('x', 17000).'"}', 413],
    ['PUT', '{"unexpected":"body"}', 422],
]);

<?php

declare(strict_types=1);

use App\Services\SolarApi\Data\CloseApproach;
use App\Support\CloseApproachFormat;
use App\Support\UpcomingCloseApproach;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\CatalogueObservation;

beforeEach(function () {
    $this->travelTo('2026-10-10 12:00:00');
    fakeSolar();
});

it('shows the next pass within 10 lunar distances on the home page', function () {
    $response = $this->get('/')
        ->assertOk()
        ->assertSee('Next close approach')
        ->assertSee('2022 UP6')
        ->assertSee('href="'.route('objects.show', 'ast-sooner').'"', false)
        ->assertSee('15 October 2026, 20:59 UTC')
        ->assertSee('datetime="2026-10-15T20:59:00Z"', false)
        ->assertSee('Passes in 5 days 8 hours')
        ->assertSee('2.6 LD')
        ->assertSee('9 km/s')
        ->assertSee('Shown from this device’s time zone when the browser can read it.')
        ->assertSee('href="'.route('close-approaches').'"', false)
        ->assertSee('aria-live="polite"', false)
        ->assertSee('aria-atomic="true"', false)
        ->assertSee('prefers-reduced-motion: reduce', false)
        ->assertSee('upcomingPassClock', false)
        ->assertDontSee('2019 XF2')
        ->assertDontSee('No object page is published')
        ->assertDontSee('Approximate size');

    $html = $response->getContent();
    $clock = substr($html, (int) strpos($html, 'upcomingPassClock'), 2200);
    expect($clock)->toContain('prefers-reduced-motion')
        ->and($clock)->not->toContain('setInterval');

    $document = new DOMDocument;
    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $live = $xpath->query('//*[@id="next-pass-heading"]/ancestor::section//*[@aria-live="polite"]')->item(0);
    expect($live)->not->toBeNull()
        ->and(trim($live->textContent))->toBe('Passes in 5 days 8 hours')
        ->and($xpath->query('.//*[@data-pass-local]', $live))->toHaveCount(0);

    Http::assertSent(fn ($request) => str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/close-approaches')
        && $request['from'] === '2026-10-10'
        && $request['to'] === '2026-12-09'
        && $request['body'] === 'Earth'
        && $request['limit'] === 1000
        && abs((float) $request['max_dist_au'] - UpcomingCloseApproach::maxDistanceAu()) < 1e-9);
});

it('reuses the cached close-approach catalogue on the next home view', function () {
    $this->get('/')->assertOk();
    $sent = Http::recorded(fn ($request) => str_ends_with(parse_url($request->url(), PHP_URL_PATH) ?? '', '/close-approaches'))->count();

    $this->get('/')->assertOk()->assertSee('2022 UP6');

    expect(Http::recorded(fn ($request) => str_ends_with(parse_url($request->url(), PHP_URL_PATH) ?? '', '/close-approaches'))->count())
        ->toBe($sent)
        ->and($sent)->toBe(1);
});

it('links the catalogue object id from the close-approach row', function () {
    fakeHomePasses([
        passRow(['object_id' => 'ast-example', 'name' => 'Catalogued rock']),
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('href="'.route('objects.show', 'ast-example').'"', false)
        ->assertSee('Catalogued rock')
        ->assertDontSee('No object page is published');
});

it('shows measured size and omits speed when it was not reported', function () {
    fakeHomePasses([
        passRow(['name' => 'Catalogued body', 'radius_km' => 0.05, 'v_rel_km_s' => null, 'absolute_magnitude_h' => 22]),
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Catalogued body')
        ->assertSee('100 m')
        ->assertSee('Size')
        ->assertDontSee('Approximate size')
        ->assertDontSee('km/s')
        ->assertDontSee('albedo');
});

it('estimates size from absolute magnitude when no radius is recorded', function () {
    fakeHomePasses([
        passRow(['name' => 'Faint', 'radius_km' => null, 'absolute_magnitude_h' => 26, 'v_rel_km_s' => 12.34]),
    ]);
    $size = CloseApproachFormat::size(CloseApproach::fromArray([
        'name' => 'Faint', 'body' => 'Earth', 'cd_iso' => '2026-10-15T20:59:00Z', 'absolute_magnitude_h' => 26,
    ]));

    $this->get('/')
        ->assertOk()
        ->assertSee('Approximate size')
        ->assertSee($size['text'])
        ->assertSee($size['note'])
        ->assertSee('12.3 km/s');
});

it('explains an empty window without claiming the sky is clear', function () {
    fakeHomePasses([]);

    $this->get('/')
        ->assertOk()
        ->assertSee('No close approach in this window')
        ->assertSee('does not establish that no encounters occur')
        ->assertSee('href="'.route('close-approaches').'"', false)
        ->assertDontSee('Passes in')
        ->assertDontSee('temporarily unavailable');
});

it('ignores passes farther than 10 lunar distances', function () {
    fakeHomePasses([
        passRow(['name' => 'Too Far', 'dist_au' => UpcomingCloseApproach::maxDistanceAu() + 0.001]),
    ]);

    $this->get('/')->assertOk()->assertSee('No close approach in this window')->assertDontSee('Too Far');
});

it('keeps the rest of the home page up when close approaches fail', function () {
    fakeHomePasses([], status: 503);

    $this->get('/')
        ->assertOk()
        ->assertSee('Browse by kind')
        ->assertSee('The next close approach')
        ->assertSee('temporarily unavailable')
        ->assertDontSee('No close approach in this window')
        ->assertSee(number_format(15546));
});

it('says when the closest-encounter cap may hide an earlier pass', function () {
    $rows = [passRow([
        'object_id' => 'ast-soon', 'name' => 'Sooner Far', 'cd_iso' => '2026-10-12T00:00:00Z', 'dist_au' => 0.02,
    ])];
    for ($index = 1; $index < UpcomingCloseApproach::LIMIT; $index++) {
        $rows[] = passRow([
            'object_id' => 'ast-'.$index,
            'name' => 'Later '.$index,
            'cd_iso' => '2026-11-01T00:00:00Z',
            'dist_au' => 0.001,
        ]);
    }
    fakeHomePasses($rows);

    $this->get('/')
        ->assertOk()
        ->assertSee('Sooner Far')
        ->assertSee('closest '.UpcomingCloseApproach::LIMIT.' encounters')
        ->assertDontSee('Later 1');
});

/**
 * @param  list<array<string,mixed>>  $rows
 * @param  list<string>  $missingIds
 * @param  list<string>  $brokenIds
 */
function fakeHomePasses(array $rows, int $status = 200, array $missingIds = [], array $brokenIds = []): void
{
    config(['cache.default' => 'array']);
    Cache::flush();
    CatalogueObservation::prime();
    Http::swap(new Factory);
    Http::fake(function ($request) use ($rows, $status, $missingIds, $brokenIds) {
        $path = parse_url($request->url(), PHP_URL_PATH) ?? '';
        if (str_ends_with($path, '/close-approaches') && ! str_contains($path, '/objects/')) {
            return $status === 200
                ? Http::response(['results' => $rows])
                : Http::response(['detail' => 'down'], $status);
        }
        if (str_contains($path, '/stats')) {
            return Http::response(statsPayload());
        }
        if (preg_match('#/objects/(.+)$#', $path, $match)) {
            $id = rawurldecode($match[1]);
            if (in_array($id, $brokenIds, true)) {
                return Http::response(['detail' => 'boom'], 500);
            }
            if (in_array($id, $missingIds, true)) {
                return Http::response(['detail' => 'not found'], 404);
            }

            return Http::response(objectDetail($id));
        }

        return Http::response(['results' => []]);
    });
}

/** @param  array<string,mixed>  $overrides */
function passRow(array $overrides = []): array
{
    return array_replace([
        'object_id' => 'ast-example',
        'name' => 'Example',
        'designation' => '2026 AA',
        'body' => 'Earth',
        'cd_iso' => '2026-10-15T20:59:00Z',
        'dist_au' => 0.00672,
        'v_rel_km_s' => 9.02,
    ], $overrides);
}

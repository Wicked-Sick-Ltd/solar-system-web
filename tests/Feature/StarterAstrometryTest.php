<?php

declare(strict_types=1);

use App\Services\SolarApi\Data\StarterSource;
use App\Services\SolarApi\Data\StarterTarget;
use App\Services\SolarApi\Exceptions\SolarApiException;
use App\Services\SolarApi\StarterCatalogueClient;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\CatalogueObservation;

function astrometryFixture(): array
{
    return json_decode(file_get_contents(base_path('tests/fixtures/starter-astrometry.json')), true, flags: JSON_THROW_ON_ERROR);
}

function fakeAstrometry(array $fixture): void
{
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(function ($request) use ($fixture) {
        $id = basename(rawurldecode(parse_url($request->url(), PHP_URL_PATH)));
        foreach ($fixture['results'] as $row) {
            if ($row['id'] === $id) {
                return Http::response($row + ['provenance' => $fixture['sources'][$row['source']]]);
            }
        }

        return Http::response(['available' => true, 'results' => $fixture['results'], 'sources' => $fixture['sources'], 'total' => 3, 'limit' => 24, 'offset' => 0]);
    });
}

beforeEach(function () {
    $this->withoutVite();
    config(['cache.default' => 'array']);
    Cache::flush();
    CatalogueObservation::prime();
    fakeAstrometry(astrometryFixture());
});

it('renders verified frame reference epoch proper motion and exact evidence without a current-position claim', function () {
    $this->get('/observing-targets/bsc5p:hr2491')->assertOk()->assertSee('FK5')->assertSee('J2000.0')
        ->assertSee('Catalogue reference epoch')->assertSee('Individual observation epoch')->assertSee('Julian year 2000.0')
        ->assertSee('-0.553 arcsec/year')->assertSee('-1.205 arcsec/year')->assertSee('including cosine of declination')
        ->assertSee('has not been applied here')->assertSee('does not establish when individual observations were taken')
        ->assertSee('CDS VizieR')->assertSee('a3f83273ccaecf913a0d8e077413062c20ce1fc01ce890c80615a9572d5f1be9');
    $this->get('/observing-targets/openngc:NGC0224')->assertOk()->assertSee('ICRS')->assertSee('Not applicable to ICRS')
        ->assertSee('Static catalogue direction')->assertSee('107')->assertSee('CC BY-SA 4.0')->assertSee('GAVO Data Center');
    $this->get('/observing-targets')->assertOk()->assertSee('Coordinate-frame evidence');
});

it('keeps unmatched M45 browsable without inferring a frame', function () {
    $this->get('/observing-targets/openngc:Mel022')->assertOk()->assertSee('Coordinate frame unsupported for planning')
        ->assertSee('No matching row')->assertDontSee('Verified coordinate frame')->assertDontSee('Julian year 2000.0');
});

it('keeps old catalogues browsable with an explicit missing-metadata explanation', function () {
    $fixture = astrometryFixture();
    foreach ($fixture['results'] as &$row) {
        unset($row['astrometry']);
    }
    foreach ($fixture['sources'] as &$source) {
        unset($source['astrometry_evidence']);
    }
    fakeAstrometry($fixture);
    $this->get('/observing-targets/bsc5p:hr2491')->assertOk()->assertSee('metadata is unavailable in this catalogue version')
        ->assertDontSee('Verified coordinate frame')->assertDontSee('FK5');
});

it('distinguishes zero proper motion from missing and preserves tiny values', function () {
    $fixture = astrometryFixture();
    $fixture['results'][0]['source_data']['pmra'] = '0';
    $fixture['results'][0]['source_data']['pmdec'] = '0.00000001';
    $fixture['results'][0]['astrometry']['pm_ra_cosdec_arcsec_per_year'] = 0;
    $fixture['results'][0]['astrometry']['pm_dec_arcsec_per_year'] = 0.00000001;
    fakeAstrometry($fixture);
    $this->get('/observing-targets/bsc5p:hr2491')->assertOk()->assertSee('0 arcsec/year')->assertSee('10⁻⁸ arcsec/year');
    Cache::flush();
    CatalogueObservation::prime();
    $fixture['results'][0]['source_data']['pmra'] = '';
    $fixture['results'][0]['astrometry']['pm_ra_cosdec_arcsec_per_year'] = null;
    $fixture['results'][0]['astrometry']['motion_model'] = 'static_catalogue_direction';
    fakeAstrometry($fixture);
    $this->get('/observing-targets/bsc5p:hr2491')->assertOk()->assertSee('Not reported')->assertSee('Static catalogue direction');
});

it('rejects inconsistent metadata clearly before caching then recovers', function (string $key, mixed $value) {
    $fixture = astrometryFixture();
    data_set($fixture, $key, $value);
    fakeAstrometry($fixture);
    $this->get('/observing-targets')->assertStatus(503)->assertDontSee('No records match');
    fakeAstrometry(astrometryFixture());
    $this->get('/observing-targets')->assertOk();
})->with([
    'scalar metadata' => ['results.0.astrometry', 'FK5'],
    'null metadata' => ['results.0.astrometry', null],
    'wrong frame' => ['results.0.astrometry.frame', 'ICRS'],
    'wrong equinox' => ['results.0.astrometry.equinox', 'J1950.0'],
    'epoch string' => ['results.0.astrometry.reference_epoch_jyear', '2000.0'],
    'epoch boolean' => ['results.0.astrometry.reference_epoch_jyear', true],
    'epoch changed' => ['results.0.astrometry.reference_epoch_jyear', 2016.0],
    'invented observation date' => ['results.0.astrometry.observation_epoch_jyear', 2000.0],
    'propagated' => ['results.0.astrometry.coordinates_propagated', true],
    'wrong identity' => ['results.0.astrometry.matched_identifier', '2492'],
    'wrong raw identity' => ['results.0.source_data.hr', '2492'],
    'wrong source' => ['results.0.astrometry.evidence_source', 'openngc'],
    'numeric motion string' => ['results.0.astrometry.pm_ra_cosdec_arcsec_per_year', '0'],
    'raw motion mismatch' => ['results.0.astrometry.pm_ra_cosdec_arcsec_per_year', 0],
    'raw motion missing' => ['results.0.source_data.pmra', ''],
    'raw motion nonnumeric' => ['results.0.source_data.pmra', 'unknown'],
    'array motion' => ['results.0.astrometry.pm_ra_cosdec_arcsec_per_year', []],
    'boolean motion' => ['results.0.astrometry.pm_ra_cosdec_arcsec_per_year', false],
    'missing motion declared available' => ['results.0.astrometry.pm_ra_cosdec_arcsec_per_year', null],
    'deep sky invented motion' => ['results.1.astrometry.pm_ra_cosdec_arcsec_per_year', 0],
    'unsupported frame filled in' => ['results.2.astrometry.frame', 'ICRS'],
    'empty unsupported reason' => ['results.2.astrometry.unsupported_reason', ''],
    'null source evidence' => ['sources.bsc5p.astrometry_evidence', null],
    'bad evidence URL' => ['sources.bsc5p.astrometry_evidence.query_url', 'javascript:alert(1)'],
    'authenticated evidence URL' => ['sources.bsc5p.astrometry_evidence.query_url', 'https://user:password@example.test'],
    'bad evidence hash' => ['sources.bsc5p.astrometry_evidence.response_sha256', 'bad'],
    'invented matched count' => ['sources.bsc5p.astrometry_evidence.matched_records', 51],
    'wrong evidence dataset' => ['sources.bsc5p.astrometry_evidence.dataset', 'other'],
    'inconsistent unsupported IDs' => ['sources.openngc.astrometry_evidence.unsupported_identifiers', []],
]);

it('rejects a partially missing contract instead of treating it as a legacy catalogue', function () {
    $fixture = astrometryFixture();
    unset($fixture['results'][0]['astrometry']);
    fakeAstrometry($fixture);
    $this->get('/observing-targets')->assertStatus(503);
    $fixture = astrometryFixture();
    unset($fixture['results'][0]['astrometry']['observation_epoch_jyear']);
    fakeAstrometry($fixture);
    $this->get('/observing-targets/bsc5p:hr2491')->assertStatus(503);
});

it('escapes publisher and unsupported reason strings', function () {
    $fixture = astrometryFixture();
    $fixture['sources']['openngc']['astrometry_evidence']['authority'] = '<img src=x onerror=alert(1)>';
    $fixture['results'][2]['astrometry']['unsupported_reason'] = '<script>alert(1)</script>';
    fakeAstrometry($fixture);
    $this->get('/observing-targets/openngc:Mel022')->assertOk()->assertSee('&lt;script&gt;', false)
        ->assertSee('&lt;img', false)->assertDontSee('<img src=x', false)->assertDontSee('<script>alert(1)</script>', false);
});

it('does not reuse old serialized DTO cache entries after the new contract', function () {
    $key = 'starter-catalogue:v1:'.hash('sha256', (string) config('services.solar.base_url').'detail'.json_encode(['bsc5p:hr2491']));
    Cache::put($key, 'old serialized DTO placeholder', 3600);
    expect(app(StarterCatalogueClient::class)->target('bsc5p:hr2491')->astrometry->data['frame'])->toBe('FK5');
    Http::assertSentCount(1);
});

it('rejects nonfinite motion values at the DTO boundary', function (float $value) {
    $fixture = astrometryFixture();
    $row = $fixture['results'][0];
    $row['astrometry']['pm_ra_cosdec_arcsec_per_year'] = $value;
    $source = StarterSource::fromArray($fixture['sources']['bsc5p'], 'bsc5p');
    expect(fn () => StarterTarget::fromArray($row, $source))
        ->toThrow(SolarApiException::class);
})->with([INF, -INF, NAN]);

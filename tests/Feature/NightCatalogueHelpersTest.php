<?php

declare(strict_types=1);

use App\Services\Observing\NightCatalogueProvenance;
use App\Services\Observing\NightProviderProvenance;
use App\Services\Observing\NightTargets;
use App\Services\SolarApi\Data\StarterSource;
use App\Services\SolarApi\Data\StarterTarget;
use App\Services\SolarApi\Exceptions\SolarApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

function nightProvenanceFixture(): array
{
    return json_decode(file_get_contents(base_path('tests/fixtures/observing/catalogue-provenance.json')), true, flags: JSON_THROW_ON_ERROR);
}

it('preserves eight exact mixed identifiers and resolves journal namespaces without guessing a body', function () {
    Http::preventStrayRequests();
    $ids = ['moon', 'mercury', 'venus', 'mars', 'jupiter', 'saturn', 'bsc5p:hr2491', 'openngc:NGC0224'];
    expect(NightTargets::parse($ids))->toBe($ids)
        ->and(NightTargets::hints(['targets' => $ids]))->toBe($ids)
        ->and(NightTargets::hints(['lat' => '51.5', 'unrelated' => []]))->toBe([])
        ->and(NightTargets::journalIdentity('moon'))->toBe(['catalogue' => 'solar', 'id' => 'moon-luna'])
        ->and(NightTargets::journalIdentity('jupiter'))->toBe(['catalogue' => 'solar', 'id' => 'planet-jupiter'])
        ->and(NightTargets::journalIdentity('bsc5p:hr2491'))->toBe(['catalogue' => 'starter', 'id' => 'bsc5p:hr2491'])
        ->and(NightTargets::journalIdentity('openngc:NGC0224'))->toBe(['catalogue' => 'starter', 'id' => 'openngc:NGC0224']);
    Http::assertNothingSent();
});

it('rejects malformed ambiguous or duplicate target selections without coercion', function (mixed $value) {
    expect(fn () => NightTargets::parse($value))->toThrow(ValidationException::class)
        ->and(fn () => NightTargets::hints(['targets' => $value]))->toThrow(ValidationException::class);
})->with([
    [null], [true], ['moon'], [[]], [['first' => 'moon']], [['moon', 'moon']], [array_fill(0, 9, 'moon')],
    [['Moon']], [['planet-jupiter']], [['moon-luna']], [['sun']], [['earth']], [[true]], [[null]], [[['moon']]],
    [[' bsc5p:hr2491']], [['bsc5p:hr02491']], [['bsc5p:HR2491']], [['bsc5p:hr10000']], [['openngc:NGC-0224']],
    [['openngc:'.str_repeat('N', 31)]], [['openngc:../secret']], [['openngc:NGC0224'."\n"]],
]);

it('does not mistake syntax validation for membership of a particular snapshot', function () {
    expect(NightTargets::parse(['bsc5p:hr9999']))->toBe(['bsc5p:hr9999']);
    expect(fn () => NightTargets::journalIdentity('planet-earth'))->toThrow(SolarApiException::class);
});

it('distinguishes null catalogue distance from missing numeric or coerced values', function () {
    expect(NightTargets::distance('bsc5p:hr2491', ['distance_au' => null]))->toBeNull()
        ->and(NightTargets::distance('openngc:NGC0224', ['distance_au' => null]))->toBeNull()
        ->and(NightTargets::distance('moon', ['distance_au' => 0.00257]))->toBe(0.00257);
    foreach ([[], ['distance_au' => 0], ['distance_au' => 1], ['distance_au' => false], ['distance_au' => 'null']] as $sample) {
        expect(fn () => NightTargets::distance('bsc5p:hr2491', $sample))->toThrow(SolarApiException::class);
    }
    foreach ([null, 0, -1, true, '0.00257', INF, NAN] as $value) {
        expect(fn () => NightTargets::distance('moon', ['distance_au' => $value]))->toThrow(SolarApiException::class);
    }
});

it('keeps actual catalogue provenance and absence unchanged including credit and the frame-spin limitation', function () {
    foreach (nightProvenanceFixture()['catalogues'] as $id => $data) {
        expect(NightCatalogueProvenance::validate($data, $id))->toBe($data);
        expect($data['distance_au'])->toBeNull()->and($data['input_coordinates']['observation_epoch_jyear'])->toBeNull();
    }
    $star = nightProvenanceFixture()['catalogues']['bsc5p:hr2491'];
    expect($star['frame_transform'])->toContain('frame-spin correction not applied');
    $star['pm_ra_cosdec_arcsec_per_year'] = 0;
    $star['pm_dec_arcsec_per_year'] = -0.00000000001;
    expect(NightCatalogueProvenance::validate($star, 'bsc5p:hr2491'))->toBe($star);
});

it('rejects inconsistent or unsafe calculated catalogue provenance', function (string $path, mixed $value) {
    $data = nightProvenanceFixture()['catalogues']['bsc5p:hr2491'];
    data_set($data, $path, $value);
    expect(fn () => NightCatalogueProvenance::validate($data, 'bsc5p:hr2491'))->toThrow(SolarApiException::class);
})->with([
    ['source', 'openngc'], ['snapshot_sha256', 'unknown'], ['upstream_sha256.bsc5p.tdat', 'bad'],
    ['source_url', 'javascript:alert(1)'], ['license_url', 'https://user:password@example.test/license'],
    ['source_url', 'http://example.test/'], ['attribution', null], ['frame_transform', ''], ['accuracy_note', []],
    ['retrieved_at', 'next Tuesday'], ['retrieved_at', '2026-02-30T00:00:00Z'], ['retrieved_at', '2026-10-01'],
    ['astrometry_evidence.dataset', 'openngc.data'], ['astrometry_evidence.response_sha256', true],
    ['astrometry_evidence.matched_records', true], ['astrometry_evidence.query_url', 'file:///private/file'],
    ['astrometry_evidence.unsupported_identifiers', ['2491']], ['astrometry_evidence.retrieved_at', '2026-02-30'],
    ['input_coordinates.ra_deg', 360], ['input_coordinates.dec_deg', -91], ['input_coordinates.ra_deg', '101.2871'],
    ['input_coordinates.frame', 'ICRS'], ['input_coordinates.equinox', null], ['input_coordinates.reference_epoch_jyear', '2000'],
    ['input_coordinates.observation_epoch_jyear', 2000], ['motion_model', 'static_catalogue_direction'],
    ['proper_motion_applied', 1], ['pm_ra_cosdec_arcsec_per_year', null], ['pm_dec_arcsec_per_year', INF],
    ['distance_au', 1], ['private_path', '/private/file'], ['input_coordinates.private_path', '/private/file'],
    ['astrometry_evidence.private_path', '/private/file'],
]);

it('requires explicit unknown fields and never turns static unknown motion into measured zero', function () {
    $data = nightProvenanceFixture()['catalogues']['openngc:NGC0224'];
    $data['pm_dec_arcsec_per_year'] = 0;
    expect(fn () => NightCatalogueProvenance::validate($data, 'openngc:NGC0224'))->toThrow(SolarApiException::class);
    foreach (['distance_au', 'frame_transform', 'attribution'] as $key) {
        $data = nightProvenanceFixture()['catalogues']['openngc:NGC0224'];
        unset($data[$key]);
        expect(fn () => NightCatalogueProvenance::validate($data, 'openngc:NGC0224'))->toThrow(SolarApiException::class);
    }
});

it('retains provider kernel and IERS identities without hardcoding a library release', function () {
    foreach (nightProvenanceFixture()['providers'] as $method) {
        expect(NightProviderProvenance::validate($method))->toBe($method);
        $method['astropy_version'] = '9.0.0rc1';
        $method['erfa_version'] = '3.0.0+local';
        expect(NightProviderProvenance::validate($method))->toBe($method);
    }
});

it('refuses incomplete contradictory or private provider metadata', function (string $path, mixed $value) {
    $method = nightProvenanceFixture()['providers']['jpl'];
    data_set($method, $path, $value);
    expect(fn () => NightProviderProvenance::validate($method))->toThrow(SolarApiException::class);
})->with([
    ['provider', 'unknown'], ['ephemeris', 'ERFA builtin'], ['astropy_version', []], ['erfa_version', ''],
    ['iers.data_version', null], ['iers.status', 'unknown'], ['iers.start_utc', '2026-02-30T00:00:00Z'],
    ['iers.end_utc', '1900-01-01T00:00:00Z'], ['iers.snapshot.sha256', 'missing'],
    ['iers.snapshot.algorithm', 'file-hash'], ['iers.snapshot.columns', ['MJD']],
    ['iers.private_path', '/private/iers'], ['kernel.name', '../../kernel'], ['kernel.sha256', false],
    ['kernel.size_bytes', '32726016'], ['kernel.size_bytes', 0], ['kernel.source_url', 'http://example.test/kernel'],
    ['kernel.start_tdb', '1849-12-26T00:00:00Z'], ['kernel.end_tdb', '1900-02-30T00:00:00.000'],
    ['kernel.end_tdb', '1800-01-01T00:00:00.000'], ['kernel.private_path', '/private/kernel'],
    ['jplephem_version', null], ['sample_minutes', true], ['root_tolerance_seconds', 0], ['private', 'secret'],
]);

it('keeps legacy missing method provenance unavailable instead of inferring a snapshot', function () {
    $legacy = nightProvenanceFixture()['providers']['builtin'];
    unset($legacy['erfa_version'], $legacy['iers']['data_version'], $legacy['iers']['snapshot']);
    expect(fn () => NightProviderProvenance::validate($legacy))->toThrow(SolarApiException::class);
    $builtin = nightProvenanceFixture()['providers']['builtin'];
    $builtin['kernel'] = nightProvenanceFixture()['providers']['jpl']['kernel'];
    expect(fn () => NightProviderProvenance::validate($builtin))->toThrow(SolarApiException::class);
});

it('offers planning only for supported verified catalogue directions with required motion', function () {
    $fixture = json_decode(file_get_contents(base_path('tests/fixtures/starter-astrometry.json')), true, flags: JSON_THROW_ON_ERROR);
    foreach ($fixture['results'] as $row) {
        $target = StarterTarget::fromArray($row, StarterSource::fromArray($fixture['sources'][$row['source']], $row['source']));
        expect(NightTargets::canPlan($target))->toBe($row['id'] !== 'openngc:Mel022');
    }
    $row = $fixture['results'][0];
    $source = $fixture['sources'][$row['source']];
    $row['source_data']['pmra'] = '';
    $row['astrometry']['pm_ra_cosdec_arcsec_per_year'] = null;
    $row['astrometry']['motion_model'] = 'static_catalogue_direction';
    expect(NightTargets::canPlan(StarterTarget::fromArray($row, StarterSource::fromArray($source, $row['source']))))->toBeFalse();
    unset($row['astrometry'], $source['astrometry_evidence']);
    expect(NightTargets::canPlan(StarterTarget::fromArray($row, StarterSource::fromArray($source, $row['source']))))->toBeFalse();
});

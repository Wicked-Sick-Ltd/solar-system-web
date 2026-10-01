<?php

declare(strict_types=1);

use App\Services\SolarApi\SolarApiClient;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    fakeSolar();
    Http::swap(new Factory);
    Http::preventStrayRequests();
});

it('rejects malformed galaxy coverage rather than claiming an empty catalogue', function (array $payload) {
    Http::fake(['*/galaxy' => Http::response($payload)]);
    $this->get('/galaxy/data')->assertStatus(503)->assertJsonMissingPath('hosts');
    $this->get('/galaxy')->assertOk()->assertSee('unavailable');
})->with([
    'missing results' => [['available' => true]],
    'truthy availability' => [['available' => 'false', 'results' => []]],
    'non-list results' => [['available' => true, 'results' => ['host' => exoplanetHostPayload()]]],
    'invalid unmapped count' => [['available' => true, 'results' => [], 'unmapped_hosts' => -1]],
    'invalid truncation' => [['available' => true, 'results' => [], 'truncated' => 'false']],
    'oversized response' => [['available' => true, 'results' => array_fill(0, 10001, exoplanetHostPayload())]],
    'duplicate identities' => [['available' => true, 'results' => [exoplanetHostPayload(), exoplanetHostPayload()]]],
]);

it('fails clearly on malformed host rows without dropping them from results', function (string $field, mixed $value) {
    $host = array_replace(exoplanetHostPayload(), [$field => $value]);
    Http::fake(['*/galaxy' => Http::response(['available' => true, 'results' => [$host]])]);
    $this->get('/galaxy/data')->assertStatus(503);
})->with([
    ['id', null], ['id', []], ['name', ''], ['name', ['Proxima']],
    ['distance_pc', 0], ['distance_pc', -1], ['distance_pc', '1.3'],
    ['x_pc', null], ['y_pc', []], ['z_pc', 'unknown'],
    ['galactocentric_x_pc', null], ['galactocentric_y_pc', false], ['galactocentric_z_pc', []],
    ['planet_count', 1.5], ['planet_count', -1], ['planet_count', true],
    ['distance_error_plus_pc', '0.1'], ['distance_error_minus_pc', []],
]);

it('preserves zero coordinates and missing or one-sided uncertainties', function () {
    $host = array_replace(exoplanetHostPayload(), ['x_pc' => 0, 'distance_error_plus_pc' => 0.0000001]);
    unset($host['distance_error_minus_pc']);
    Http::fake(['*/galaxy' => Http::response(['available' => true, 'results' => [$host]])]);
    $map = app(SolarApiClient::class)->galaxyMap();
    expect($map->hosts[0]['x_pc'])->toBe(0)
        ->and($map->hosts[0]['distance_error_plus_pc'])->toBe(0.0000001)
        ->and($map->hosts[0])->not->toHaveKey('distance_error_minus_pc')
        ->and($map->unmappedHosts)->toBeNull()
        ->and($map->truncated)->toBeNull();
    $this->get('/galaxy')->assertOk()->assertSee('omitted: not reported')->assertSee('did not report whether its map limit');
});

<?php

declare(strict_types=1);

use App\Services\SolarApi\SolarApiClient;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $path = getenv('CATALOGUE_CONTRACT_PATH') ?: base_path('tests/fixtures/catalogue-contract.json');
    $this->contract = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    config(['cache.default' => 'array']);
    Cache::flush();
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(function ($request) {
        $path = parse_url($request->url(), PHP_URL_PATH);
        $c = $this->contract;
        $key = match ($path) {
            '/api/v1/exoplanets' => 'exoplanets',
            '/api/v1/exoplanets/'.$c['exoplanet']['id'] => 'exoplanet',
            '/api/v1/exoplanet-hosts/'.$c['host']['id'] => 'host',
            '/api/v1/galaxy' => 'galaxy',
            '/api/v1/meteor-showers' => 'meteor_showers',
            '/api/v1/meteor-showers/GEM' => 'meteor_shower',
            '/api/v1/objects' => ($request['after'] ?? '') === '' ? 'objects' : 'next_objects',
            default => null,
        };

        if ($key !== null) {
            $expectedQuery = match ($key) {
                'exoplanets' => ['q' => 'Proxima', 'limit' => '25', 'offset' => '0'],
                'meteor_showers' => ['established_only' => 'true', 'limit' => '1000'],
                'objects', 'next_objects' => ['type' => 'asteroid', 'limit' => '25', 'offset' => '0',
                    'after' => $key === 'objects' ? '' : $c['objects']['results'][23]['id']],
                default => [],
            };
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $actualQuery);
            ksort($actualQuery);
            ksort($expectedQuery);
            expect($request->method())->toBe('GET')->and($actualQuery)->toBe($expectedQuery);
        }

        // Match the real JSON wire representation, including integral floats.
        return $key === null ? Http::response([], 404) : Http::response(
            json_encode($c[$key], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
            200, ['Content-Type' => 'application/json'],
        );
    });
});

it('consumes real offline exoplanet REST and MCP records without losing source values', function () {
    $api = app(SolarApiClient::class);
    $list = $api->exoplanets(['q' => 'Proxima']);
    $expected = $this->contract['exoplanet'];
    $planet = $api->exoplanet($expected['id']);

    expect($list->items[0]->id)->toBe($expected['id'])
        ->and($planet->measurements)->toBe($expected['source_data'])
        ->and($planet->retrievedAt)->toBe($expected['retrieved_at'])
        ->and($api->exoplanetHost($expected['host_id'])->id)->toBe($expected['host_id'])
        ->and(count($api->galaxyMap()->hosts))->toBe(count($this->contract['galaxy']['results']));

    $this->get('/exoplanets/'.$expected['id'])->assertOk()->assertSee($expected['name']);
    $this->get('/systems/'.$expected['host_id'])->assertOk()->assertSee($expected['host_name']);
});

it('preserves every meteor parameter set and the backend grouping contract', function () {
    $api = app(SolarApiClient::class);
    $list = $api->meteorShowers(true);
    $detail = $api->meteorShower('GEM');

    expect($list->parameterSetCount)->toBe(count($this->contract['meteor_showers']['items']))
        ->and($detail->code)->toBe('GEM')
        ->and(count($detail->parameterSets))->toBe(count($this->contract['meteor_shower']['parameter_sets']));
    foreach ($detail->parameterSets as $i => $set) {
        $row = $this->contract['meteor_shower']['parameter_sets'][$i];
        expect($set->adNo)->toBe($row['ad_no'])
            ->and($set->parentObjectId)->toBe($row['parent_object_id'])
            ->and($set->reference)->toBe($row['reference']);
    }
    $this->get('/meteor-showers/GEM')->assertOk()->assertSee($this->contract['meteor_shower']['name']);
});

it('continues asteroid cursor pages at the displayed boundary of real backend records', function () {
    $api = app(SolarApiClient::class);
    $first = $api->objects(['type' => 'asteroid'], 24, 0, '');
    $second = $api->objects(['type' => 'asteroid'], 24, 0, $first->nextAfter);

    expect($first->count())->toBe(24)->and($first->hasMore)->toBeTrue()
        ->and($first->nextAfter)->toBe($this->contract['objects']['results'][23]['id'])
        ->and($second->items[0]->id)->toBe($this->contract['objects']['results'][24]['id']);
    expect(array_intersect(array_column($first->items, 'id'), array_column($second->items, 'id')))->toBe([]);
});

<?php

use App\Livewire\Category;
use App\Services\SolarApi\SolarApiClient;
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

it('forwards every URL-bound asteroid filter using the existing API contract', function () {
    fakeSolar();
    $query = ['orbit' => 'APO', 'neo' => 1, 'pha' => 1, 'named' => 1, 'diameter' => '10', 'moid' => '0.05', 'quality' => '2', 'discovered' => '2000'];
    $this->get('/asteroids?'.http_build_query($query))->assertOk()->assertSee('Filter asteroids')->assertSee('Page 1');
    Http::assertSent(fn ($r) => str_contains($r->url(), '/objects') && $r['type'] === 'asteroid'
        && $r['orbit_class'] === 'APO' && $r['neo'] === 'true' && $r['pha'] === 'true' && $r['named_only'] === 'true'
        && $r['min_diameter_km'] === '10' && $r['max_moid_au'] === '0.05' && $r['max_condition_code'] === '2'
        && $r['discovered_after'] === '2000-01-01');
    Livewire::withQueryParams($query)->test(Category::class, ['kind' => 'asteroid'])
        ->assertSet('orbit', 'APO')->assertSet('neo', true)->assertSet('pha', true)->assertSet('named', true)
        ->assertSet('diameter', '10')->assertSet('moid', '0.05')->assertSet('quality', '2')->assertSet('discovered', '2000');
});

it('resets page and cursor on filter changes and clears all filters', function () {
    fakeSolar();
    Livewire::test(Category::class, ['kind' => 'asteroid'])->set('order', 'id')->set('after', 'asteroid-123')
        ->set('page', 3)->set('neo', true)->assertSet('page', 1)->assertSet('after', '')
        ->set('orbit', 'APO')->set('pha', true)->set('named', true)->set('diameter', '1')
        ->set('moid', '0.1')->set('quality', '5')->set('discovered', '2020')->call('clearFilters')
        ->assertSet('orbit', '')->assertSet('neo', false)->assertSet('pha', false)->assertSet('named', false)
        ->assertSet('diameter', '')->assertSet('moid', '')->assertSet('quality', '')->assertSet('discovered', '')
        ->assertSet('page', 1)->assertSet('after', '')->assertSet('order', 'id');
});

it('preserves legacy orbital ordering and offers an explicit restart at the offset bound', function () {
    fakeSolar();
    $this->get('/asteroids?page=417&neo=1')->assertOk()->assertSee('Page 417')
        ->assertSee('Browse full catalogue by ID from the beginning')
        ->assertSee(route('asteroids', ['neo' => 1, 'order' => 'id']))
        ->assertDontSee('page=418', false);
    Http::assertSent(fn ($r) => str_contains($r->url(), '/objects') && $r['offset'] === 9984 && ! isset($r['after']));
});

it('starts ID ordering explicitly with an empty cursor and uses the last displayed row', function () {
    $rows = array_map(fn ($i) => ['id' => sprintf('asteroid-%03d', $i), 'name' => 'Asteroid '.$i, 'object_type' => 'asteroid'], range(1, 50));
    Http::fake(function ($request) use ($rows) {
        $after = $request['after'] ?? null;
        expect($after)->not->toBeNull();
        $matches = array_values(array_filter($rows, fn ($row) => $row['id'] > $after));
        $page = array_slice($matches, 0, $request['limit']);

        return Http::response(['results' => $page, 'next_after' => $page[array_key_last($page)]['id'] ?? null]);
    });
    $first = app(SolarApiClient::class)->objects(['type' => 'asteroid'], 24, 0, '');
    $second = app(SolarApiClient::class)->objects(['type' => 'asteroid'], 24, 0, $first->nextAfter);
    $last = app(SolarApiClient::class)->objects(['type' => 'asteroid'], 24, 0, $second->nextAfter);
    expect($first->nextAfter)->toBe('asteroid-024')->and($second->items[0]->id)->toBe('asteroid-025')
        ->and($second->nextAfter)->toBe('asteroid-048')->and($last->hasMore)->toBeFalse()->and($last->nextAfter)->toBeNull();
    $ids = array_map(fn ($row) => $row->id, [...$first->items, ...$second->items, ...$last->items]);
    expect($ids)->toBe(array_column($rows, 'id'));

    $this->get('/asteroids?order=id&neo=1')->assertOk()->assertSee('catalogue ID order')
        ->assertSee(route('asteroids', ['neo' => 1, 'order' => 'id', 'after' => 'asteroid-024']))->assertDontSee('Page 1');
});

it('loads a deep shared cursor directly with bounded offset and retained filters', function () {
    Http::fake(['*/objects*' => Http::response(['results' => objectRows(25), 'next_after' => 'object-24'])]);
    $this->get('/asteroids?order=id&after=asteroid-900000&orbit=MBA')->assertOk()->assertSee('catalogue ID order');
    Http::assertSent(fn ($r) => str_contains($r->url(), '/objects') && $r['after'] === 'asteroid-900000' && $r['offset'] === 0 && $r['orbit_class'] === 'MBA');
});

it('rejects invalid filter and paging URLs without querying the backend', function (array $query) {
    $this->get('/asteroids?'.http_build_query($query))->assertOk()->assertSee('Reset filters and position');
    Http::assertNothingSent();
})->with([
    [['orbit' => 'invented']], [['diameter' => '-1']], [['moid' => 'nan']], [['quality' => '10']],
    [['discovered' => '2020-99']], [['discovered' => '1599']], [['order' => 'random']],
    [['page' => 0]], [['page' => 418]], [['page' => PHP_INT_MAX]],
    [['order' => 'id', 'after' => str_repeat('a', 201)]], [['after' => 'asteroid-1']],
]);

it('offers recovery for an empty filtered page and distinct backend unavailability', function () {
    Http::fake(['*/objects*' => Http::response(['results' => []])]);
    $this->get('/asteroids?neo=1')->assertOk()->assertSee('No asteroids match these filters')
        ->assertSee('First results with these filters')->assertSee(route('asteroids', ['neo' => 1, 'page' => 1]))
        ->assertSee('Search by name or designation');
    Http::swap(new Factory);
    Cache::flush();
    Http::fake(['*/objects*' => Http::response([], 503)]);
    $this->get('/asteroids?neo=1')->assertOk()->assertSee('temporarily unavailable')->assertDontSee('No asteroids match');
});

it('keeps asteroid controls and filters off other category pages', function () {
    fakeSolar();
    $this->get('/comets?orbit=APO&neo=1&order=id&after=asteroid-100')->assertOk()->assertDontSee('Filter asteroids');
    Http::assertSent(fn ($r) => str_contains($r->url(), '/objects') && $r['type'] === 'comet' && ! isset($r['orbit_class']) && ! isset($r['neo']) && ! isset($r['after']));
});

it('does not silently restart full traversal on an older endpoint without cursor support', function () {
    Http::fake(['*/objects*' => Http::response(['results' => objectRows(25)])]);
    $this->get('/asteroids?order=id&after=asteroid-900000')->assertOk()->assertSee('temporarily unavailable')
        ->assertDontSee('catalogue ID order')->assertDontSee('rel="next"', false);
});

it('distinguishes a missing object endpoint from empty filtered data', function () {
    Http::fake(['*/objects*' => Http::response([], 404)]);
    $this->get('/asteroids?neo=1')->assertOk()->assertSee('temporarily unavailable')->assertDontSee('No asteroids match');
});

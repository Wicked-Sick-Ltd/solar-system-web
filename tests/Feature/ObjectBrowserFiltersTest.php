<?php

declare(strict_types=1);

use App\Livewire\Objects\Index;
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

it('rejects malformed object-browser URLs without querying a different selection', function (array $query) {
    $this->get('/objects?'.http_build_query($query))->assertOk()->assertSee('Reset filters and page');
    Http::assertNothingSent();
})->with([
    [['type' => ['moon']]], [['type' => 'not-a-type']], [['parent' => ['Jupiter']]],
    [['parent' => str_repeat('x', 201)]], [['size' => ['small']]], [['size' => 'unknown']],
    [['neo' => 'potato']], [['neo' => ['1']]], [['named' => 'false-ish']],
    [['page' => 'abc']], [['page' => '1.5']], [['page' => '0']], [['page' => '-1']],
    [['page' => '418']], [['page' => '999999999999999999999999999999']], [['page' => ['2']]], [['page' => '']],
]);

it('preserves literal parent strings even while a separate filter is invalid', function (string $parent) {
    Http::fake(['*/objects*' => Http::response(['results' => []])]);
    Livewire::withQueryParams(['parent' => $parent, 'size' => 'unknown'])->test(Index::class)
        ->assertSet('parent', $parent)->set('size', 'small')->assertSet('parent', $parent)->assertSee('No objects match');
    Http::assertSent(fn ($request) => $request['parent'] === $parent && $request['min_radius_km'] === 1.0 && $request['max_radius_km'] === 100.0);
    Http::assertSentCount(1);
})->with(['true', 'false', 'null', '0', 'moon-luna']);

it('normalizes valid checkbox URL values without interpreting false as true', function (string $value, bool $enabled) {
    Http::fake(['*/objects*' => Http::response(['results' => []])]);
    Livewire::withQueryParams(['neo' => $value, 'named' => $value])->test(Index::class)
        ->assertSet('neo', $enabled)->assertSet('named', $enabled);
    Http::assertSent(fn ($request) => $enabled
        ? $request['neo'] === 'true' && $request['named_only'] === 'true'
        : ! isset($request['neo']) && ! isset($request['named_only']));
})->with([['true', true], ['1', true], ['false', false], ['0', false]]);

it('does not coerce malformed object-browser updates or erase their errors with another filter', function (string $field, mixed $value) {
    Http::fake(['*/objects*' => Http::response(['results' => []])]);
    Livewire::test(Index::class)->set($field, $value)->assertSee('Reset filters and page')
        ->set('parent', 'Jupiter')->assertSee('Reset filters and page')
        ->call('clearFilters')->assertDontSee('Reset filters and page');
    Http::assertSentCount(1);
})->with([
    ['type', ['moon']], ['size', true], ['neo', null], ['named', ['1']],
    ['page', true], ['page', false], ['page', 1.5], ['page', 2.0], ['page', null],
]);

it('allows clearing nullable text updates without unsetting typed properties', function (string $field) {
    Http::fake(['*/objects*' => Http::response(['results' => []])]);
    Livewire::test(Index::class)->set($field, null)->assertSet($field, '')->assertSee('No objects match');
})->with(['type', 'parent', 'size']);

it('keeps known catalogue types outside the short dropdown available by URL', function () {
    Http::fake(['*/objects*' => Http::response(['results' => []])]);
    $this->get('/objects?type=dwarf_planet_candidate')->assertOk()->assertDontSee('Reset filters and page')
        ->assertSee('value="dwarf_planet_candidate" selected', false);
    Http::assertSent(fn ($request) => $request['type'] === 'dwarf_planet_candidate');
});

it('normalizes numeric checkbox updates before querying and publishing URL state', function (mixed $value, bool $enabled) {
    Http::fake(['*/objects*' => Http::response(['results' => []])]);
    Livewire::test(Index::class)->set('neo', $value)->set('named', $value)
        ->assertSet('neo', $enabled)->assertSet('named', $enabled)->assertDontSee('Reset filters and page');
})->with([[1, true], ['1', true], [0, false], ['0', false]]);

it('applies an object form from page one without dropping independent invalid fields', function () {
    Http::fake(['*/objects*' => Http::response(['results' => []])]);
    Livewire::withQueryParams(['parent' => 'true', 'page' => 'broken'])->test(Index::class)
        ->call('applyFilters')->assertSet('page', 1)->assertSet('parent', 'true')->assertDontSee('Reset filters and page');
    Livewire::withQueryParams(['parent' => 'true', 'size' => 'unknown', 'page' => 'broken'])->test(Index::class)
        ->call('applyFilters')->assertSee('Choose a valid size range.')->assertDontSee('Choose a whole page number');
    Http::assertSentCount(1);
});

it('escapes arbitrary parent names in selected options and pagination URLs', function () {
    Http::fake(['*/objects*' => Http::response(['results' => objectRows(25)])]);
    $parent = '<script>alert("x")</script>';
    $this->get('/objects?'.http_build_query(['parent' => $parent]))->assertOk()->assertSee($parent)->assertDontSee($parent, false);
});

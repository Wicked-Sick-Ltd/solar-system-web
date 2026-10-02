<?php

declare(strict_types=1);

use App\Livewire\Category;
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

function catalogueDocument(string $html): DOMXPath
{
    $document = new DOMDocument;
    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

    return new DOMXPath($document);
}

/** Submit the server-rendered successful controls, without JavaScript. */
function catalogueFormUrl(DOMXPath $document, string $label): string
{
    $form = $document->query('//form[@aria-label="'.$label.'"]')->item(0);
    expect($form)->toBeInstanceOf(DOMElement::class);
    expect(strtolower($form->getAttribute('method')))->toBe('get');
    $query = [];
    foreach ($document->query('.//input[@name] | .//select[@name]', $form) as $control) {
        if ($control->getAttribute('type') === 'checkbox' && ! $control->hasAttribute('checked')) {
            continue;
        }
        $value = $control->getAttribute('value');
        if ($control->tagName === 'select') {
            $option = $document->query('./option[@selected]', $control)->item(0) ?? $document->query('./option', $control)->item(0);
            $value = $option?->getAttribute('value') ?? '';
        }
        $query[$control->getAttribute('name')] = $value;
    }

    return $form->getAttribute('action').'?'.http_build_query($query);
}

function fakePagedExoplanets(): void
{
    Http::fake(['*/exoplanets*' => function ($request) {
        $offset = (int) ($request['offset'] ?? 0);

        return Http::response(['available' => true, 'results' => array_map(fn ($i) => array_replace(exoplanetPayload(), [
            'id' => 'exo-'.$i, 'name' => 'Planet number '.$i,
        ]), range($offset, $offset + 24))]);
    }]);
}

it('submits every selected exoplanet filter through a native GET form and restarts paging', function () {
    fakePagedExoplanets();
    $query = ['q' => 'false', 'method' => 'Future "method" & technique', 'distance' => '25', 'page' => 2];
    $response = $this->get('/exoplanets?'.http_build_query($query))->assertOk();
    $document = catalogueDocument($response->getContent());
    $url = catalogueFormUrl($document, 'Exoplanet filters');
    parse_str(parse_url($url, PHP_URL_QUERY), $submitted);
    expect($submitted)->toBe(array_diff_key($query, ['page' => true]));
    $this->get($url)->assertOk()->assertSee('Planet number 0');
    Http::assertSent(fn ($r) => $r['q'] === 'false' && $r['discovery_method'] === $query['method'] && $r['max_distance_pc'] === '25' && $r['offset'] === 0);
});

it('retains all validated exoplanet filters in native next and previous page links', function () {
    fakePagedExoplanets();
    $query = ['q' => 'Kepler & true', 'method' => 'Radial Velocity', 'distance' => '100', 'page' => 2];
    $response = $this->get('/exoplanets?'.http_build_query($query))->assertOk();
    $document = catalogueDocument($response->getContent());
    foreach (['prev' => 1, 'next' => 3] as $relation => $page) {
        $link = $document->query('//main//a[@rel="'.$relation.'"]')->item(0);
        expect($link)->not->toBeNull();
        $url = $link->getAttribute('href');
        parse_str(parse_url($url, PHP_URL_QUERY), $selection);
        expect($selection)->toBe(array_replace($query, ['page' => (string) $page]));
        $this->get($url)->assertOk()->assertSee('Planet number '.(($page - 1) * 24));
    }
});

it('offers first-page recovery for stale exoplanet pages without claiming no matches anywhere', function () {
    Http::fake(['*/exoplanets*' => Http::response(['available' => true, 'results' => []])]);
    $query = ['q' => 'Proxima', 'method' => 'Radial Velocity', 'distance' => '10', 'page' => 12];
    $this->get('/exoplanets?'.http_build_query($query))->assertOk()->assertSee('No exoplanets at this page')
        ->assertDontSee('No exoplanets match those filters')
        ->assertSee(route('exoplanets.index', array_replace($query, ['page' => 1])));
});

it('does not offer an invalid next-page link beyond the supported exoplanet bound', function () {
    fakePagedExoplanets();
    $response = $this->get('/exoplanets?page=4000')->assertOk()->assertSee('browsing page limit has been reached');
    expect(catalogueDocument($response->getContent())->query('//main//a[@rel="next"]')->length)->toBe(0);
});

it('restarts exoplanet form submissions without discarding other validation errors', function () {
    fakePagedExoplanets();
    Livewire::withQueryParams(['q' => 'true', 'page' => 'broken'])->test(Exoplanets::class)
        ->call('applyFilters')->assertSet('page', 1)->assertSet('q', 'true')->assertSee('Planet number 0');
    Livewire::withQueryParams(['q' => 'true', 'distance' => '5', 'page' => 3])->test(Exoplanets::class)
        ->call('applyFilters')->assertSet('page', 1)->assertSee('selected distance is invalid')->assertDontSee('Planet number 0');
});

it('renders the meteor date and established checkbox as native successful GET controls', function () {
    Http::fake(['*/meteor-showers*' => Http::response(['items' => [], 'count' => 0])]);
    $response = $this->get('/meteor-showers?active_on=2028-02-29&established_only=1')->assertOk();
    $url = catalogueFormUrl(catalogueDocument($response->getContent()), 'Meteor shower filters');
    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    expect($query)->toBe(['active_on' => '2028-02-29', 'established_only' => '1']);
    $this->get($url)->assertOk();
    $response = $this->get('/meteor-showers?established_only=0')->assertOk();
    $url = catalogueFormUrl(catalogueDocument($response->getContent()), 'Meteor shower filters');
    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    expect($query)->toBe(['active_on' => '']);
});

it('links category pages to their own next page without JavaScript', function (string $path) {
    Http::fake(['*/objects*' => Http::response(['results' => objectRows(25)])]);
    $response = $this->get($path.'?page=2')->assertOk();
    $document = catalogueDocument($response->getContent());
    foreach (['prev' => 1, 'next' => 3] as $relation => $page) {
        $url = $document->query('//main//a[@rel="'.$relation.'"]')->item(0)->getAttribute('href');
        expect(parse_url($url, PHP_URL_PATH))->toBe($path);
        expect(parse_url($url, PHP_URL_QUERY))->toBe('page='.$page);
        $this->get($url)->assertOk();
    }
})->with(['/comets', '/tnos']);

it('gives category empty and malformed pages a usable first-page recovery link', function (string $path) {
    Http::fake(['*/objects*' => Http::response(['results' => []])]);
    $this->get($path.'?page=12')->assertOk()->assertSee('No objects at this page')
        ->assertSee('Return to the first page')->assertSee(url($path).'?page=1', false)->assertDontSee('Nothing here yet');
    $this->get($path.'?page=broken')->assertOk()->assertSee('Return to the first page')->assertSee(url($path).'?page=1', false);
})->with(['/comets', '/tnos']);

it('keeps category next-page links within supported bounds', function () {
    Http::fake(['*/objects*' => Http::response(['results' => objectRows(25)])]);
    $response = $this->get('/comets?page=417')->assertOk()->assertSee('browsing page limit has been reached');
    expect(catalogueDocument($response->getContent())->query('//main//a[@rel="next"]')->length)->toBe(0);
});

it('restarts an asteroid form submission in its chosen order with the same filters', function () {
    Http::fake(['*/objects*' => Http::response(['results' => objectRows(25)])]);
    Livewire::withQueryParams(['order' => 'id', 'orbit' => 'MBA', 'after' => 'obj-12'])->test(Category::class, ['kind' => 'asteroid'])
        ->call('applyFilters')->assertSet('after', '')->assertSet('page', 1)->assertSet('order', 'id')->assertSet('orbit', 'MBA');
});

it('leaves catalogue links available to native modified clicks while enhancing plain navigation', function (string $path) {
    fakeSolar();
    $document = catalogueDocument($this->get($path)->assertOk()->getContent());
    expect($document->query('//main//a[@*[name()="wire:click.prevent"]]')->length)->toBe(0);
    expect($document->query('//main//a[@*[name()="wire:navigate"]]')->length)->toBeGreaterThan(0);
})->with(['/exoplanets?page=2', '/meteor-showers', '/asteroids', '/comets?page=2', '/tnos?page=2']);

it('submits the initial object filter state and follows filtered native pages', function () {
    Http::fake(['*/objects*' => Http::response(['results' => objectRows(25)])]);
    $query = ['type' => 'moon', 'parent' => 'moon-luna', 'size' => 'small', 'neo' => '1', 'named' => '1', 'page' => '2'];
    $response = $this->get('/objects?'.http_build_query($query))->assertOk();
    $document = catalogueDocument($response->getContent());
    $url = catalogueFormUrl($document, 'Object filters');
    parse_str(parse_url($url, PHP_URL_QUERY), $submitted);
    expect($submitted)->toBe(array_diff_key($query, ['page' => true]));
    $this->get($url)->assertOk();
    Http::assertSent(fn ($request) => $request['type'] === 'moon' && $request['parent'] === 'moon-luna'
        && $request['min_radius_km'] === 1.0 && $request['max_radius_km'] === 100.0
        && $request['neo'] === 'true' && $request['named_only'] === 'true' && $request['offset'] === 0);
    foreach (['prev' => '1', 'next' => '3'] as $relation => $page) {
        $url = $document->query('//main//a[@rel="'.$relation.'"]')->item(0)->getAttribute('href');
        parse_str(parse_url($url, PHP_URL_QUERY), $selection);
        expect($selection)->toBe(array_replace($query, ['page' => $page]));
        $this->get($url)->assertOk();
    }
});

it('recovers a stale object page with the same filters and respects the page bound', function () {
    Http::fake(['*/objects*' => Http::response(['results' => []])]);
    $this->get('/objects?type=moon&parent=Jupiter&size=tiny&page=12')->assertOk()->assertSee('No objects at this page')
        ->assertDontSee('No objects match those filters')
        ->assertSee(route('objects.index', ['type' => 'moon', 'parent' => 'Jupiter', 'size' => 'tiny', 'page' => 1]));
    Cache::flush();
    Http::swap(new Factory);
    Http::fake(['*/objects*' => Http::response(['results' => objectRows(25)])]);
    $response = $this->get('/objects?page=417')->assertOk()->assertSee('browsing page limit has been reached');
    expect(catalogueDocument($response->getContent())->query('//main//a[@rel="next"]')->length)->toBe(0);
});

<?php

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => fakeSolar());

it('offers explicit map loading alongside immediate native browsing', function () {
    $response = $this->get('/galaxy?host=host-proxima')->assertOk()
        ->assertSee('Load 3D map')->assertSee('Load the 3D map to use these controls')
        ->assertSee(route('systems.index'))->assertSee(route('systems.show', 'host-proxima'))
        ->assertSee('Works without JavaScript or a 3D display.')
        ->assertSee('The interactive map needs JavaScript.');
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//*[@data-load-map and @type="button" and @hidden]')->length)->toBe(1)
        ->and($xpath->query('//*[@disabled and (@data-system or @data-view or @data-radius or @data-reset)]')->length)->toBe(4)
        ->and($xpath->query('//*[@data-sun-label and @hidden]')->length)->toBe(1);
});

it('does not offer a load action without a measured catalogue', function () {
    fakeSolarDown();
    $this->get('/galaxy')->assertOk()->assertDontSee('data-load-map', false)->assertSee('unavailable');
});

it('keeps full catalogue option nodes out of the initial accessible page', function () {
    Http::swap(new Factory);
    $rows = array_map(fn ($i) => array_replace(exoplanetHostPayload(), ['id' => 'host-'.$i]), range(1, 5000));
    Http::fake(['*/galaxy' => Http::response(['available' => true, 'results' => $rows])]);
    $response = $this->get('/galaxy')->assertOk();
    $response->assertSee('host-1')->assertDontSee('host-5000');
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    expect((new DOMXPath($dom))->query('//*[@data-system]/option')->length)->toBe(1);
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

function observingSectionLinks(string $html): array
{
    $document = new DOMDocument;
    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $nav = '//nav[@aria-label="On this page"]';

    expect($xpath->evaluate("count($nav)"))->toBe(1.0)
        ->and($xpath->evaluate("string($nav/@class)"))->toContain('print:hidden')
        ->and($xpath->evaluate("count($nav//*[@x-cloak or @hidden])"))->toBe(0.0);

    $destinations = [];
    foreach ($xpath->query("$nav//a") as $link) {
        $fragment = $link->getAttribute('href');
        expect($fragment)->toStartWith('#');
        $id = substr($fragment, 1);
        expect($xpath->evaluate("count(//*[@id='$id'])"))->toBe(1.0)
            ->and($xpath->evaluate("count(//h2[@id='$id'][@tabindex='-1'])"))->toBe(1.0)
            ->and(trim($link->textContent))->not->toBe('');
        $destinations[] = $id;
    }

    return $destinations;
}

it('offers native section links to focusable observatory and journal headings', function (string $path, array $ids) {
    Http::preventStrayRequests();
    expect(observingSectionLinks($this->get($path)->assertOk()->getContent()))->toBe($ids);
    Http::assertNothingSent();
})->with([
    'observatory' => ['/observatory', ['equipment-heading', 'sites-heading', 'optics-heading', 'workspace-backup-heading']],
    'journal' => ['/observing-journal', ['journal-lists-heading', 'journal-entries-heading', 'journal-backup-heading']],
]);

it('omits result destinations before a calculation and on validation errors', function () {
    Http::preventStrayRequests();
    expect(observingSectionLinks($this->get('/observe/night')->assertOk()->getContent()))->toBe(['night-plan-heading']);
    expect(observingSectionLinks($this->post('/observe/night', [])->assertStatus(422)->getContent()))->toBe(['night-plan-heading']);
    Http::assertNothingSent();
});

it('links to rendered result sections without a separate menu item for every target', function () {
    $fixture = json_decode(file_get_contents(base_path('tests/fixtures/observing/night.json')), true, flags: JSON_THROW_ON_ERROR);
    Http::fake(['*' => Http::response($fixture)]);
    $input = ['date' => '2026-10-01', 'timezone' => 'Europe/London', 'lat' => 51.5, 'lon' => -0.12,
        'targets' => ['moon', 'saturn'], 'min_altitude_deg' => 20, 'sun_altitude_deg' => -12, 'min_moon_separation_deg' => 0];

    expect(observingSectionLinks($this->post('/observe/night', $input)->assertOk()->getContent()))->toBe([
        'night-plan-heading', 'night-summary', 'night-session-heading', 'equipment-suggestions-heading',
        'night-weather-heading', 'target-'.$fixture['targets'][0]['id'], 'night-method',
    ]);

});

it('omits result destinations when the astronomy service is unavailable', function () {
    $input = ['date' => '2026-10-01', 'timezone' => 'Europe/London', 'lat' => 51.5, 'lon' => -0.12,
        'targets' => ['moon', 'saturn'], 'min_altitude_deg' => 20, 'sun_altitude_deg' => -12, 'min_moon_separation_deg' => 0];
    Http::fake(['*' => Http::response([], 503)]);
    expect(observingSectionLinks($this->post('/observe/night', $input)->assertStatus(503)->getContent()))->toBe(['night-plan-heading']);
});

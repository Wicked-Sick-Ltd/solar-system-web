<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

beforeEach(fn () => fakeSolar());

it('offers every university activity with prerequisites and a printable handout', function () {
    $response = $this->get('/higher-education')->assertOk();

    foreach (config('higher-education.activities') as $id => $activity) {
        $response->assertSee($activity['title'])
            ->assertSee($activity['prerequisites'])
            ->assertSee($activity['duration'])
            ->assertSee(route('higher-education.handout', ['activity' => $id]), false);
    }

    $response->assertSee('On this page')->assertSee('Students do not need an account or a telescope.');
    Http::assertNothingSent();
});

it('renders a complete printable student worksheet and separate teaching notes', function (string $id) {
    $activity = config('higher-education.activities.'.$id);
    $response = $this->get('/higher-education/'.$id.'/handout')->assertOk();

    $response->assertSee($activity['title'])
        ->assertSee($activity['question'])
        ->assertSee($activity['deliverable'])
        ->assertSee('Student worksheet')
        ->assertSee('Teaching notes')
        ->assertSee('Save as PDF')
        ->assertSee('@media print', false)
        ->assertSee('break-before: page', false);

    foreach ($activity['steps'] as $step) {
        $response->assertSee($step);
    }

    foreach ($activity['sources'] as $source) {
        $response->assertSee('href="'.$source['url'].'"', false);
    }

    Http::assertNothingSent();
})->with(['keplers-law', 'exoplanet-selection', 'reproducible-research']);

it('does not resolve arbitrary config keys or unknown handouts', function () {
    $this->get('/higher-education/unknown/handout')->assertNotFound();
    $this->get('/higher-education/title/handout')->assertNotFound();
});

it('describes university activities as free HTML learning resources', function () {
    $this->get('/higher-education')->assertOk()
        ->assertSee('<title>Higher education · '.config('site.name').'</title>', false)
        ->assertSee('"@type":"LearningResource"', false)
        ->assertSee('"educationalLevel":"Undergraduate"', false)
        ->assertSee('"encodingFormat":"text/html"', false)
        ->assertSee('"isAccessibleForFree":true', false);
});

it('uses the configured canonical origin for printable handouts', function () {
    config(['app.url' => 'https://publicuniverse.net']);

    $this->get('/higher-education/keplers-law/handout')->assertOk()
        ->assertSee('<link rel="canonical" href="https://publicuniverse.net/higher-education/keplers-law/handout">', false);
});

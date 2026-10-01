<?php

declare(strict_types=1);

it('keeps learning and navigation available to guests during a catalogue outage', function (string $url, string $heading) {
    fakeSolarDown();

    $this->get($url)->assertOk()->assertSee($heading)
        ->assertSee(route('explore'), false)->assertSee(route('observe'), false)
        ->assertSee(route('learn'), false)->assertSee(route('api'), false);
})->with([
    ['/explore', 'Explore the universe'],
    ['/observe', 'Observe the sky'],
    ['/learn', 'A little curiosity goes a long way'],
]);

it('connects learning activities to filtered catalogues and their primary references', function () {
    fakeSolar();

    $this->get('/learn')->assertOk()
        ->assertSee(route('exoplanets.index', ['method' => 'Transit']), false)
        ->assertSee('https://spaceplace.nasa.gov/light-year/en/', false)
        ->assertSee('https://science.nasa.gov/exoplanets/how-we-find-and-characterize/', false)
        ->assertSee('https://exoplanetarchive.ipac.caltech.edu/docs/PSCompPars.html', false)
        ->assertSee('not a zero')->assertSee('different studies');
});

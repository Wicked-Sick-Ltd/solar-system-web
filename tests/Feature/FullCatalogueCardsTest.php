<?php

declare(strict_types=1);

beforeEach(fn () => fakeSolar());

it('shows orbit quality, discovery, aliases and close approaches for a small body', function () {
    $this->get('/objects/ast-20099942-apophis')
        ->assertOk()
        ->assertSee('Orbit quality')
        ->assertSee('very well determined')
        ->assertSee('8,121')
        ->assertSee('Apollo')
        ->assertSee('Kitt Peak')
        ->assertSee('Egyptian god')
        ->assertSee('Also known as')
        ->assertSee('2004 MN4')
        ->assertSee('Close approaches')
        ->assertSee('2029-04-13 21:46')
        ->assertSee('0.1 LD')
        ->assertSee('98 on record');
});

it('shows the atmosphere card and the extra fact-sheet rows for a planet', function () {
    $this->get('/objects/planet-saturn')
        ->assertOk()
        ->assertSee('Atmosphere')
        ->assertSee('Molecular hydrogen (H2)')
        ->assertSee('96.3 %')
        ->assertDontSee('Close approaches');
});

it('adds mass and density columns to the moons table', function () {
    $this->get('/objects/planet-saturn')
        ->assertOk()
        ->assertSee('Density')
        ->assertSee('g/cm³');
});

it('links the whole-database download from the footer and the about page', function () {
    $this->get('/about')->assertOk()->assertSee('Download the whole database')->assertSee(config('site.download_url'), escape: false);
    $this->get('/')->assertOk()->assertSee(config('site.download_url'), escape: false);
});

it('opens the visual card for comet photometry alone and gates orbit quality on quality fields only', function () {
    $v = \App\Services\SolarApi\Data\VisualProperties::fromArray(['comet_m1' => 5.5]);
    expect($v->hasAny())->toBeTrue();
    $o = \App\Services\SolarApi\Data\OrbitalElements::fromArray(['moid_au' => 0.01]);
    expect($o->hasQualityData())->toBeFalse();
    $o = \App\Services\SolarApi\Data\OrbitalElements::fromArray(['last_obs' => '2024-06-30']);
    expect($o->hasQualityData())->toBeTrue();
});

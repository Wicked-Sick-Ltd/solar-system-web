<?php

declare(strict_types=1);

beforeEach(fn () => fakeSolar());

it('shows the sky panel with RA, Dec, constellation and hemisphere', function () {
    $this->get('/objects/planet-saturn')
        ->assertOk()
        ->assertSee('In the sky')
        ->assertSee('23h 12m 04s')
        ->assertSee('Aquarius')
        ->assertSee('both hemispheres')
        ->assertSee('Get precise data for my location');
});

it('hides the sky panel when the backend has no sky data', function () {
    $this->get('/objects/missing-sky')
        ->assertOk()
        ->assertDontSee('In the sky');
});

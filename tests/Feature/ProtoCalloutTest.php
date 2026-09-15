<?php

declare(strict_types=1);

beforeEach(fn () => fakeSolar());

it('calls out the distance from the Sun in AU for Proto on the Pluto page', function () {
    $this->get('/objects/dwarf-pluto')
        ->assertOk()
        ->assertSee('Pluto')
        ->assertSee('id="proto"', escape: false)
        ->assertSee('For Proto, so he never forgets')
        ->assertSee('39.48 AU')
        ->assertSee('29.66 AU')
        ->assertSee('49.31 AU')
        ->assertSee('Insomnia');
});

it('does not show the Proto callout on other objects', function () {
    $this->get('/objects/planet-saturn')
        ->assertOk()
        ->assertDontSee('Proto');
});

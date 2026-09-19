<?php

declare(strict_types=1);

beforeEach(fn () => fakeSolar());

it('draws the Sun, Earth and the object in the "Where is it now" panel', function () {
    $this->get('/objects/planet-saturn')
        ->assertOk()
        ->assertSee('Where is it now')
        ->assertSee('relative-position', false)      // the new figure's root class
        ->assertSee('data-body="sun"', false)
        ->assertSee('data-body="earth"', false)
        ->assertSee('data-body="object"', false)
        ->assertSee('from Earth')
        ->assertDontSee('Orbit of Saturn');            // the old schematic is gone
});

it('shows a moon at its parent and says so', function () {
    $this->get('/objects/moon-luna')
        ->assertOk()
        ->assertSee('Where is it now')
        ->assertSee('data-body="object"', false)
        ->assertSee('shown at its parent');
});

it('omits the figure when there is no position', function () {
    $this->get('/objects/dwarf-pluto')
        ->assertOk()
        ->assertDontSee('relative-position', false);
});

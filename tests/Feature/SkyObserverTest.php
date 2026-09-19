<?php

declare(strict_types=1);

use App\Livewire\SkyObserver;
use Livewire\Livewire;

beforeEach(fn () => fakeSolar());

it('starts idle with a call to action', function () {
    Livewire::test(SkyObserver::class, ['objectId' => 'planet-saturn'])
        ->assertSee('Get precise data for my location')
        ->assertDontSee('Altitude');
});

it('renders the observer view for a location', function () {
    Livewire::test(SkyObserver::class, ['objectId' => 'planet-saturn'])
        ->call('setLocation', 51.5, -0.12)
        ->assertSet('lat', 51.5)
        ->assertSee('Altitude')
        ->assertSee('Up now')
        ->assertSee('WNW')
        ->assertSee('Forget my location');
});

it('rejects impossible coordinates', function () {
    Livewire::test(SkyObserver::class, ['objectId' => 'planet-saturn'])
        ->call('setLocation', 123.0, 0.0)
        ->assertHasErrors(['lat']);
});

it('forgets the location', function () {
    Livewire::test(SkyObserver::class, ['objectId' => 'planet-saturn'])
        ->call('setLocation', 51.5, -0.12)
        ->call('forget')
        ->assertSet('lat', null)
        ->assertSee('Get precise data for my location');
});

it('offers a paste box and a guide to finding coordinates', function () {
    Livewire::test(SkyObserver::class, ['objectId' => 'planet-saturn'])
        ->assertSee('Paste coordinates')
        ->assertSee('How do I find my coordinates?')
        ->assertSee('right-click the spot you want', false)
        ->assertSee('press and hold the spot to drop a pin', false);
});

it('hides the coordinate entry helpers once a location is set', function () {
    Livewire::test(SkyObserver::class, ['objectId' => 'planet-saturn'])
        ->call('setLocation', 51.5, -0.12)
        ->assertDontSee('How do I find my coordinates?');
});

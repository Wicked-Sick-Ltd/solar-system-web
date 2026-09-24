<?php

declare(strict_types=1);

use App\Livewire\SkyObserver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => fakeSolar());

it('asks guests to sign in for alerts', function () {
    Livewire::test(SkyObserver::class, ['objectId' => 'planet-saturn'])
        ->call('setLocation', 51.5, -0.12)
        ->assertSee('Sign in')
        ->assertSee('get email alerts');
});

it('lets a signed-in user save and remove one alert for current object and location', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $component = Livewire::test(SkyObserver::class, ['objectId' => 'planet-saturn'])
        ->call('setLocation', 51.5, -0.12)
        ->call('saveAlert')
        ->assertSee('Email alert saved');

    expect($user->visibilityAlerts()->count())->toBe(1);

    $component->call('saveAlert');
    expect($user->visibilityAlerts()->count())->toBe(1);

    $component->call('removeAlert')
        ->assertSee('Tell me when it is up after dark');

    expect($user->visibilityAlerts()->count())->toBe(0);
});

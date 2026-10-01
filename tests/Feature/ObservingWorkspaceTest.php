<?php

declare(strict_types=1);

use App\Livewire\ObservingWorkspace;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

it('renders the guest workspace without personal Livewire state or catalogue requests', function () {
    $this->withoutVite();
    Http::preventStrayRequests();
    $page = Livewire::test(ObservingWorkspace::class)
        ->assertSee('Your observatory')
        ->assertSee('No equipment or named sites are uploaded')
        ->assertSee('No observing sites saved yet.')
        ->assertSee('Review before replacing')
        ->assertSee('Current sky calculations do not apply them.');
    expect($page->instance()->all())->toBe([]);
    Http::assertNothingSent();
});

it('keeps native personal-data forms disabled until the browser controller installs its handlers', function () {
    $this->withoutVite();
    $html = Livewire::test(ObservingWorkspace::class)->html();
    expect($html)->toContain('<fieldset disabled data-workspace-enabled>')
        ->toContain('data-workspace-equipment-form')
        ->toContain('data-workspace-site-form')
        ->toContain('role="alert" id="workspace-error"')
        ->toContain('<noscript>')
        ->not->toContain('wire:model')
        ->not->toContain('wire:submit');
});

it('serves the workspace route to guests without exposing it to search indexes', function () {
    $this->withoutVite();
    Http::preventStrayRequests();
    $this->get(route('observatory'))
        ->assertOk()
        ->assertSee('Your observatory')
        ->assertSee('noindex', false);
    Http::assertNothingSent();
});

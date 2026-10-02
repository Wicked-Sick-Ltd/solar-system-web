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
        ->assertSee('Saving here or signing in does not upload equipment or named sites.')
        ->assertSee('A separate private account backup requires your explicit preview and upload choice.')
        ->assertSee('No observing sites saved yet.')
        ->assertSee('Review before replacing')
        ->assertSee('Activating a site only selects its approximate location for other sky views.');
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

it('provides guest optical calculations with explicit scientific limits and textual diagram equivalents', function () {
    $this->withoutVite();
    Livewire::test(ObservingWorkspace::class)
        ->assertSee('Compare your optical setup')
        ->assertSee('Angular diameter to compare (arcminutes, optional)')
        ->assertSee('Field-stop estimates use the paraxial approximation.')
        ->assertSee('No planet sizes or catalogue measurements are filled in automatically.')
        ->assertSee('data-optics-comparison', false)
        ->assertSee('optics-diagram-description', false);
});

it('describes camera geometry and user-entered horizon limits without implying planner integration', function () {
    $this->withoutVite();
    Livewire::test(ObservingWorkspace::class)
        ->assertSee('Camera sensor')
        ->assertSee('Sensor width (mm)')
        ->assertSee('Horizon mask (optional)')
        ->assertSee('Copy this saved site in the night planner to apply its horizon mask to a calculation.')
        ->assertSee('older version-1 backups remain readable')
        ->assertSee('Unknown pixel size stays unknown.')
        ->assertSee('no camera is controlled.');
});

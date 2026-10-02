<?php

declare(strict_types=1);

use App\Livewire\ObservingJournal;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

it('serves guest journal controls without an API call or account', function () {
    Http::fake();
    $this->get('/observing-journal')->assertOk()->assertSee('Observing lists and journal')
        ->assertSee('Actual observation time in UTC')->assertSee('Include site names and coordinates in exports')
        ->assertSee('No site snapshot')->assertSee('Save correction')->assertSee('Cancel correction')
        ->assertSee('The record identifier, catalogue target and historical equipment/site snapshot stay unchanged.')
        ->assertSee('Download original journal storage')->assertSee('data-journal-controls disabled', false)
        ->assertSee('noindex', false);
    Http::assertNothingSent();
});

it('keeps private records out of Livewire properties and native form actions', function () {
    $component = Livewire::test(ObservingJournal::class);
    expect($component->instance()->all())->toBe([]);
    $component->assertDontSee('wire:model', false)->assertDontSee('wire:submit', false)
        ->assertDontSee('method="POST"', false)->assertSee('Signing in does not upload this journal.');
});

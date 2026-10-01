<?php

declare(strict_types=1);

use App\Models\PrivateObservingWorkspace;
use App\Models\User;
use App\Services\Observing\ObservingAccountScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('requires authentication before rendering an account backup page', function () {
    $this->get('/account/observing-backup')->assertRedirect('/login');
});

it('renders private transfer controls without loading the encrypted account copy', function () {
    Http::fake();
    $user = User::factory()->create();
    $this->actingAs($user)->get('/account/observing-backup')->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('X-Robots-Tag', 'noindex')->assertSee($user->email)
        ->assertSee('data-account-scope="'.ObservingAccountScope::forUser($user).'"', false)
        ->assertSee('No account preview loaded.')->assertSee('data-sync-upload type="button" disabled', false)
        ->assertSee('Signing in never uploads')->assertSee('not end-to-end encryption')
        ->assertDontSee('wire:model', false)->assertDontSee('wire:submit', false);
    expect(PrivateObservingWorkspace::count())->toBe(0);
    Http::assertNothingSent();
});

it('binds each rendered page to its own account without exposing numeric user identifiers', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $firstScope = ObservingAccountScope::forUser($first);
    $secondScope = ObservingAccountScope::forUser($second);
    expect($firstScope)->not->toBe($secondScope)->toMatch('/^[a-f0-9]{64}$/');
    $this->actingAs($second)->get('/account/observing-backup')
        ->assertSee('data-account-scope="'.$secondScope.'"', false)->assertDontSee($firstScope);
});

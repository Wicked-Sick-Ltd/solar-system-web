<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\VisibilityAlert;
use App\Notifications\VisibilityUpAfterDarkNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(fn () => fakeSolar());

it('sends one mail when visibility changes to up-after-dark, then suppresses duplicates', function () {
    $user = User::factory()->create();

    $alert = VisibilityAlert::query()->create([
        'user_id' => $user->id,
        'object_id' => 'planet-saturn',
        'latitude' => 51.50,
        'longitude' => -0.12,
        'active' => true,
    ]);

    Notification::fake();

    $this->artisan('alerts:send-visibility')->assertSuccessful();

    Notification::assertSentTo($user, VisibilityUpAfterDarkNotification::class);

    $alert->refresh();
    expect($alert->last_state_up_after_dark)->toBeTrue()
        ->and($alert->last_triggered_at)->not->toBeNull();

    Notification::fake();

    $this->artisan('alerts:send-visibility')->assertSuccessful();

    Notification::assertNothingSent();
});

<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\VisibilityAlert;
use App\Notifications\VisibilityUpAfterDarkNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

it('does not send while another command owns the delivery lock', function () {
    $user = User::factory()->create();
    VisibilityAlert::query()->create([
        'user_id' => $user->id, 'object_id' => 'planet-saturn',
        'latitude' => 51.50, 'longitude' => -0.12, 'active' => true,
    ]);
    Notification::fake();
    $lock = Cache::lock('alerts:send-visibility', 3600);
    expect($lock->get())->toBeTrue();

    try {
        $this->artisan('alerts:send-visibility')->assertSuccessful();
        Notification::assertNothingSent();
    } finally {
        $lock->release();
    }

    $this->artisan('alerts:send-visibility')->assertSuccessful();
    Notification::assertSentTo($user, VisibilityUpAfterDarkNotification::class);
});

it('preserves the triggered state when observer data is unavailable', function () {
    $alert = VisibilityAlert::query()->create([
        'user_id' => User::factory()->create()->id, 'object_id' => 'missing-sky',
        'latitude' => 51.50, 'longitude' => -0.12, 'active' => true,
        'last_state_up_after_dark' => true,
    ]);
    Notification::fake();

    $this->artisan('alerts:send-visibility')->assertSuccessful();

    expect($alert->refresh()->last_state_up_after_dark)->toBeTrue();
    expect($alert->last_checked_at)->toBeNull();
    Notification::assertNothingSent();
});

it('continues after a transport failure and retries only the pending transition', function () {
    $users = User::factory()->count(2)->create();
    foreach ($users as $user) {
        VisibilityAlert::query()->create([
            'user_id' => $user->id, 'object_id' => 'planet-saturn',
            'latitude' => 51.50, 'longitude' => -0.12, 'active' => true,
        ]);
    }
    Notification::shouldReceive('send')->once()->ordered()->andThrow(new RuntimeException('Mail transport unavailable'));
    Notification::shouldReceive('send')->once()->ordered()->andReturnNull();

    $this->artisan('alerts:send-visibility')->assertFailed();

    expect($users[0]->visibilityAlerts()->first()->last_state_up_after_dark)->toBeNull();
    expect($users[1]->visibilityAlerts()->first()->last_state_up_after_dark)->toBeTrue();

    Notification::fake();
    $this->artisan('alerts:send-visibility')->assertSuccessful();
    Notification::assertSentTo($users[0], VisibilityUpAfterDarkNotification::class);
    Notification::assertNotSentTo($users[1], VisibilityUpAfterDarkNotification::class);
});

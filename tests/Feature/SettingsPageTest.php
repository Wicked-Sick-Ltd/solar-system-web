<?php

declare(strict_types=1);

use App\Support\SettingsPayload;

beforeEach(fn () => fakeSolar());

it('lists everything the site remembers, with a clear-all and a share link', function () {
    $this->get('/settings')
        ->assertOk()
        ->assertSee('Your settings')
        ->assertSee('observer_location')
        ->assertSee('preferences')
        ->assertSee('theme')
        ->assertSee('Clear everything this site remembers')
        ->assertSee('Use these settings on another device')
        ->assertSee('nothing is stored on our servers');
});

it('offers, but does not apply, settings from a valid share link', function () {
    $token = SettingsPayload::encode(['theme' => 'light', 'location' => ['lat' => 50.97, 'lon' => -1.58], 'preferences' => ['timeFormat' => '24']]);

    $this->get('/settings?s='.$token)
        ->assertOk()
        ->assertSee('Apply settings from a link?')
        ->assertSee('50.97, -1.58')
        ->assertSee('24-hour')
        ->assertSee('Light')
        ->assertSee('Apply these settings');
});

it('says so when a share link is unreadable', function () {
    $this->get('/settings?s=not-a-real-token!!')
        ->assertOk()
        ->assertSee('nothing was changed')
        ->assertDontSee('Apply these settings');
});

it('is linked from the footer, the observer panel and the privacy page', function () {
    $this->get('/about')->assertOk()->assertSee(route('settings'));
    $this->get('/privacy')->assertOk()->assertSee('observer_location')->assertSee(route('settings'));
});

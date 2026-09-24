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
        ->assertSee('Account and alert data are managed separately')
        ->assertSee('after the #');
});

it('keeps share payloads off the request, so logs and Referer never see a location', function () {
    $token = SettingsPayload::encode(['theme' => 'light', 'location' => ['lat' => 50.97, 'lon' => -1.58], 'preferences' => ['timeFormat' => '24']]);

    $html = $this->get('/settings?s='.$token)->assertOk()->getContent();
    $script = html_entity_decode($html);

    // The observing location must not be in the HTML the server sent — only the
    // browser may decode the fragment after the page has arrived.
    expect($html)->not->toContain('50.97')
        ->and($html)->not->toContain($token)
        ->and($html)->toContain('Apply settings from a link?')
        ->and($script)->toContain("return baseUrl + '#s=' + b64")
        ->and($script)->not->toContain("return baseUrl + '?s=' + b64")
        ->and($script)->toContain('shareTokenFromUrl()')
        ->and($script)->toContain("hashParams.has('s')")
        ->and($script)->toContain("addEventListener('hashchange'");
});

it('drops the share token from the address bar once the offer is on screen', function () {
    $script = html_entity_decode($this->get('/settings')->assertOk()->getContent());

    expect($script)->toContain('this.forgetShareToken();')
        ->and($script)->toContain("url.searchParams.delete('s')")
        ->and($script)->toContain("hashParams.has('s')");
});

it('ships the copy for an unreadable share link, shown only after the browser tries to decode', function () {
    $this->get('/settings')
        ->assertOk()
        ->assertSee('nothing was changed')
        ->assertSee('Apply these settings');
});

it('changes the theme only through the shared applier, so the header toggle keeps in step', function () {
    $html = $this->get('/settings')->assertOk()->getContent();

    // The pre-paint bootstrap owns data-theme and the stored value; every control asks it to change them.
    expect(substr_count($html, "setAttribute('data-theme'"))->toBe(1)
        ->and(substr_count($html, "localStorage.setItem('theme'"))->toBe(1)
        ->and(substr_count($html, 'window.applyTheme('))->toBeGreaterThanOrEqual(3);

    // Header toggle and settings page both follow the resulting event.
    expect(substr_count($html, 'x-on:theme-changed.window'))->toBe(2);
});

it('is linked from the footer, the observer panel and the privacy page', function () {
    $this->get('/about')->assertOk()->assertSee(route('settings'));
    $this->get('/privacy')
        ->assertOk()
        ->assertSee('observer_location')
        ->assertSee('Open-Meteo')
        ->assertSee(route('settings'))
        ->assertSee('fragment of the URL');
});

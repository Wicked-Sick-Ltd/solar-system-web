<?php

declare(strict_types=1);

use App\Support\SettingsPayload;

beforeEach(fn () => fakeSolar());

it('ships no analytics and no consent banner when no measurement id is configured', function () {
    config(['site.analytics.ga_measurement_id' => null]);

    // Only essential cookies are set, which need no consent under PECR.
    $this->get('/')
        ->assertOk()
        ->assertDontSee('googletagmanager.com', escape: false)
        ->assertDontSee('<meta name="ga-measurement-id"', escape: false)
        ->assertDontSee('id="cookie-banner"', escape: false);
});

it('exposes the measurement id but never loads gtag server-side, only behind consent', function () {
    config(['site.analytics.ga_measurement_id' => 'G-TEST1234']);

    $this->get('/')
        ->assertOk()
        ->assertSee('name="ga-measurement-id" content="G-TEST1234"', escape: false)
        ->assertDontSee('<script async src="https://www.googletagmanager.com', escape: false)
        ->assertSee('cookie_consent', escape: false);
});

it('tells gtag where it is instead of letting it read the address bar', function () {
    config(['site.analytics.ga_measurement_id' => 'G-TEST1234']);

    $this->get('/')
        ->assertOk()
        ->assertSee('name="ga-page-location" content="'.url('/').'"', escape: false)
        ->assertSee('meta[name="ga-page-location"]', escape: false)
        ->assertSee('page_location: pageLocation()', escape: false);
});

it('redacts a settings share token from the page location it reports', function () {
    config(['site.analytics.ga_measurement_id' => 'G-TEST1234']);

    // Previewing a share link must not hand the observing location inside it to Google,
    // whether or not the visitor ever clicks "Apply these settings".
    $token = SettingsPayload::encode(['theme' => 'light', 'location' => ['lat' => 50.97, 'lon' => -1.58]]);

    $html = $this->get('/settings?s='.$token)->assertOk()->getContent();

    preg_match('/<meta name="ga-page-location" content="([^"]*)">/', $html, $m);

    expect($m[1] ?? '')->toBe(route('settings').'?s=redacted')
        ->and($m[1] ?? '')->not->toContain($token);

    // And the banner reports that, never the address bar gtag would read by default.
    expect($html)->not->toContain('page_location: location.href')
        ->and($html)->not->toContain('page_location: location');

    // Opening a leftover query share must not echo the coordinates in the HTML either.
    expect($html)->not->toContain('50.97');
});

it('shows a cookie banner that links to the privacy policy when analytics is enabled', function () {
    config(['site.analytics.ga_measurement_id' => 'G-TEST1234']);

    $this->get('/')
        ->assertOk()
        ->assertSee('id="cookie-banner"', escape: false)
        ->assertSee(route('privacy'), escape: false)
        ->assertSee('Essential only')
        ->assertSee('Accept analytics');
});

it('publishes a privacy policy page', function () {
    $this->get('/privacy')
        ->assertOk()
        ->assertSee('Privacy')
        ->assertSee('Google Analytics')
        ->assertSee('Mailchimp')
        ->assertSee(config('site.contact_email'))
        ->assertSee('UK GDPR');
});

it('links the privacy policy from the footer and lists it in the sitemap', function () {
    $this->get('/')->assertSee('href="'.route('privacy').'"', escape: false);
    $this->get('/sitemap.xml')->assertSee(route('privacy'), escape: false);
});

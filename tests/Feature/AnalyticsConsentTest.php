<?php

declare(strict_types=1);

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

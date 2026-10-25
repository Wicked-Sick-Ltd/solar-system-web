<?php

declare(strict_types=1);

beforeEach(fn () => fakeSolar());

it('leads the built-in banner with the analytics explanation and grants storage unless essential', function () {
    config([
        'site.analytics.ga_measurement_id' => 'G-TEST1234',
        'site.analytics.gtm_container_id' => null,
        'services.cookieyes.script_id' => null,
    ]);

    $html = $this->get('/')->assertOk()->getContent();
    $bannerAt = strpos($html, 'id="cookie-banner"');
    expect($bannerAt)->toBeInt();
    $banner = substr($html, $bannerAt);

    expect($html)
        ->toContain("var STORAGE = 'pu_cookie_consent'")
        ->toContain("return read() !== 'essential'")
        ->toContain("analytics_storage: granted ? 'granted' : 'denied'")
        ->toContain("analytics_storage: 'denied'")
        ->toContain('localStorage.getItem(STORAGE)')
        ->toContain('localStorage.setItem(STORAGE, value)')
        ->not->toContain('id="cookieyes"')
        ->not->toContain('cdn-cookieyes.com');

    expect($banner)
        ->toContain('Analytics helps us make the site better.')
        ->toContain('anonymous Google Analytics (G-4H1XSCFT9J)')
        ->toContain('planets, missions, and tools')
        ->toContain('no ad tracking or cross-site profiling')
        ->toContain('Accept analytics')
        ->toContain('Essential only')
        ->toContain("choose('analytics')")
        ->toContain("choose('essential')");

    expect(strpos($banner, 'Accept analytics'))->toBeLessThan(strpos($banner, 'Essential only'))
        ->and(strpos($banner, 'var(--accent-fill)'))->toBeLessThan(strpos($banner, 'Essential only'));

    $this->get('/privacy')
        ->assertOk()
        ->assertSee('Analytics helps us make the site better.')
        ->assertSee('pu_cookie_consent', false)
        ->assertSee("publicUniverseAnalytics.choose('analytics')", false)
        ->assertSee("publicUniverseAnalytics.choose('essential')", false);
});

it('renders CookieYes ahead of gtag and hides the built-in banner when a script id is configured', function () {
    config([
        'site.analytics.ga_measurement_id' => 'G-TEST1234',
        'site.analytics.gtm_container_id' => null,
        'services.cookieyes.script_id' => 'abc123script',
    ]);

    $html = $this->get('/')->assertOk()->getContent();
    $cookieyes = strpos($html, '<script id="cookieyes" type="text/javascript" src="https://cdn-cookieyes.com/client_data/abc123script/script.js" data-cfasync="false"></script>');
    $gtag = strpos($html, 'https://www.googletagmanager.com/gtag/js?id=G-TEST1234');

    expect($cookieyes)->toBeInt()
        ->and($gtag)->toBeInt()
        ->and($cookieyes)->toBeLessThan($gtag)
        ->and($html)->not->toContain('id="cookie-banner"')
        ->and($html)->toContain("return read() !== 'essential'");
});

it('keeps the built-in banner when the CookieYes script id is empty', function () {
    config([
        'site.analytics.ga_measurement_id' => 'G-TEST1234',
        'services.cookieyes.script_id' => '',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('id="cookie-banner"', false)
        ->assertDontSee('cdn-cookieyes.com', false);
});

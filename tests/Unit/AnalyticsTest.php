<?php

declare(strict_types=1);

use App\Support\Analytics;
use App\Support\SettingsPayload;
use Illuminate\Http\Request;

it('reports the plain URL when there is nothing sensitive in it', function () {
    $location = Analytics::pageLocation(Request::create('https://solar.test/objects/mars'));

    expect($location)->toBe('https://solar.test/objects/mars');
});

it('keeps ordinary query parameters, which say something about how pages are used', function () {
    $location = Analytics::pageLocation(Request::create('https://solar.test/catalogue?page=3'));

    expect($location)->toBe('https://solar.test/catalogue?page=3');
});

it('reads only a real GA4 measurement id from GA4_MEASUREMENT_ID', function () {
    expect(file_get_contents(config_path('site.php')))->toContain("env('GA4_MEASUREMENT_ID')")
        ->and(file_get_contents(config_path('site.php')))->not->toContain('GA_MEASUREMENT_ID');

    config(['site.analytics.ga_measurement_id' => ' g-test1234 ']);
    expect(Analytics::measurementId())->toBe('G-TEST1234');

    foreach ([null, '', '   ', 'UA-123456', 'G-AB', '<script>', 'G-TEST1234;alert(1)', 12] as $invalid) {
        config(['site.analytics.ga_measurement_id' => $invalid]);
        expect(Analytics::measurementId())->toBeNull();
    }
});

it('reads only a real GTM container id from GTM_CONTAINER_ID', function () {
    expect(file_get_contents(config_path('site.php')))->toContain("env('GTM_CONTAINER_ID', 'GTM-M8THNDPH')");

    config(['site.analytics.gtm_container_id' => ' gtm-m8thndph ']);
    expect(Analytics::gtmContainerId())->toBe('GTM-M8THNDPH');

    foreach ([null, '', '   ', 'G-TEST1234', 'GTM-AB', 'GTM-'.str_repeat('A', 17), '<script>', 'GTM-M8THNDPH;alert(1)', 12] as $invalid) {
        config(['site.analytics.gtm_container_id' => $invalid]);
        expect(Analytics::gtmContainerId())->toBeNull();
    }
});

it('treats analytics as configured when GA4, Tag Manager, or both are valid', function () {
    config(['site.analytics.ga_measurement_id' => null, 'site.analytics.gtm_container_id' => null]);
    expect(Analytics::enabled())->toBeFalse();

    config(['site.analytics.ga_measurement_id' => 'G-TEST1234', 'site.analytics.gtm_container_id' => null]);
    expect(Analytics::enabled())->toBeTrue()->and(Analytics::gtmContainerId())->toBeNull();

    config(['site.analytics.ga_measurement_id' => null, 'site.analytics.gtm_container_id' => 'gtm-m8thndph']);
    expect(Analytics::enabled())->toBeTrue()->and(Analytics::gtmContainerId())->toBe('GTM-M8THNDPH');

    config(['site.analytics.ga_measurement_id' => 'G-TEST1234', 'site.analytics.gtm_container_id' => 'GTM-M8THNDPH']);
    expect(Analytics::enabled())->toBeTrue()->and(Analytics::measurementId())->toBe('G-TEST1234');
});

it('reads only an explicit accept cookie as a server opt-in', function () {
    $accepted = Request::create('/', 'GET', cookies: [Analytics::CONSENT_COOKIE => Analytics::CONSENT_ACCEPTED]);
    $essential = Request::create('/', 'GET', cookies: [Analytics::CONSENT_COOKIE => 'essential']);
    $padded = Request::create('/', 'GET', cookies: [Analytics::CONSENT_COOKIE => 'all ']);
    $missing = Request::create('/');

    expect(Analytics::hasServerOptIn($accepted))->toBeTrue()
        ->and(Analytics::hasServerOptIn($essential))->toBeFalse()
        ->and(Analytics::hasServerOptIn($padded))->toBeFalse()
        ->and(Analytics::hasServerOptIn($missing))->toBeFalse();
});

it('ships the consent script that Livewire navigation can call', function () {
    expect(Analytics::clientScript())
        ->toContain("var COOKIE = '".Analytics::CONSENT_COOKIE."'")
        ->toContain("analytics_storage: 'denied'")
        ->toContain('window.dataLayer.push({')
        ->toContain("window.gtag('set', {")
        ->toContain('page_location: page')
        ->toContain('page_referrer: referrer')
        ->toContain('sanitizeAnalyticsUrl(document.referrer)')
        ->toContain('send_page_view: false')
        ->toContain("addEventListener('livewire:navigated'")
        ->toContain('publishPageLocation();')
        ->toContain('history.pushState')
        ->toContain("bindHistoryMethod('replaceState')")
        ->toContain("addEventListener('popstate'")
        ->toContain('googletagmanager.com/gtag/js')
        ->toContain("analytics_storage: granted ? 'granted' : 'denied'")
        ->toContain("window.gtag('set', 'url_passthrough', true)")
        ->toContain("window.gtag('set', 'ads_data_redaction', true)")
        ->toContain("window.gtag('config', id")
        ->toContain("script.setAttribute('data-cfasync', 'false')")
        ->toContain('configureMeasurement();');
});

it('documents that GA4 history page views must be disabled for the Tag Manager container', function () {
    $sentence = "Disable 'Page changes based on browser history events' under GA4 Enhanced Measurement when configuring the GA4 tag in GTM-M8THNDPH, as SPA page_view events are dispatched manually after URL redaction";

    expect(file_get_contents(config_path('site.php')))->toContain($sentence)
        ->and(file_get_contents(base_path('DEPLOYMENT.md')))->toContain($sentence)
        ->and(file_get_contents(base_path('.env.example')))->toContain($sentence)
        ->and(Analytics::clientScript())->toContain($sentence);
});

it('redacts the settings share token, which carries an observing location', function () {
    $token = SettingsPayload::encode(['location' => ['lat' => 50.97, 'lon' => -1.58]]);

    $location = Analytics::pageLocation(Request::create('https://solar.test/settings?s='.$token));

    expect($location)->toBe('https://solar.test/settings?s=redacted')
        ->and($location)->not->toContain($token);
});

it('redacts observer coordinates from the query string before they can be reported', function () {
    $location = Analytics::pageLocation(Request::create('https://solar.test/observe/night?lat=12.34567&lon=1.23456&targets[]=moon'));

    expect($location)->not->toContain('12.34567')
        ->and($location)->not->toContain('1.23456')
        ->and($location)->toContain('lat=redacted')
        ->and($location)->toContain('lon=redacted')
        ->and($location)->toContain('moon');
});

it('redacts observer coordinates from the API try-it query bag', function () {
    $location = Analytics::pageLocation(Request::create('https://solar.test/api?try=plan_night&p%5Blat%5D=51.5&p%5Blon%5D=-0.1&p%5Bdate%5D=2026-10-10'));

    expect($location)->toContain('p%5Blat%5D=redacted')
        ->and($location)->toContain('p%5Blon%5D=redacted')
        ->and($location)->toContain('p%5Bdate%5D=2026-10-10')
        ->and($location)->not->toContain('51.5')
        ->and($location)->not->toContain('-0.1');
});

it('redacts bracketed descendants of the settings token and observer coordinates', function () {
    $location = Analytics::pageLocation(Request::create(
        'https://solar.test/api?lat[]=51.5&lon[]=9.87654&p[lat][]=51.5&p[lon][]=1.23456&s[]=PRIVATE_SETTINGS&targets=moon&p[date]=2026-10-10',
    ));

    expect($location)->toContain('lat=redacted')
        ->and($location)->toContain('lon=redacted')
        ->and($location)->toContain('s=redacted')
        ->and($location)->toContain('p%5Blat%5D=redacted')
        ->and($location)->toContain('p%5Blon%5D=redacted')
        ->and($location)->toContain('p%5Bdate%5D=2026-10-10')
        ->and($location)->toContain('targets=moon')
        ->and($location)->not->toContain('51.5')
        ->and($location)->not->toContain('9.87654')
        ->and($location)->not->toContain('1.23456')
        ->and($location)->not->toContain('PRIVATE_SETTINGS');
});

it('redacts try-it coordinates nested inside a list of parameter bags', function () {
    $location = Analytics::pageLocation(Request::create(
        'https://solar.test/api?p[][lat]=51.5&p[][lon]=1.23456&p[0][date]=2026-10-10',
    ));

    expect($location)->toContain('2026-10-10')
        ->and($location)->toContain('redacted')
        ->and($location)->not->toContain('51.5')
        ->and($location)->not->toContain('1.23456');
});

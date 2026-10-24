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
        ->toContain('send_page_view: false')
        ->toContain("addEventListener('livewire:navigated'")
        ->toContain('publishPageLocation();')
        ->toContain('googletagmanager.com/gtag/js');
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

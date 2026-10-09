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

it('ships the consent script that Livewire navigation can call', function () {
    expect(Analytics::clientScript())
        ->toContain("analytics_storage: 'denied'")
        ->toContain("addEventListener('livewire:navigated'")
        ->toContain('googletagmanager.com/gtag/js');
});

it('redacts the settings share token, which carries an observing location', function () {
    $token = SettingsPayload::encode(['location' => ['lat' => 50.97, 'lon' => -1.58]]);

    $location = Analytics::pageLocation(Request::create('https://solar.test/settings?s='.$token));

    expect($location)->toBe('https://solar.test/settings?s=redacted')
        ->and($location)->not->toContain($token);
});

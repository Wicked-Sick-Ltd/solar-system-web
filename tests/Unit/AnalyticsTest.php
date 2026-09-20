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

it('redacts the settings share token, which carries an observing location', function () {
    $token = SettingsPayload::encode(['location' => ['lat' => 50.97, 'lon' => -1.58]]);

    $location = Analytics::pageLocation(Request::create('https://solar.test/settings?s='.$token));

    expect($location)->toBe('https://solar.test/settings?s=redacted')
        ->and($location)->not->toContain($token);
});

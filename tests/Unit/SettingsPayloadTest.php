<?php

declare(strict_types=1);

use App\Support\SettingsPayload;

it('round-trips a full settings object as a URL-safe fragment token', function () {
    $settings = ['theme' => 'light', 'location' => ['lat' => 50.97, 'lon' => -1.58], 'preferences' => ['timeFormat' => '24']];
    $token = SettingsPayload::encode($settings);

    expect($token)->toMatch('~^[A-Za-z0-9_-]+$~')
        ->and(SettingsPayload::decode($token))->toBe($settings);
});

it('drops unknown keys and invalid values, keeping what is good', function () {
    $token = SettingsPayload::encode([]);
    // Hand-build a token with junk in it.
    $junk = rtrim(strtr(base64_encode(json_encode([
        'theme' => 'neon', 'tracking_id' => 'abc', 'location' => ['lat' => 123, 'lon' => 0],
        'preferences' => ['timeFormat' => '24', 'font' => 'comic'],
    ])), '+/', '-_'), '=');

    expect(SettingsPayload::decode($junk))->toBe(['preferences' => ['timeFormat' => '24']])
        ->and(SettingsPayload::decode($token))->toBeNull();
});

it('rounds a shared location to 2 dp like every other location path', function () {
    $token = SettingsPayload::encode(['location' => ['lat' => 50.9712345, 'lon' => -1.5798765]]);
    expect(SettingsPayload::decode($token))->toBe(['location' => ['lat' => 50.97, 'lon' => -1.58]]);
});

it('refuses garbage, oversize and non-base64url tokens', function (string $token) {
    expect(SettingsPayload::decode($token))->toBeNull();
})->with(['', 'not base64!', str_repeat('A', 401), rtrim(base64_encode('"just a string"'), '='), rtrim(base64_encode('{bad json'), '=')]);

it('rejects invalid coordinate types and out-of-range values before rounding', function (mixed $latitude) {
    expect(SettingsPayload::clean(['location' => ['lat' => $latitude, 'lon' => 0]]))->toBe([]);
})->with([null, false, true, '', ' ', [[]], [[12]], '0x10', '1e309', 90.001, -90.001]);

it('drops compound time formats instead of casting them to strings', function () {
    expect(SettingsPayload::clean(['preferences' => ['timeFormat' => ['24']]]))->toBe([])
        ->and(SettingsPayload::clean(['preferences' => ['timeFormat' => 24]]))->toBe(['preferences' => ['timeFormat' => '24']]);
});

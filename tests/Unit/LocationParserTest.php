<?php

declare(strict_types=1);

use App\Support\LocationParser;

it('parses decimal pairs in the common separators', function (string $text) {
    expect(LocationParser::parse($text))->toBe(['lat' => 51.51, 'lon' => -0.13]);
})->with([
    '51.51, -0.13',
    '51.51 -0.13',
    '51.51,-0.13',
    '  51.5074, -0.1278 ',        // rounded to 2 dp
    'lat 51.51 lon -0.13',
]);

it('parses hemisphere suffixes and prefixes', function (string $text, float $lat, float $lon) {
    expect(LocationParser::parse($text))->toBe(['lat' => $lat, 'lon' => $lon]);
})->with([
    ['51.51 N, 0.13 W', 51.51, -0.13],
    ['51.51N 0.13W', 51.51, -0.13],
    ['33.87 S 151.21 E', -33.87, 151.21],
    ['N 51.51 W 0.13', 51.51, -0.13],
]);

it('parses degrees-minutes-seconds as Google Maps shows them', function () {
    // 51°30′26″N 0°07′39″W → 51.5072, -0.1275
    expect(LocationParser::parse('51°30\'26"N 0°07\'39"W'))->toBe(['lat' => 51.51, 'lon' => -0.13])
        ->and(LocationParser::parse('51°30′26″N 0°07′39″W'))->toBe(['lat' => 51.51, 'lon' => -0.13])
        ->and(LocationParser::parse('40°26′46″N 79°58′56″W'))->toBe(['lat' => 40.45, 'lon' => -79.98]);
});

it('parses Google Maps links that carry coordinates', function (string $url, float $lat, float $lon) {
    expect(LocationParser::parse($url))->toBe(['lat' => $lat, 'lon' => $lon]);
})->with([
    ['https://www.google.com/maps/@51.5074,-0.1278,15z', 51.51, -0.13],
    ['https://www.google.com/maps/place/London/@51.5072178,-0.1275862,11z/data=!3m1!4b1', 51.51, -0.13],
    ['https://www.google.com/maps?q=51.5074,-0.1278', 51.51, -0.13],
    ['https://maps.google.com/?ll=48.8584,2.2945&z=16', 48.86, 2.29],
    ['https://www.google.com/maps/search/?api=1&query=-33.8688%2C151.2093', -33.87, 151.21],
    ['https://www.google.com/maps/place/Eiffel+Tower/data=!4m5!3m4!1s0x0:0x0!8m2!3d48.8583701!4d2.2944813', 48.86, 2.29],
]);

it('returns null for anything it cannot read', function (string $text) {
    expect(LocationParser::parse($text))->toBeNull();
})->with([
    '',
    'London',
    'https://maps.app.goo.gl/AbCdEf',           // short link: no coordinates inside
    '///filled.count.soap',                     // what3words is a different path
    '91.0, 0.0',                                // out of range
    '51.5',                                     // one number only
    '51.51, -0.13, 12',                         // three numbers
]);

it('still reads coordinates with irregular whitespace', function (string $text) {
    expect(LocationParser::parse($text))->toBe(['lat' => 51.51, 'lon' => -0.13]);
})->with([
    "51.51 ,\t -0.13",
    "51.51\n\n-0.13",
    "lat :  51.51   lon:\t-0.13",
    'N  51.51    W  0.13',
    "51°30'26\"N  ,  0°07'39\"W",
    "51 ° 30 ′ 26 ″ N \t 0 ° 07 ′ 39 ″ W",
]);

it('rejects whitespace-padded fragments without backtracking through the padding', function (string $text) {
    preg_match('~~', '');
    $started = hrtime(true);

    expect(LocationParser::parse($text))->toBeNull()
        ->and(preg_last_error_msg())->toBe('No error')
        ->and((hrtime(true) - $started) / 1e6)->toBeLessThan(250.0);
})->with([
    'number then spaces' => ['51'.str_repeat(' ', 100).'x'],
    'number then kilobytes of spaces' => ['51'.str_repeat(' ', 5000).'x'],
    'labelled number then tabs' => ['lat 51'.str_repeat("\t", 5000).'lon'],
    'number and comma then spaces' => ['51,'.str_repeat(' ', 5000).'x'],
    'DMS then spaces' => ['51°30\'26"N'.str_repeat(' ', 50000).'x'],
    'DMS then spaces, comma, spaces' => ['51°30\'26"N'.str_repeat(' ', 25000).','.str_repeat(' ', 25000).'x'],
]);

it('recognises what3words addresses with or without the slashes', function (string $text, ?string $words) {
    expect(LocationParser::what3words($text))->toBe($words);
})->with([
    ['///filled.count.soap', 'filled.count.soap'],
    ['filled.count.soap', 'filled.count.soap'],
    ['  ///Filled.Count.Soap ', 'filled.count.soap'],
    ['https://what3words.com/filled.count.soap', 'filled.count.soap'],
    ['///gefüllt.zählen.seife', 'gefüllt.zählen.seife'],
    ['51.51, -0.13', null],
    ['filled.count', null],
    ['www.google.com', null],
]);

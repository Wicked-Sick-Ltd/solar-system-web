<?php

use App\Services\Observing\IanaTimezone;
use App\Services\Observing\InvalidSyncPayload;

it('preserves an IANA backward alias when the host tzdata omits the link', function (string $input, string $target, string $expected) {
    expect(IanaTimezone::canonical($input, [$target, 'Europe/London']))->toBe($expected);
})->with([
    'Calcutta' => ['Asia/Calcutta', 'Asia/Kolkata', 'Asia/Calcutta'],
    'calcutta lowercase' => ['asia/calcutta', 'Asia/Kolkata', 'Asia/Calcutta'],
    'Kiev' => ['Europe/Kiev', 'Europe/Kyiv', 'Europe/Kiev'],
    'Saigon' => ['Asia/Saigon', 'Asia/Ho_Chi_Minh', 'Asia/Saigon'],
    'US Eastern' => ['US/Eastern', 'America/New_York', 'US/Eastern'],
    'UCT folds to UTC' => ['UCT', 'Etc/UTC', 'UTC'],
]);

it('rejects a backward alias whose canonical zone is not installed', function () {
    expect(fn () => IanaTimezone::canonical('Asia/Calcutta', ['Europe/London']))->toThrow(InvalidSyncPayload::class);
});

it('uses the host identifier spelling before the backward table', function () {
    expect(IanaTimezone::canonical('europe/london', ['Europe/London']))->toBe('Europe/London');
    expect(IanaTimezone::canonical('GMT', ['GMT']))->toBe('UTC');
    expect(IanaTimezone::canonical('Etc/UTC', ['Etc/UTC']))->toBe('UTC');
    expect(IanaTimezone::canonical('Asia/Calcutta', ['Asia/Calcutta']))->toBe('Asia/Calcutta');
});

it('rejects a timezone the host list and the backward links both omit', function () {
    expect(fn () => IanaTimezone::canonical('Moon/Unknown', ['Europe/London']))->toThrow(InvalidSyncPayload::class);
});

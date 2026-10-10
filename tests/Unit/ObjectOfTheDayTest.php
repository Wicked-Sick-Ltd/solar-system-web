<?php

declare(strict_types=1);

use App\Support\ObjectOfTheDay;
use Carbon\CarbonImmutable;

beforeEach(fn () => $this->travelTo(CarbonImmutable::parse('2026-10-12 09:30:00', 'UTC')));

it('keeps the homepage pick: crc32 of the UTC year and zero-based day of year', function () {
    $date = CarbonImmutable::parse('2026-10-23', 'UTC');

    expect(ObjectOfTheDay::slugFor($date))
        ->toBe(ObjectOfTheDay::POOL[crc32('2026-295') % count(ObjectOfTheDay::POOL)])
        ->toBe('planet-saturn');
});

it('picks by UTC day whatever the timezone of the date given', function () {
    $lateInLondon = CarbonImmutable::parse('2026-07-01 00:30:00', 'Europe/London'); // 30 June UTC

    expect(ObjectOfTheDay::slugFor($lateInLondon))->toBe(ObjectOfTheDay::slugFor(CarbonImmutable::parse('2026-06-30', 'UTC')));
});

it('accepts real past dates up to today', function (string $value) {
    expect(ObjectOfTheDay::parse($value)?->format('Y-m-d'))->toBe($value);
})->with([ObjectOfTheDay::FIRST_DATE, '2026-02-28', '2026-10-12']);

it('rejects malformed, impossible, future and pre-launch dates', function (string $value) {
    expect(ObjectOfTheDay::parse($value))->toBeNull();
})->with(['2026-10-13', '2027-01-01', '2026-02-30', '2025-12-31', '2026-1-05', '20261012', '2026-10-12x', '']);

it('links neighbouring days only within the permitted range', function () {
    expect(ObjectOfTheDay::next(ObjectOfTheDay::today()))->toBeNull()
        ->and(ObjectOfTheDay::previous(CarbonImmutable::parse(ObjectOfTheDay::FIRST_DATE, 'UTC')))->toBeNull()
        ->and(ObjectOfTheDay::previous(ObjectOfTheDay::today())?->format('Y-m-d'))->toBe('2026-10-11');
});

it('builds the dated permalink', function () {
    expect(ObjectOfTheDay::url(CarbonImmutable::parse('2026-10-12', 'UTC')))->toBe(url('/today/2026-10-12'));
});

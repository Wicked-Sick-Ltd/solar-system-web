<?php

declare(strict_types=1);

use App\Support\Format;

it('returns null for absent values rather than a placeholder', function () {
    expect(Format::au(null))->toBeNull()
        ->and(Format::km(null))->toBeNull()
        ->and(Format::massKg(null))->toBeNull()
        ->and(Format::periodDays(null))->toBeNull()
        ->and(Format::degrees(null))->toBeNull()
        ->and(Format::date(null))->toBeNull()
        ->and(Format::date(''))->toBeNull();
});

it('formats astronomical units, trimming trailing zeros', function () {
    expect(Format::au(9.53667594))->toBe('9.537 AU')
        ->and(Format::au(1.0))->toBe('1 AU')
        ->and(Format::au(2.5, 2))->toBe('2.5 AU');
});

it('formats kilometres, switching to scientific notation when large', function () {
    expect(Format::km(58232.0))->toBe('58,232 km')
        ->and(Format::km(2_000_000.0))->toContain('× 10')
        ->and(Format::km(2_000_000.0))->toEndWith(' km');
});

it('renders mass in scientific notation with a superscript exponent', function () {
    expect(Format::massKg(5.6834e26))->toBe('5.683 × 10²⁶ kg');
});

it('chooses a readable unit for an orbital period', function () {
    expect(Format::periodDays(0.5))->toBe('12 hours')      // sub-day → hours
        ->and(Format::periodDays(687.0))->toBe('687 days')  // months → days
        ->and(Format::periodDays(10759.22))->toContain('years'); // long → years
});

it('keeps bare years intact and formats full dates', function () {
    expect(Format::date('1877'))->toBe('1877')
        ->and(Format::date('1877-08-18'))->toBe('18 August 1877');
});

it('formats whole counts with separators', function () {
    expect(Format::count(15546))->toBe('15,546');
});

it('labels azimuths with 16-point compass bearings', function () {
    expect(Format::bearing(0.0))->toBe('N')
        ->and(Format::bearing(22.5))->toBe('NNE')
        ->and(Format::bearing(285.0))->toBe('WNW')
        ->and(Format::bearing(359.0))->toBe('N');
});

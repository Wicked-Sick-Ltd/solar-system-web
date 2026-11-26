<?php

declare(strict_types=1);

use App\Services\SolarApi\Data\MeteorShower;
use App\Support\Format;
use App\Support\MeteorShowerPage;
use App\Support\Sky\UkMeteorViewing;

/** Mirrors the live IAU MDC parameter sets the hub reports for GEM and QUA (annual, most members). */
function ukViewingShower(string $code, string $name, float $longitude, float $ra, float $dec): MeteorShower
{
    $set = static fn (int $adNo, string $activity, float $lon, int $members) => [
        'iau_no' => 4, 'ad_no' => $adNo, 'code' => $code, 'name' => $name, 'activity' => $activity,
        'solar_longitude_deg' => $lon, 'ra_deg' => $ra, 'dec_deg' => $dec, 'n_members' => $members,
    ];

    return MeteorShower::fromArray(['iau_no' => 4, 'code' => $code, 'name' => $name, 'parameter_sets' => [
        $set(0, 'annual', $longitude + 0.1, 221),
        $set(4, '2002/08', $longitude - 1.0, 9999),
        $set(5, 'annual', $longitude, 4895),
    ]]);
}

it('matches the IMO 2026 Geminid maximum (solar longitude 262.2, 14 Dec 14h UT) to within 30 minutes', function () {
    $peak = UkMeteorViewing::nextSolarLongitude(262.2, new DateTimeImmutable('2026-12-01T00:00:00Z'));

    expect(abs($peak->getTimestamp() - (new DateTimeImmutable('2026-12-14T14:00:00Z'))->getTimestamp()))
        ->toBeLessThan(30 * 60);
});

it('puts the Geminids on the night of 13 to 14 December 2026 from the UK', function () {
    $viewing = UkMeteorViewing::forShower(ukViewingShower('GEM', 'Geminids', 262.0, 113.5, 32.3), new DateTimeImmutable('2026-11-26T10:00:00Z'));

    expect($viewing)->not->toBeNull()
        ->and($viewing['ad_no'])->toBe(5)
        ->and($viewing['peak_local']->format('Y-m-d'))->toBe('2026-12-14')
        ->and($viewing['peak_in_dark'])->toBeFalse()
        ->and($viewing['night_start_local']->format('Y-m-d'))->toBe('2026-12-13')
        ->and($viewing['night_end_local']->format('Y-m-d'))->toBe('2026-12-14')
        ->and($viewing['best_start_local'])->not->toBeNull()
        ->and((int) $viewing['highest_local']->format('G'))->toBeIn([1, 2, 3])
        ->and($viewing['highest_altitude_deg'])->toBeGreaterThan(65.0)->toBeLessThan(75.0)
        ->and(Format::bearing($viewing['highest_azimuth_deg']))->toBe('S')
        // Waxing crescent around a quarter lit (IMO / USNO: new Moon 9 Dec, first quarter 17 Dec).
        ->and($viewing['moon_waxing'])->toBeTrue()
        ->and($viewing['moon_illumination'])->toBeGreaterThan(0.15)->toBeLessThan(0.35);
});

it('puts the Quadrantids on the night of 3 to 4 January 2027 with the radiant climbing towards dawn', function () {
    $viewing = UkMeteorViewing::forShower(ukViewingShower('QUA', 'Quadrantids', 283.0, 230.2, 49.5), new DateTimeImmutable('2026-11-26T10:00:00Z'));

    expect($viewing['night_start_local']->format('Y-m-d'))->toBe('2027-01-03')
        ->and($viewing['night_end_local']->format('Y-m-d'))->toBe('2027-01-04')
        ->and($viewing['peak_in_dark'])->toBeTrue()
        ->and($viewing['moon_waxing'])->toBeFalse()
        ->and($viewing['best_end_local'])->toEqual($viewing['dark_end_local']);
});

it('keeps showing a peak that passed a few hours ago', function () {
    $viewing = UkMeteorViewing::forShower(ukViewingShower('GEM', 'Geminids', 262.0, 113.5, 32.3), new DateTimeImmutable('2026-12-14T15:00:00Z'));

    expect($viewing['peak_local']->format('Y-m-d'))->toBe('2026-12-14');
});

it('returns nothing without a peak longitude or radiant', function () {
    $shower = MeteorShower::fromArray(['iau_no' => 1, 'code' => 'XXX', 'name' => 'Exampleids', 'parameter_sets' => [
        ['iau_no' => 1, 'ad_no' => 0, 'code' => 'XXX', 'name' => 'Exampleids', 'activity' => 'annual', 'solar_longitude_deg' => 100.0],
    ]]);

    expect(UkMeteorViewing::forShower($shower))->toBeNull();
});

it('builds UK FAQ answers from the calculation and appends configured copy', function () {
    $shower = ukViewingShower('GEM', 'Geminids', 262.0, 113.5, 32.3);
    $viewing = UkMeteorViewing::forShower($shower, new DateTimeImmutable('2026-11-26T10:00:00Z'));
    $faq = MeteorShowerPage::ukFaq($shower, $viewing, [['question' => 'Q?', 'answer' => 'A.'], ['question' => '', 'answer' => 'skip']]);

    expect($faq)->toHaveCount(4)
        ->and($faq[0]['question'])->toBe('What time is the Geminid meteor shower tonight in the UK?')
        ->and($faq[0]['answer'])->toContain('the night of 13–14 December 2026')
        ->and($faq[3])->toBe(['question' => 'Q?', 'answer' => 'A.']);
});

it('labels nights, directions and Moon phases in plain UK English', function () {
    expect(UkMeteorViewing::nightLabel(new DateTimeImmutable('2026-12-13'), new DateTimeImmutable('2026-12-14')))->toBe('13–14 December 2026')
        ->and(UkMeteorViewing::nightLabel(new DateTimeImmutable('2027-01-31'), new DateTimeImmutable('2027-02-01')))->toBe('31 January – 1 February 2027')
        ->and(UkMeteorViewing::nightLabel(new DateTimeImmutable('2026-12-31'), new DateTimeImmutable('2027-01-01')))->toBe('31 December 2026 – 1 January 2027')
        ->and(UkMeteorViewing::direction(181.0))->toBe('south')
        ->and(UkMeteorViewing::direction(45.0))->toBe('north-east')
        ->and(UkMeteorViewing::direction(22.5))->toBe('north-north-east')
        ->and(UkMeteorViewing::moonPhaseName(0.23, true))->toBe('waxing crescent')
        ->and(UkMeteorViewing::moonPhaseName(0.99, false))->toBe('full')
        ->and(UkMeteorViewing::moonPhaseName(0.7, false))->toBe('waning gibbous');
});

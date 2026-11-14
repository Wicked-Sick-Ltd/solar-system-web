<?php

declare(strict_types=1);

use App\Services\SolarApi\Data\MeteorShower;
use App\Support\MeteorShowerPage;
use Carbon\CarbonImmutable;

it('builds shower titles from the name, the activity year and the reported peak', function () {
    $now = CarbonImmutable::parse('2026-11-14T18:20:00Z');
    $ursids = showerRecord('URS', 'Ursids', [
        ['activity' => 'annual', 'solar_longitude_deg' => 270.5, 'n_members' => 40],
        ['activity' => '2011', 'solar_longitude_deg' => 271.0, 'n_members' => 400],
    ]);

    expect(MeteorShowerPage::title($ursids, $now))
        ->toBe('Ursids 2026: Peak Dates, Best Time to See Them and Where to Look')
        ->and(MeteorShowerPage::description($ursids, $now))->toContain('solar longitude 270.5°')
        ->and(MeteorShowerPage::description($ursids, $now))->not->toContain('271');

    $outburst = showerRecord('EXA', 'Exampleids', [
        ['activity' => '2002/2006', 'solar_longitude_deg' => null, 'n_members' => 3],
    ]);

    expect(MeteorShowerPage::title($outburst, $now))
        ->toBe('Exampleids 2006: Peak Dates, Best Time to See Them and Where to Look')
        ->and(MeteorShowerPage::description($outburst, $now))->not->toContain('solar longitude')
        ->and(MeteorShowerPage::canonicalCode('leo'))->toBe('LEO')
        ->and(MeteorShowerPage::canonicalCode('LEO'))->toBe('LEO');
});

/**
 * @param  list<array{activity:?string, solar_longitude_deg:?float, n_members:?int}>  $sets
 */
function showerRecord(string $code, string $name, array $sets): MeteorShower
{
    $rows = [];
    foreach ($sets as $index => $set) {
        $rows[] = [
            'iau_no' => 1,
            'ad_no' => $index,
            'code' => $code,
            'name' => $name,
            'activity' => $set['activity'],
            'solar_longitude_deg' => $set['solar_longitude_deg'],
            'n_members' => $set['n_members'],
        ];
    }

    return MeteorShower::fromArray([
        'iau_no' => 1,
        'code' => $code,
        'name' => $name,
        'parameter_sets' => $rows,
    ]);
}

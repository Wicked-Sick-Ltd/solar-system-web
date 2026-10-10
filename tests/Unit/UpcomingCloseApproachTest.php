<?php

declare(strict_types=1);

use App\Services\SolarApi\Data\CloseApproach;
use App\Support\CloseApproachFormat;
use App\Support\UpcomingCloseApproach;
use Carbon\CarbonImmutable;

function pass(array $overrides = []): CloseApproach
{
    return CloseApproach::fromArray(array_replace([
        'object_id' => 'ast-1',
        'name' => 'One',
        'body' => 'Earth',
        'cd_iso' => '2026-10-15T20:59:00Z',
        'dist_au' => 0.00672,
        'v_rel_km_s' => 9.02,
    ], $overrides));
}

it('keeps the soonest future pass within 10 lunar distances', function () {
    $now = CarbonImmutable::parse('2026-10-10T12:00:00Z');
    $next = UpcomingCloseApproach::select([
        pass(['object_id' => 'later', 'name' => 'Later', 'cd_iso' => '2026-10-20T00:00:00Z', 'dist_au' => 0.001]),
        pass(['object_id' => 'sooner', 'name' => 'Sooner', 'cd_iso' => '2026-10-12T00:00:00Z', 'dist_au' => 0.02]),
        pass(['object_id' => 'past', 'name' => 'Past', 'cd_iso' => '2026-10-10T11:00:00Z', 'dist_au' => 0.001]),
        pass(['object_id' => 'far', 'name' => 'Far', 'cd_iso' => '2026-10-11T00:00:00Z', 'dist_au' => 0.04]),
        pass(['object_id' => 'moon', 'name' => 'Lunar', 'body' => 'Moon', 'cd_iso' => '2026-10-11T00:00:00Z', 'dist_au' => 0.001]),
    ], $now);

    expect($next?->name)->toBe('Sooner');
});

it('includes a pass at exactly 10 lunar distances and drops a past duplicate', function () {
    $now = CarbonImmutable::parse('2026-10-10T12:00:00Z');
    $edge = UpcomingCloseApproach::maxDistanceAu();
    $next = UpcomingCloseApproach::select([
        pass(['object_id' => 'edge', 'name' => 'Edge', 'dist_au' => $edge, 'cd_iso' => '2026-10-16T00:00:00Z']),
        pass(['object_id' => 'edge', 'name' => 'Edge', 'dist_au' => $edge / 2, 'cd_iso' => '2026-10-16T00:00:00Z']),
        pass(['object_id' => 'beyond', 'name' => 'Beyond', 'dist_au' => $edge + 0.0001, 'cd_iso' => '2026-10-11T00:00:00Z']),
    ], $now);

    expect($next?->objectId)->toBe('edge')
        ->and($next?->distAu)->toBe($edge / 2);
});

it('describes the remaining time without seconds', function () {
    $now = CarbonImmutable::parse('2026-10-10T12:00:00Z');

    expect(UpcomingCloseApproach::countdown($now, $now->addSeconds(59)))->toBe('Passes in less than a minute')
        ->and(UpcomingCloseApproach::countdown($now, $now->addSeconds(60)))->toBe('Passes in 1 minute')
        ->and(UpcomingCloseApproach::countdown($now, $now->addHours(3)->addMinutes(5)))->toBe('Passes in 3 hours 5 minutes')
        ->and(UpcomingCloseApproach::countdown($now, $now->addDays(2)))->toBe('Passes in 2 days')
        ->and(UpcomingCloseApproach::countdown($now, $now->addDays(5)->addHours(8)->addMinutes(59)))->toBe('Passes in 5 days 8 hours')
        ->and(UpcomingCloseApproach::countdown($now, $now->subMinute()))->toBe('This pass time has been reached.')
        ->and(UpcomingCloseApproach::utcLabel(CarbonImmutable::parse('2026-10-15T20:59:00Z')))->toBe('15 October 2026, 20:59 UTC');
});

it('uses a measured diameter and otherwise estimates a range from absolute magnitude', function () {
    $measured = CloseApproachFormat::size(pass(['radius_km' => 0.05]));
    $estimated = CloseApproachFormat::size(pass(['absolute_magnitude_h' => 26]));
    $absent = CloseApproachFormat::size(pass());
    $ignored = CloseApproach::fromArray([
        'name' => 'Bad', 'body' => 'Earth', 'cd_iso' => '2026-10-15T20:59:00Z',
        'radius_km' => -1, 'absolute_magnitude_h' => 'nope',
    ]);

    expect($measured)->toMatchArray(['text' => '100 m', 'approximate' => false, 'note' => null])
        ->and($estimated['approximate'])->toBeTrue()
        ->and($estimated['text'])->toStartWith('about ')
        ->and($estimated['note'])->toContain('H = 26')
        ->and($estimated['note'])->toContain('0.05')
        ->and($absent)->toBeNull()
        ->and($ignored->radiusKm)->toBeNull()
        ->and($ignored->absoluteMagnitudeH)->toBeNull()
        ->and($ignored->diameterKm())->toBeNull();
});

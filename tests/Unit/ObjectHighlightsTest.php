<?php

declare(strict_types=1);

use App\Services\SolarApi\Data\ObjectDetail;
use App\Support\ObjectHighlights;

/** @param array<string,mixed> $orbital @param array<string,mixed> $physical */
function highlightObject(string $name, string $type, array $orbital = [], array $physical = []): ObjectDetail
{
    return ObjectDetail::fromArray([
        'id' => strtolower($type.'-'.$name), 'name' => $name, 'object_type' => $type,
        'orbital' => $orbital ?: null, 'physical' => $physical ?: null,
    ]);
}

it('derives the most surprising fact the measurements support', function (ObjectDetail $object, string $fact) {
    expect(ObjectHighlights::funFact($object))->toBe($fact);
})->with([
    'solar day longer than the year' => [
        fn () => highlightObject('Mercury', 'planet', ['semi_major_axis_au' => 0.387, 'orbital_period_days' => 87.97], ['length_of_day_hours' => 4222.6, 'rotation_period_hours' => 1407.6]),
        'Sunrise to sunrise, a day on Mercury lasts 176 Earth days — longer than its 88-day year.',
    ],
    'retrograde rotation longer than the year' => [
        fn () => highlightObject('Venus', 'planet', ['semi_major_axis_au' => 0.723, 'orbital_period_days' => 224.7], ['rotation_period_hours' => -5832.6, 'length_of_day_hours' => 2802]),
        'Venus takes 243 Earth days to spin once — longer than its 225-day year.',
    ],
    'tipped on its side' => [
        fn () => highlightObject('Uranus', 'planet', ['semi_major_axis_au' => 19.19, 'orbital_period_days' => 30688.5], ['axial_tilt_deg' => 97.77, 'radius_km' => 25362]),
        'Uranus is tipped 97.8° on its side, so it rolls around the Sun like a ball.',
    ],
    'less dense than water' => [
        fn () => highlightObject('Saturn', 'planet', ['semi_major_axis_au' => 9.537], ['density_g_cm3' => 0.687, 'radius_km' => 58232]),
        'Saturn is less dense than water — 0.69 g/cm³ on average.',
    ],
    'Earths across' => [
        fn () => highlightObject('Jupiter', 'planet', ['semi_major_axis_au' => 5.2], ['radius_km' => 69911, 'surface_gravity_m_s2' => 24.79]),
        'About 11 Earths would fit side by side across Jupiter.',
    ],
    'tidally locked moon, phrased for the Moon' => [
        fn () => highlightObject('Moon', 'moon', ['semi_major_axis_au' => 0.00257, 'orbital_period_days' => 27.32], ['rotation_period_hours' => 655.7]),
        'The Moon is tidally locked: it spins once per orbit (27.3 days), so the same face always points at its planet.',
    ],
    'weight on a small world' => [
        fn () => highlightObject('Mars', 'planet', ['semi_major_axis_au' => 1.524, 'orbital_period_days' => 687], ['radius_km' => 3389.5, 'surface_gravity_m_s2' => 3.71]),
        'You would weigh just 38% of your Earth weight on Mars.',
    ],
    'light travel time' => [
        fn () => highlightObject('Earth', 'planet', ['semi_major_axis_au' => 1.0, 'orbital_period_days' => 365.25], ['radius_km' => 6371, 'surface_gravity_m_s2' => 9.8]),
        'Sunlight takes 8 min 19 s to reach Earth.',
    ],
    'long orbit' => [
        fn () => highlightObject('Halley', 'comet', ['semi_major_axis_au' => 17.93, 'orbital_period_days' => 27728.0]),
        'Halley takes 75.9 years to travel once around the Sun.',
    ],
]);

it('never draws Sun-relative facts from planetocentric moon orbits', function () {
    $moon = highlightObject('Phobos', 'moon', ['semi_major_axis_au' => 0.0000627, 'orbital_period_days' => 0.319]);

    expect(ObjectHighlights::funFact($moon))->toBe('Phobos circles its planet once every 7.7 hours.')
        ->and(ObjectHighlights::keyStats($moon))->toContain(['label' => 'From its planet', 'value' => '9,380 km']);
});

it('says nothing rather than guess for a sparse record', function () {
    $object = highlightObject('2024 AB', 'asteroid');

    expect(ObjectHighlights::funFact($object))->toBeNull()
        ->and(ObjectHighlights::keyStats($object))->toBe([]);
});

it('lists diameter, distance from the Sun and orbital period as key stats', function () {
    $object = highlightObject('Mars', 'planet', ['semi_major_axis_au' => 1.524, 'orbital_period_days' => 687], ['radius_km' => 3389.5]);

    expect(ObjectHighlights::keyStats($object))->toBe([
        ['label' => 'Diameter', 'value' => '6,779 km'],
        ['label' => 'From the Sun', 'value' => '1.52 AU'],
        ['label' => 'Orbital period', 'value' => '687 days'],
    ]);
});

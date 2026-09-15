<?php

declare(strict_types=1);

use App\Services\SolarApi\Data\ObjectDetail;
use App\Services\SolarApi\Data\Position;
use App\Support\PlutoDistance;

it('falls back to the NASA fact-sheet values when the backend has no orbital data', function () {
    $object = ObjectDetail::fromArray(plutoDetail());

    $d = PlutoDistance::for($object, null);

    expect($d->meanAu)->toBe(39.48)
        ->and($d->perihelionAu)->toBe(29.66)
        ->and($d->aphelionAu)->toBe(49.31)
        ->and($d->nowAu)->toBeNull()
        ->and($d->isLive())->toBeFalse();
});

it('prefers live orbital elements and the current position when the backend has them', function () {
    $object = ObjectDetail::fromArray(plutoDetail() + [] /* keep fixture */);
    $object = ObjectDetail::fromArray(array_replace(plutoDetail(), [
        'orbital' => ['semi_major_axis_au' => 39.5, 'perihelion_au' => 29.7, 'aphelion_au' => 49.3],
    ]));
    $position = Position::fromArray(['input_date' => '2026-09-15', 'distance_from_sun_au' => 35.62]);

    $d = PlutoDistance::for($object, $position);

    expect($d->meanAu)->toBe(39.5)
        ->and($d->perihelionAu)->toBe(29.7)
        ->and($d->aphelionAu)->toBe(49.3)
        ->and($d->nowAu)->toBe(35.62)
        ->and($d->isLive())->toBeTrue();
});

it('only applies to Pluto', function () {
    expect(PlutoDistance::appliesTo(ObjectDetail::fromArray(plutoDetail())))->toBeTrue()
        ->and(PlutoDistance::appliesTo(ObjectDetail::fromArray(saturnDetail())))->toBeFalse();
});

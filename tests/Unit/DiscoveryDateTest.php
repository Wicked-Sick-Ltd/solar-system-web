<?php

declare(strict_types=1);

use App\Services\SolarApi\Data\ObjectDetail;

it('prefers the catalogue discovery date over an earlier MPC plate date', function () {
    $object = ObjectDetail::fromArray(plutoDetail());

    expect($object->discoveryDate)->toBe('1930-02-18')
        ->and($object->discovery?->discoveredOn)->toBe('1930-01-23')
        ->and($object->discoveryDateForDisplay())->toBe('1930-02-18');
});

it('uses the MPC date when the catalogue has no discovery date', function () {
    $object = ObjectDetail::fromArray(apophisDetail());

    expect($object->discoveryDate)->toBeNull()
        ->and($object->discoveryDateForDisplay())->toBe('2004-06-19');
});

it('keeps the date when the catalogue and the MPC record agree', function () {
    $object = ObjectDetail::fromArray(array_replace(apophisDetail(), [
        'discovery_date' => '2004-06-19',
    ]));

    expect($object->discoveryDateForDisplay())->toBe('2004-06-19');
});

it('uses the catalogue date when there is no MPC record', function () {
    $object = ObjectDetail::fromArray(array_replace(plutoDetail(), [
        'discovery' => null,
    ]));

    expect($object->discovery)->toBeNull()
        ->and($object->discoveryDateForDisplay())->toBe('1930-02-18');
});

it('returns null when neither date is recorded', function () {
    expect(ObjectDetail::fromArray(lunaDetail())->discoveryDateForDisplay())->toBeNull();
});

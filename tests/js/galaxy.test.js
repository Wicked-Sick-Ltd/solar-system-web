import { test } from 'node:test';
import assert from 'node:assert/strict';
import { distanceUncertainty, position, visibleHosts, SUN_GALACTOCENTRIC } from '../../resources/js/galaxy-data.js';

const host = { distance_pc: 5, x_pc: 3, y_pc: 4, z_pc: 0, galactocentric_x_pc: -8119, galactocentric_y_pc: 4, galactocentric_z_pc: 20.8 };
test('render conversion preserves distance and north-up handedness', () => {
    assert.deepEqual(position(host, 'nearby'), [3, 0, -4]);
    assert.equal(Math.hypot(...position(host, 'nearby')), 5);
    assert.deepEqual(position(host, 'galaxy'), [-8119, 20.8, -4]);
    assert.ok(Math.abs(Math.hypot(...SUN_GALACTOCENTRIC) - 8122) < 1e-8);
});
test('distance filters never manufacture positions for missing data', () => {
    const rows = [host, { ...host, distance_pc: null }, { ...host, x_pc: null }, { ...host, distance_pc: 500 }];
    assert.equal(visibleHosts(rows, '25').length, 1);
    assert.equal(visibleHosts(rows, 'all').length, 2);
    assert.equal(visibleHosts(rows, '2').length, 0);
});

test('distance uncertainties preserve small values and zero separately from absence', () => {
    assert.equal(distanceUncertainty({ distance_error_plus_pc: 0.00034, distance_error_minus_pc: -0.00035 }, 'en-GB'),
        'Distance uncertainty: upper +1.109E-3 light-years; lower −1.142E-3 light-years.');
    assert.equal(distanceUncertainty({ distance_error_plus_pc: 0, distance_error_minus_pc: null }, 'en-GB'),
        'Distance uncertainty: upper +0 light-years; lower not reported.');
    assert.equal(distanceUncertainty({ distance_error_minus_pc: -0.1 }, 'en-GB'),
        'Distance uncertainty: upper not reported; lower −0.3262 light-years.');
    assert.equal(distanceUncertainty({}, 'en-GB'),
        'Distance uncertainty: upper not reported; lower not reported.');
});

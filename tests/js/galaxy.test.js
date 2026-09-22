import { test } from 'node:test';
import assert from 'node:assert/strict';
import { position, visibleHosts, SUN_GALACTOCENTRIC } from '../../resources/js/galaxy-data.js';

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

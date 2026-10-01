import test from 'node:test';
import assert from 'node:assert/strict';
import { validateHorizonMask, horizonAltitude, minimumAltitudeAt, parseHorizonText, horizonText } from '../../resources/js/observing/horizon.js';
const point = (azimuthDeg, minAltitudeDeg) => ({ azimuthDeg, minAltitudeDeg });

test('unknown horizon is distinct from a measured zero-degree horizon', () => {
    assert.equal(horizonAltitude(null, 90), null);
    assert.equal(horizonAltitude([point(0, 0), point(180, 0)], 90), 0);
    assert.deepEqual(minimumAltitudeAt({ minAltitudeDeg: 20, horizonMask: null }, 90), { minimumAltitudeDeg: 20, horizonKnown: false });
});
test('mask canonicalizes order and 360 degrees to north without changing altitudes', () => {
    assert.deepEqual(validateHorizonMask([point(180, 10), point(360, -5)]), [point(0, -5), point(180, 10)]);
    assert.equal(horizonAltitude([point(180, 10), point(360, -5)], 0), -5);
    assert.equal(horizonAltitude([point(180, 10), point(360, -5)], 360), -5);
});
test('linear interpolation wraps both sides of the 0/360 seam', () => {
    const mask = [point(10, 30), point(180, 0), point(350, 10)];
    assert.equal(horizonAltitude(mask, 0), 20);
    assert.equal(horizonAltitude(mask, 360), 20);
    assert.equal(horizonAltitude(mask, 355), 15);
    assert.equal(horizonAltitude(mask, 5), 25);
    assert.equal(horizonAltitude(mask, 10), 30);
});
test('two-point sparse masks interpolate across both arcs while baseline stays independent', () => {
    const mask = [point(90, 10), point(270, 30)];
    assert.equal(horizonAltitude(mask, 180), 20);
    assert.equal(horizonAltitude(mask, 0), 20);
    assert.deepEqual(minimumAltitudeAt({ minAltitudeDeg: 25, horizonMask: mask }, 90), { minimumAltitudeDeg: 25, horizonKnown: true });
    assert.deepEqual(minimumAltitudeAt({ minAltitudeDeg: 25, horizonMask: mask }, 270), { minimumAltitudeDeg: 30, horizonKnown: true });
    assert.equal(minimumAltitudeAt({ minAltitudeDeg: 90, horizonMask: mask }, 270).minimumAltitudeDeg, 90);
});
for (const [label, value] of [
    ['empty array', []], ['one point', [point(0, 1)]], ['too many points', Array.from({ length: 73 }, (_, i) => point(i, 1))],
    ['duplicate', [point(0, 1), point(0, 2)]], ['seam duplicate', [point(0, 1), point(360, 2)]],
    ['negative azimuth', [point(-1, 0), point(90, 2)]], ['beyond360', [point(361, 0), point(90, 2)]],
    ['above zenith', [point(0, 91), point(90, 2)]], ['below nadir', [point(0, -91), point(90, 2)]],
    ['unknown field', [{ ...point(0, 1), measured: true }, point(90, 2)]], ['numeric string', [point('0', 1), point(90, 2)]],
    ['boolean', [point(0, true), point(90, 2)]], ['nonfinite', [point(0, Infinity), point(90, 2)]], ['bad point', [null, point(90, 2)]],
]) test(`rejects malformed mask: ${label}`, () => assert.throws(() => validateHorizonMask(value)));

test('user-entered text round-trips, preserves negative altitude and refuses partial lines', () => {
    const mask = parseHorizonText('360, -5\n180, 20');
    assert.equal(horizonText(mask), '0, -5\n180, 20');
    assert.deepEqual(parseHorizonText(horizonText(mask)), mask);
    assert.equal(parseHorizonText('  '), null);
    for (const text of ['0,20\n90,', '0,1\n90,2,3', '0,1\n\n90,2', 'a,b', 'x'.repeat(8001)]) assert.throws(() => parseHorizonText(text));
});
test('invalid queried direction and invalid baseline never create an interpolated result', () => {
    for (const value of [-1, 361, null, true, '90', Infinity]) assert.throws(() => horizonAltitude(null, value));
    assert.throws(() => minimumAltitudeAt({ minAltitudeDeg: -1, horizonMask: null }, 0));
});

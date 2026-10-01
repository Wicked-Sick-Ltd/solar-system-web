import test from 'node:test';
import assert from 'node:assert/strict';
import { telescopeOptics, binocularOptics, angularComparison, opticalNumber } from '../../resources/js/observing/optics.js';

const scope = { kind: 'telescope', apertureMm: 200, focalLengthMm: 1000 };
const eyepiece = { kind: 'eyepiece', focalLengthMm: 20, apparentFovDeg: 60, fieldStopMm: null };
const close = (actual, expected) => assert.ok(Math.abs(actual - expected) < 1e-10, `${actual} != ${expected}`);

test('published 1000mm/20mm example gives 50x; aperture200mm gives exit pupil4mm', () => {
    const result = telescopeOptics(scope, eyepiece);
    assert.equal(result.magnification, 50);
    assert.equal(result.exitPupilMm, 4);
    assert.equal(result.trueFovDeg, 1.2);
    assert.equal(result.fovMethod, 'apparent-field');
});
test('Barlow and reducer factors propagate through focal length, magnification, exit pupil and field', () => {
    const barlow = telescopeOptics(scope, eyepiece, { kind: 'barlow', factor: 2 });
    assert.deepEqual(barlow, { effectiveFocalLengthMm: 2000, magnification: 100, exitPupilMm: 2, trueFovDeg: 0.6, fovMethod: 'apparent-field' });
    const reducer = telescopeOptics(scope, eyepiece, { kind: 'reducer', factor: 0.5 });
    assert.deepEqual(reducer, { effectiveFocalLengthMm: 500, magnification: 25, exitPupilMm: 8, trueFovDeg: 2.4, fovMethod: 'apparent-field' });
});
test('published effective field stop takes precedence over a disagreeing apparent field', () => {
    const result = telescopeOptics(scope, { ...eyepiece, fieldStopMm: 20 });
    close(result.trueFovDeg, 1.1459155902616465);
    assert.equal(result.fovMethod, 'field-stop');
    close(telescopeOptics(scope, { ...eyepiece, fieldStopMm: 20 }, { kind: 'barlow', factor: 2 }).trueFovDeg, 0.5729577951308232);
});
test('missing independent optical measurements do not become fabricated zero outputs', () => {
    const noEyepiece = telescopeOptics(scope);
    assert.equal(noEyepiece.effectiveFocalLengthMm, 1000);
    assert.equal(noEyepiece.magnification, null);
    const noAperture = telescopeOptics({ ...scope, apertureMm: null }, eyepiece);
    assert.equal(noAperture.magnification, 50);
    assert.equal(noAperture.exitPupilMm, null);
    const noFields = telescopeOptics(scope, { ...eyepiece, apparentFovDeg: null });
    assert.equal(noFields.trueFovDeg, null);
    assert.equal(noFields.fovMethod, null);
});
for (const invalid of [0, -1, Infinity, NaN, '20', true, [], {}]) test(`invalid optical values (${String(invalid)}) remain unknown`, () => {
    assert.equal(telescopeOptics({ ...scope, focalLengthMm: invalid }, eyepiece).magnification, null);
    assert.equal(telescopeOptics(scope, { ...eyepiece, focalLengthMm: invalid }).magnification, null);
    assert.equal(telescopeOptics(scope, { ...eyepiece, fieldStopMm: invalid }).trueFovDeg, null);
    assert.equal(binocularOptics({ kind: 'binocular', apertureMm: 50, magnification: invalid }).exitPupilMm, null);
});
test('malformed or incompatible accessory selection does not silently mean no accessory', () => {
    for (const accessory of [{ kind: 'reducer', factor: 2 }, { kind: 'barlow', factor: 0.5 }, { kind: 'telescope', factor: 2 }, {}]) assert.equal(telescopeOptics(scope, eyepiece, accessory).magnification, null);
});
test('binocular exit pupil uses stated magnification and field stays unknown unless entered', () => {
    const binocular = { kind: 'binocular', apertureMm: 50, magnification: 10 };
    assert.deepEqual(binocularOptics(binocular), { effectiveFocalLengthMm: null, magnification: 10, exitPupilMm: 5, trueFovDeg: null, fovMethod: null });
    assert.equal(binocularOptics(binocular, 6.5).trueFovDeg, 6.5);
    assert.equal(binocularOptics(binocular, 6.5).fovMethod, 'stated');
    assert.equal(binocularOptics(binocular, 181).trueFovDeg, null);
});
test('unphysical angular outputs and floating overflow are withheld', () => {
    assert.equal(telescopeOptics({ ...scope, focalLengthMm: 0.1 }, eyepiece).trueFovDeg, null);
    assert.equal(telescopeOptics({ ...scope, focalLengthMm: Number.MAX_VALUE }, eyepiece, { kind: 'barlow', factor: 2 }).effectiveFocalLengthMm, null);
});
test('angular comparison uses diameter ratio and a shared scale, including targets larger than field', () => {
    const inside = angularComparison(1, 30);
    assert.equal(inside.fieldFraction, 0.5); assert.equal(inside.fits, true);
    assert.equal(inside.fieldRadius, 80); assert.equal(inside.targetRadius, 40);
    const outside = angularComparison(0.5, 60);
    assert.equal(outside.fieldFraction, 2); assert.equal(outside.fits, false);
    assert.equal(outside.fieldRadius, 40); assert.equal(outside.targetRadius, 80);
    assert.equal(angularComparison(1, 60).fits, true);
});
test('unknown, invalid or out-of-range angular inputs never create a preview', () => {
    for (const value of [null, undefined, 0, -1, NaN, Infinity, '60', true]) {
        assert.equal(angularComparison(1, value), null);
        assert.equal(angularComparison(value, 60), null);
    }
    assert.equal(angularComparison(181, 60), null);
    assert.equal(angularComparison(1, 10801), null);
});
test('tiny positive outputs retain significant values and diagram ratios without minimum-size exaggeration', () => {
    const field = telescopeOptics({ ...scope, focalLengthMm: 100000 }, { ...eyepiece, fieldStopMm: 0.1 }).trueFovDeg;
    assert.notEqual(opticalNumber(field), '0');
    const tiny = angularComparison(1, 0.00001);
    assert.ok(tiny.targetRadius < 0.001);
    assert.notEqual(opticalNumber(tiny.fieldFraction), '0');
    assert.equal(opticalNumber(null), 'Unknown');
});

// Exact rectilinear geometry; small-angle approximations would not pass the wide-field case.
import { cameraOptics, cameraComparison } from '../../resources/js/observing/optics.js';
const sensor = { kind: 'camera', sensorWidthMm: 36, sensorHeightMm: 24, pixelSizeUm: 5 };
test('camera sensor dimensions produce known angular width,height and central pixel scale', () => {
    const result = cameraOptics(scope, sensor);
    close(result.widthDeg, 2.062425339775906);
    close(result.heightDeg, 1.3750327092781995);
    close(result.pixelScaleArcsec, 1.0313240312333338);
});
test('wide camera field uses atan rather than a small-angle linear estimate', () => {
    const result = cameraOptics({ ...scope, focalLengthMm: 18 }, sensor);
    close(result.widthDeg, 90);
    close(result.heightDeg, 67.38013505195957);
});
test('camera accessory scaling and unknown pixel size preserve independent sensor fields', () => {
    const base = cameraOptics(scope, { ...sensor, pixelSizeUm: null });
    assert.equal(base.pixelScaleArcsec, null);
    assert.ok(base.widthDeg > 0 && base.heightDeg > 0);
    const amplified = cameraOptics(scope, sensor, { kind: 'barlow', factor: 2 });
    close(amplified.widthDeg, 2 * Math.atan(36 / 4000) * 180 / Math.PI);
    for (const bad of [null, 0, -1, true, '36', Infinity]) assert.equal(cameraOptics(scope, { ...sensor, sensorWidthMm: bad }).widthDeg, null);
    assert.equal(cameraOptics({ kind: 'binocular', magnification: 10 }, sensor).widthDeg, null);
});
test('camera comparison preserves projection aspect ratio rather than treating a rectangle as a circle', () => {
    const field = cameraOptics({ ...scope, focalLengthMm: 18 }, sensor);
    const comparison = cameraComparison(field.widthDeg, field.heightDeg, 60);
    close(comparison.width / comparison.height, 1.5);
    assert.equal(comparison.fits, true);
    assert.equal(cameraComparison(field.widthDeg, field.heightDeg, 70 * 60).fits, false);
    assert.equal(cameraComparison(field.widthDeg, field.heightDeg, 10800), null);
    assert.equal(cameraComparison(null, 1, 60), null);
});

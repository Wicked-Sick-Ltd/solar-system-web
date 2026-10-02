import test from 'node:test';
import assert from 'node:assert/strict';
import { compareEyepieces, temporaryTelescope, temporaryEyepiece, validateSuggestionTargets, catalogueDiameter } from '../../resources/js/observing/equipment-suggestions.js';
const scope = temporaryTelescope(200, 1000);
const eye = (id, focal, field = 60) => ({ ...temporaryEyepiece(focal, field, null), id, name: id });
const targets = () => [{ id: 'openngc:NGC0224', name: 'Andromeda', catalogue: { source: 'openngc', source_url: 'https://example.test/source', snapshot_sha256: 'a'.repeat(64), attribution: 'Source author', license: 'CC BY-SA 4.0', license_url: 'https://example.test/license', appearance: { families: ['deep_sky'], magnitude: 3.44, magnitude_band: 'V', magnitude_flag: null, magnitude_code: null, major_axis_arcmin: 177.83, minor_axis_arcmin: 69.66 } } }];

test('1000mm / 20mm yields 50x with 4mm exit pupil; order is explicit and known fields lead', () => {
    const eyes = [eye('short', 10), eye('long', 20), eye('unknown', 30, null)];
    const result = compareEyepieces({ instrument: scope, eyepieces: eyes });
    assert.deepEqual(result.map(row => row.id), ['unknown', 'long', 'short']);
    assert.equal(result[1].magnification, 50); assert.equal(result[1].exitPupilMm, 4);
    assert.equal(result[1].trueFovDeg, 1.2);
    assert.deepEqual(compareEyepieces({ instrument: scope, eyepieces: eyes, order: 'widest-field' }).map(row => row.id), ['long', 'short', 'unknown']);
    assert.deepEqual(compareEyepieces({ instrument: scope, eyepieces: eyes, order: 'highest-power' }).map(row => row.id), ['short', 'long', 'unknown']);
    assert.deepEqual(eyes.map(row => row.id), ['short', 'long', 'unknown']);
});
test('ties use exact stable identities independently of input order and names', () => {
    const a = eye('a', 20), b = eye('b', 20);
    for (const order of ['lowest-power', 'highest-power', 'widest-field']) assert.deepEqual(compareEyepieces({ instrument: scope, eyepieces: [b, a], order }).map(row => row.id), ['a', 'b']);
});
test('known field-stop model takes precedence and Barlow changes magnification and containment', () => {
    const e = { ...eye('eye', 20), fieldStopMm: 20 };
    const row = compareEyepieces({ instrument: scope, eyepieces: [e], accessory: { kind: 'barlow', factor: 2 }, diameterArcmin: 60 })[0];
    assert.equal(row.magnification, 100); assert.equal(row.exitPupilMm, 2); assert.equal(row.fovMethod, 'field-stop');
    assert.equal(row.comparison.fits, false);
});
test('unknown field remains unknown, never silently treated as zero or a contained target', () => {
    const row = compareEyepieces({ instrument: scope, eyepieces: [eye('eye', 20, null)], diameterArcmin: 1 })[0];
    assert.equal(row.trueFovDeg, null); assert.equal(row.comparison, null); assert.equal(row.magnification, 50);
});
test('binocular field requires an explicit stated value; telescope accessories do not modify binoculars', () => {
    const instrument = { id: 'b', name: 'Binoculars', kind: 'binocular', apertureMm: 50, magnification: 10 };
    assert.equal(compareEyepieces({ instrument })[0].trueFovDeg, null);
    const row = compareEyepieces({ instrument, accessory: { kind: 'barlow', factor: 2 }, binocularFieldDeg: 6.5, diameterArcmin: 60 })[0];
    assert.equal(row.magnification, 10); assert.equal(row.exitPupilMm, 5); assert.equal(row.comparison.fits, true);
});
test('unrealistic temporary inputs and malformed comparisons are rejected without coercion', () => {
    for (const value of [null, true, '', '200', 0, -1, NaN, Infinity]) assert.throws(() => temporaryTelescope(value, 1000));
    assert.throws(() => temporaryEyepiece(20, 181, null)); assert.throws(() => temporaryEyepiece(20, 60, -1));
    for (const diameterArcmin of [true, '1', 0, -1, Infinity, 10801]) assert.throws(() => compareEyepieces({ instrument: scope, diameterArcmin }));
    assert.throws(() => compareEyepieces({ instrument: scope, order: 'best' }));
    assert.throws(() => compareEyepieces({ instrument: scope, eyepieces: [eye('a', 20), eye('a', 10)] }));
});
test('source context retains negative magnitudes, zero and null but never interprets brightness as visibility', () => {
    const input = targets(); input[0].catalogue.appearance.magnitude = -1;
    const clean = validateSuggestionTargets(input);
    assert.equal(clean[0].catalogue.appearance.magnitude, -1);
    assert.equal(catalogueDiameter(clean[0]), 177.83);
    input[0].catalogue.appearance.magnitude = 0; input[0].catalogue.appearance.major_axis_arcmin = 0;
    assert.equal(validateSuggestionTargets(input)[0].catalogue.appearance.magnitude, 0);
    assert.equal(catalogueDiameter(validateSuggestionTargets(input)[0]), null);
    input[0].catalogue.appearance.magnitude = null;
    assert.equal(validateSuggestionTargets(input)[0].catalogue.appearance.magnitude, null);
});
test('stars, doubles, dynamic targets and older backend metadata have no invented extent', () => {
    const input = targets(); input[0].catalogue.appearance.families = ['double_star']; input[0].catalogue.appearance.separation_arcsec = 100;
    assert.equal(catalogueDiameter(validateSuggestionTargets(input)[0]), null);
    assert.equal(catalogueDiameter(validateSuggestionTargets([{ id: 'moon', name: 'Moon' }])[0]), null);
    delete input[0].catalogue.appearance;
    assert.equal(validateSuggestionTargets(input)[0].catalogue, null);
});
test('malformed source fields fail, valid optional nulls survive and unknown properties are stripped', () => {
    for (const [field, value] of [['magnitude', true], ['major_axis_arcmin', '1'], ['minor_axis_arcmin', -1], ['families', ['deep_sky', 'deep_sky']], ['magnitude_flag', []]]) {
        const input = targets(); input[0].catalogue.appearance[field] = value;
        assert.throws(() => validateSuggestionTargets(input));
    }
    for (const url of ['javascript:alert(1)', 'http://example.test', 'https://secret@example.test']) {
        const input = targets(); input[0].catalogue.source_url = url; assert.throws(() => validateSuggestionTargets(input));
    }
    const input = targets(); input[0].catalogue.secret = 'hidden'; input[0].catalogue.appearance.secret = 'hidden';
    assert.equal(JSON.stringify(validateSuggestionTargets(input)).includes('hidden'), false);
    const clean = validateSuggestionTargets(input); clean[0].catalogue.appearance.families.push('double_star');
    assert.deepEqual(input[0].catalogue.appearance.families, ['deep_sky']);
});

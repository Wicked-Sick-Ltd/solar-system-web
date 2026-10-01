import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { WORKSPACE_KEY, LOCATION_KEY, MAX_BYTES, LEGACY_MAX_BYTES, emptyWorkspace, validateWorkspace, parseWorkspace, loadWorkspace, saveWorkspace, putEntry, removeEntry, activateSite, activeLocationMatches } from '../../resources/js/observing/workspace-store.js';
import { workspaceController, equipmentSummary } from '../../resources/js/observing/workspace-ui.js';

const legacyFixture = () => JSON.parse(readFileSync(new URL('../fixtures/observing/workspace-v1.json', import.meta.url), 'utf8'));
const fixture = () => validateWorkspace(legacyFixture());
test('documented v2 camera and horizon example is a portable import and export', () => {
    const raw = readFileSync(new URL('../fixtures/observing/workspace-v2.json', import.meta.url), 'utf8');
    const workspace = parseWorkspace(raw);
    assert.equal(workspace.schemaVersion, 2);
    assert.equal(workspace.equipment.find(item => item.kind === 'camera').sensorWidthMm, 36);
    assert.equal(workspace.sites[0].horizonMask.length, 4);
    assert.deepEqual(parseWorkspace(JSON.stringify(workspace)), workspace);
});
function memory() {
    const data = new Map();
    return { getItem: key => data.get(key) ?? null, setItem: (key, value) => data.set(key, value), removeItem: key => data.delete(key) };
}
test('new guest workspace is empty and all five equipment types round-trip without network or accounts', () => {
    const storage = memory();
    assert.deepEqual(loadWorkspace(storage), emptyWorkspace());
    saveWorkspace(storage, fixture());
    assert.deepEqual(loadWorkspace(storage), fixture());
    assert.equal(storage.getItem(LOCATION_KEY), null);
});
test('site coordinates validate before rounding and preserve zero and southern/western values', () => {
    const value = fixture();
    Object.assign(value.sites[0], { latitude: -51.5074, longitude: 0, minAltitudeDeg: 0 });
    const site = validateWorkspace(value).sites[0];
    assert.equal(site.latitude, -51.51);
    assert.equal(site.longitude, 0);
    assert.equal(site.minAltitudeDeg, 0);
    value.sites[0].latitude = 90.001;
    assert.throws(() => validateWorkspace(value), /Latitude/);
});
for (const [label, change] of [
    ['unknown version', value => value.schemaVersion = 99],
    ['missing version', value => delete value.schemaVersion],
    ['unknown root field', value => value.accountId = 42],
    ['unknown equipment field', value => value.equipment[0].owner = 'private'],
    ['unknown site field', value => value.sites[0].horizon = []],
    ['string number', value => value.equipment[0].apertureMm = '200'],
    ['zero aperture', value => value.equipment[0].apertureMm = 0],
    ['infinite focal length', value => value.equipment[0].focalLengthMm = Infinity],
    ['negative magnification', value => value.equipment[2].magnification = -1],
    ['barlow below unity', value => value.equipment[3].factor = 0.8],
    ['reducer above unity', value => value.equipment[4].factor = 2],
    ['missing unknown field value', value => delete value.equipment[1].fieldStopMm],
    ['empty unknown field', value => value.equipment[1].fieldStopMm = ''],
    ['too wide eyepiece', value => value.equipment[1].apparentFovDeg = 181],
    ['unknown kind', value => value.equipment[0].kind = 'camera'],
    ['prototype kind', value => value.equipment[0].kind = '__proto__'],
    ['non UUID', value => value.equipment[0].id = '../../users/1'],
    ['duplicate equipment', value => value.equipment.push(value.equipment[0])],
    ['duplicate cross collection', value => value.sites[0].id = value.equipment[0].id],
    ['dangling active site', value => value.activeSiteId = value.equipment[0].id],
    ['invalid timezone', value => value.sites[0].timezone = 'Moon/Tranquility'],
    ['offset timezone', value => value.sites[0].timezone = '+01:00'],
    ['boolean coordinate', value => value.sites[0].latitude = false],
    ['null coordinate', value => value.sites[0].longitude = null],
    ['altitude above zenith', value => value.sites[0].minAltitudeDeg = 91],
    ['empty name', value => value.sites[0].name = '   '],
    ['long name', value => value.sites[0].name = 'x'.repeat(101)],
    ['control characters in name', value => value.sites[0].name = '\u0001'.repeat(81)],
    ['too many entries', value => value.equipment = Array(101).fill(value.equipment[0])],
]) test(`refuses ${label} without changing saved workspace`, () => {
    const storage = memory();
    saveWorkspace(storage, fixture());
    const before = storage.getItem(WORKSPACE_KEY);
    const malformed = fixture();
    change(malformed);
    assert.throws(() => saveWorkspace(storage, malformed));
    assert.equal(storage.getItem(WORKSPACE_KEY), before);
});
test('malformed and oversized JSON never produce a partial import', () => {
    for (const value of ['null', '[]', '{broken', '{}', ' '.repeat(MAX_BYTES + 1), JSON.stringify({ ...fixture(), schemaVersion: '1' })]) assert.throws(() => parseWorkspace(value));
});
test('equipment update replaces stable identity and site deletion clears its reference only', () => {
    const value = fixture();
    const next = putEntry(value, 'equipment', { ...value.equipment[0], name: 'Updated scope' });
    assert.equal(next.equipment.length, 5);
    assert.equal(next.equipment[0].name, 'Updated scope');
    assert.equal(value.equipment[0].name, 'Backyard telescope');
    next.activeSiteId = next.sites[0].id;
    const removed = removeEntry(next, 'sites', next.activeSiteId);
    assert.equal(removed.activeSiteId, null);
    assert.deepEqual(removed.sites, []);
    assert.equal(removed.equipment.length, 5);
});
test('only explicit activation updates observer compatibility and reload keeps the association', () => {
    const storage = memory();
    const controller = workspaceController(storage);
    controller.load();
    controller.replace(fixture());
    assert.equal(storage.getItem(LOCATION_KEY), null);
    const site = controller.value.sites[0];
    controller.activate(site.id);
    assert.deepEqual(JSON.parse(storage.getItem(LOCATION_KEY)), { lat: 51.51, lon: -0.13 });
    assert.equal(activeLocationMatches(storage, loadWorkspace(storage)), true);
    controller.save('sites', { ...site, latitude: 52 });
    assert.equal(controller.isActive(site.id), false);
    assert.equal(JSON.parse(storage.getItem(LOCATION_KEY)).lat, 51.51);
    controller.activate(site.id);
    assert.equal(JSON.parse(storage.getItem(LOCATION_KEY)).lat, 52);
    controller.remove('sites', site.id);
    assert.equal(controller.value.activeSiteId, null);
    assert.equal(JSON.parse(storage.getItem(LOCATION_KEY)).lat, 52);
});
test('failed activation restores old document and independent observer selection', () => {
    const storage = memory();
    saveWorkspace(storage, fixture());
    storage.setItem(LOCATION_KEY, JSON.stringify({ lat: 0, lon: 0 }));
    const before = storage.getItem(WORKSPACE_KEY);
    const originalSet = storage.setItem;
    let failure = true;
    storage.setItem = (key, value) => {
        if (key === WORKSPACE_KEY && failure) { failure = false; throw new Error('Quota exceeded'); }
        originalSet(key, value);
    };
    assert.throws(() => activateSite(storage, fixture(), fixture().sites[0].id), /Quota/);
    assert.equal(storage.getItem(WORKSPACE_KEY), before);
    assert.deepEqual(JSON.parse(storage.getItem(LOCATION_KEY)), { lat: 0, lon: 0 });
});
test('blocked storage and quota errors surface without reporting saved state', () => {
    const blocked = { getItem() { throw new Error('Blocked'); } };
    assert.throws(() => workspaceController(blocked).load(), /Blocked/);
    const storage = memory();
    const controller = workspaceController(storage);
    controller.load();
    storage.setItem = () => { throw new Error('Quota'); };
    assert.throws(() => controller.save('equipment', fixture().equipment[0]), /Quota/);
    assert.deepEqual(controller.value, emptyWorkspace());
});
test('unknown future documents remain untouched until explicit recovery reset', () => {
    const storage = memory();
    const future = JSON.stringify({ ...fixture(), schemaVersion: 12 });
    storage.setItem(WORKSPACE_KEY, future);
    const controller = workspaceController(storage);
    assert.throws(() => controller.load(), /version/);
    assert.throws(() => controller.replace(fixture()), /unavailable/);
    assert.equal(controller.rawBackup(), future);
    controller.reset();
    controller.replace(fixture());
    assert.deepEqual(controller.value, fixture());
});
test('import is preview-only, exports are round-trip, replacement never activates imported site', () => {
    const storage = memory();
    storage.setItem(LOCATION_KEY, '{"lat":1,"lon":2}');
    const controller = workspaceController(storage);
    controller.load();
    const data = fixture();
    data.activeSiteId = data.sites[0].id;
    const preview = controller.import(JSON.stringify(data));
    assert.deepEqual(controller.value, emptyWorkspace());
    assert.equal(storage.getItem(WORKSPACE_KEY), null);
    controller.replace(preview);
    assert.equal(controller.value.activeSiteId, null);
    assert.equal(storage.getItem(LOCATION_KEY), '{"lat":1,"lon":2}');
    assert.deepEqual(parseWorkspace(controller.export()), fixture());
});
test('two tabs refuse stale replacement, edits, deletion and activation instead of losing updates', () => {
    const storage = memory();
    const first = workspaceController(storage), second = workspaceController(storage);
    first.load(); second.load();
    first.replace(fixture());
    for (const action of [() => second.replace(fixture()), () => second.save('equipment', fixture().equipment[0]), () => second.remove('sites', fixture().sites[0].id), () => second.activate(fixture().sites[0].id)]) assert.throws(action, /another tab/);
    second.load();
    assert.deepEqual(second.value, first.value);
});
test('unknown optical values remain visibly unknown instead of becoming zero', () => {
    const eyepiece = { ...fixture().equipment[1], apparentFovDeg: null };
    assert.match(equipmentSummary(eyepiece), /apparent field unknown/);
    assert.match(equipmentSummary(eyepiece), /field stop unknown/);
});

test('maximum-entry export stays within the import byte limit even when JSON expands names', () => {
    const data = emptyWorkspace();
    // JSON escapes lone Unicode surrogates to six ASCII bytes each.
    // They remain inert text; the byte limit must apply to that actual encoding.
    const longName = '\ud800'.repeat(81);
    for (let i = 1; i <= 100; i++) {
        data.equipment.push({ ...fixture().equipment[0], id: `00000000-0000-4000-8000-${String(i).padStart(12, '0')}`, name: longName });
        data.sites.push({ ...fixture().sites[0], id: `10000000-0000-4000-8000-${String(i).padStart(12, '0')}`, name: longName });
    }
    const controller = workspaceController(memory());
    controller.load();
    controller.replace(data);
    const exported = controller.export();
    assert.ok(new TextEncoder().encode(exported).length <= MAX_BYTES);
    assert.deepEqual(parseWorkspace(exported), data);
});

test('site timezones use portable canonical casing', () => {
    const data = fixture();
    data.sites[0].timezone = 'europe/london';
    assert.equal(validateWorkspace(data).sites[0].timezone, 'Europe/London');
    data.sites[0].timezone = 'utc';
    assert.equal(validateWorkspace(data).sites[0].timezone, 'UTC');
});

test('legacy v1 loads into v2 without writing, preserves IDs, and exports explicit v2 data', () => {
    const storage = memory();
    const original = JSON.stringify(legacyFixture());
    storage.setItem(WORKSPACE_KEY, original);
    const controller = workspaceController(storage);
    controller.load();
    assert.equal(controller.value.schemaVersion, 2);
    assert.equal(controller.value.sites[0].horizonMask, null);
    assert.equal(controller.value.sites[0].id, legacyFixture().sites[0].id);
    assert.equal(JSON.parse(controller.export()).schemaVersion, 2);
    assert.equal(storage.getItem(WORKSPACE_KEY), original);
    controller.save('equipment', { ...controller.value.equipment[0], name: 'Edited telescope' });
    assert.equal(JSON.parse(storage.getItem(WORKSPACE_KEY)).schemaVersion, 2);
});
test('near-old-limit v1 data remains importable after the additional v2 site fields', () => {
    const data = { schemaVersion: 1, equipment: [], sites: [], activeSiteId: null };
    for (let i = 1; i <= 100; i++) {
        data.equipment.push({ ...legacyFixture().equipment[0], id: `00000000-0000-4000-8000-${String(i).padStart(12, '0')}`, name: '\ud800'.repeat(100) });
        data.sites.push({ ...legacyFixture().sites[0], id: `10000000-0000-4000-8000-${String(i).padStart(12, '0')}`, name: '\ud800'.repeat(100) });
    }
    let index = 0;
    while (new TextEncoder().encode(JSON.stringify(data)).length > LEGACY_MAX_BYTES) {
        const entry = [...data.equipment, ...data.sites][index++ % 200];
        entry.name = entry.name.slice(1);
    }
    const upgraded = parseWorkspace(JSON.stringify(data));
    const bytes = new TextEncoder().encode(JSON.stringify(upgraded)).length;
    assert.ok(bytes > LEGACY_MAX_BYTES && bytes <= MAX_BYTES);
    assert.deepEqual(parseWorkspace(JSON.stringify(upgraded)), upgraded);
    assert.throws(() => parseWorkspace(JSON.stringify(data) + ' '.repeat(10)), /128 KiB/);
});
test('v2 camera and user-entered horizon data round-trip with strict dimensions and provenance', () => {
    const data = fixture();
    data.equipment.push({ id: '00000000-0000-4000-8000-000000000099', name: 'User sensor', kind: 'camera', sensorWidthMm: 36, sensorHeightMm: 24, pixelSizeUm: null });
    data.sites[0].horizonMask = [{ azimuthDeg: 180, minAltitudeDeg: 20 }, { azimuthDeg: 360, minAltitudeDeg: -5 }];
    const clean = validateWorkspace(data);
    assert.equal(clean.equipment.at(-1).pixelSizeUm, null);
    assert.deepEqual(clean.sites[0].horizonMask, [{ azimuthDeg: 0, minAltitudeDeg: -5 }, { azimuthDeg: 180, minAltitudeDeg: 20 }]);
    assert.deepEqual(parseWorkspace(JSON.stringify(clean)), clean);
    for (const [field, value] of [['sensorWidthMm', 0], ['sensorHeightMm', -1], ['pixelSizeUm', '5'], ['sensorWidthMm', Infinity], ['pixelSizeUm', false]]) {
        const malformed = structuredClone(clean); malformed.equipment.at(-1)[field] = value;
        assert.throws(() => validateWorkspace(malformed));
    }
    const oversizePixel = structuredClone(clean);
    Object.assign(oversizePixel.equipment.at(-1), { sensorWidthMm: 0.01, pixelSizeUm: 100 });
    assert.throws(() => validateWorkspace(oversizePixel), /Pixel size/);
    const misleading = structuredClone(clean); misleading.sites[0].horizonMask[0].source = 'surveyed by NASA';
    assert.throws(() => validateWorkspace(misleading));
    const old = legacyFixture(); old.equipment.push(clean.equipment.at(-1));
    assert.throws(() => validateWorkspace(old), /equipment type/);
});
test('raw snapshot conflicts remain detectable after a legacy document is normalized in memory', () => {
    const storage = memory(); storage.setItem(WORKSPACE_KEY, JSON.stringify(legacyFixture()));
    const controller = workspaceController(storage); controller.load();
    storage.setItem(WORKSPACE_KEY, JSON.stringify(fixture()));
    assert.throws(() => controller.save('equipment', controller.value.equipment[0]), /another tab/);
});

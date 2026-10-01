import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { WORKSPACE_KEY, LOCATION_KEY, MAX_BYTES, emptyWorkspace, validateWorkspace, parseWorkspace, loadWorkspace, saveWorkspace, putEntry, removeEntry, activateSite, activeLocationMatches } from '../../resources/js/observing/workspace-store.js';
import { workspaceController, equipmentSummary } from '../../resources/js/observing/workspace-ui.js';

const fixture = () => JSON.parse(readFileSync(new URL('../fixtures/observing/workspace-v1.json', import.meta.url), 'utf8'));
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
    ['unknown version', value => value.schemaVersion = 2],
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

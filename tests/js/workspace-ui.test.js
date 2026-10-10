import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { mountWorkspace, what3wordsAddress } from '../../resources/js/observing/workspace-ui.js';
import { WORKSPACE_KEY } from '../../resources/js/observing/workspace-store.js';

class Element {
    constructor() { this.events = new Map(); this.children = []; this.dataset = {}; this.style = {}; this.textContent = ''; this.disabled = true; this.hidden = false; this.value = ''; }
    get value() { return this._value; }
    set value(value) { this._value = String(value ?? ''); }
    addEventListener(type, fn) { const fns = this.events.get(type) || []; fns.push(fn); this.events.set(type, fns); }
    removeEventListener(type, fn) { this.events.set(type, (this.events.get(type) || []).filter(item => item !== fn)); }
    emit(type, event = {}) { for (const fn of [...this.events.get(type) || []]) fn({ target: this, ...event }); }
    append(...items) { this.children.push(...items); }
    replaceChildren() { this.children = []; }
    focus() { this.focused = true; }
    click() { this.emit('click'); }
    closest() { return this.dataset.workspaceAction ? this : null; }
}
function harness(saved = null) {
    const data = new Map(saved ? [[WORKSPACE_KEY, saved]] : []);
    const storage = { getItem: key => data.get(key) ?? null, setItem: (key, value) => data.set(key, value), removeItem: key => data.delete(key) };
    const root = new Element();
    const view = new Element();
    view.localStorage = storage;
    view.URL = { createObjectURL: () => 'blob:test', revokeObjectURL() {} };
    view.CustomEvent = class { constructor(type) { this.type = type; } };
    view.dispatchEvent = event => view.emit(event.type, event);
    const doc = { defaultView: view, createElement: () => new Element() };
    root.ownerDocument = doc;
    const names = ['equipment-form', 'site-form', 'equipment-list', 'sites-list', 'equipment-empty', 'sites-empty', 'status', 'error', 'preview', 'preview-summary', 'preview-details', 'import'];
    const elements = Object.fromEntries(names.map(name => [name, new Element()]));
    for (const name of ['equipment-form', 'site-form']) {
        const form = elements[name];
        const keys = ['entryId', 'name', 'kind', 'apertureMm', 'focalLengthMm', 'magnification', 'apparentFovDeg', 'fieldStopMm', 'factor', 'latitude', 'longitude', 'timezone', 'minAltitudeDeg', 'horizonMask', 'sensorWidthMm', 'sensorHeightMm', 'pixelSizeUm'];
        const fields = Object.fromEntries(keys.map(key => [key, new Element()]));
        const submit = new Element();
        form.elements = { namedItem: key => fields[key] };
        form.querySelector = () => submit;
        form.querySelectorAll = () => [];
        form.reset = () => { Object.values(fields).forEach(field => field.value = ''); fields.kind.value = 'telescope'; fields.timezone.value = 'UTC'; fields.minAltitudeDeg.value = '20'; };
        form.reset();
    }
    const enabled = [new Element(), new Element(), new Element()];
    root.querySelector = selector => elements[selector.slice('[data-workspace-'.length, -1)];
    root.querySelectorAll = () => enabled;
    root.contains = () => true;
    function action(action, extra = {}) {
        const control = new Element();
        control.dataset = { workspaceAction: action, ...extra };
        root.emit('click', { target: control });
    }
    return { root, view, elements, enabled, storage, action, mount: () => mountWorkspace(root, { uuid: () => '00000000-0000-4000-8000-000000000009', confirm: () => true }) };
}
const fixtureText = readFileSync(new URL('../fixtures/observing/workspace-v1.json', import.meta.url), 'utf8');

test('mount enables native controls only after installing submit protection; save prevents navigation', () => {
    const h = harness();
    h.mount();
    assert.ok(h.enabled.every(control => !control.disabled));
    const form = h.elements['equipment-form'];
    for (const [key, value] of Object.entries({ name: '<img src=x onerror=alert(1)>', kind: 'telescope', apertureMm: '200', focalLengthMm: '1000' })) form.elements.namedItem(key).value = value;
    let prevented = false;
    h.root.emit('submit', { target: form, preventDefault() { prevented = true; } });
    assert.ok(prevented);
    const saved = JSON.parse(h.storage.getItem(WORKSPACE_KEY));
    assert.equal(saved.equipment[0].name, '<img src=x onerror=alert(1)>');
    assert.equal(h.elements['equipment-list'].children[0].children[0].textContent, saved.equipment[0].name);
    assert.match(h.elements.status.textContent, /Saved in this browser/);
});
test('unreadable storage displays an error and ordinary export cannot pretend the workspace was empty', () => {
    const h = harness('{bad');
    h.mount();
    assert.match(h.elements.error.textContent, /JSON/);
    h.action('export');
    assert.match(h.elements.error.textContent, /original stored data/);
    assert.equal(h.storage.getItem(WORKSPACE_KEY), '{bad');
});
test('file preview never writes storage and late file reads are inert after teardown', async () => {
    const h = harness();
    const mounted = h.mount();
    let resolve;
    h.elements.import.emit('change', { target: { files: [{ size: fixtureText.length, text: () => new Promise(done => resolve = done) }] } });
    mounted.dispose();
    resolve(fixtureText);
    await Promise.resolve();
    assert.equal(h.storage.getItem(WORKSPACE_KEY), null);
    assert.equal(h.elements.preview.hidden, true);
    assert.ok(h.enabled.every(control => control.disabled));
    assert.equal(h.root.events.get('submit').length, 0);
    assert.equal(h.view.events.get('storage').length, 0);
});
test('reload cancels an in-flight import; a new import requires an explicit replace action', async () => {
    const h = harness();
    h.mount();
    let resolve;
    h.elements.import.emit('change', { target: { files: [{ size: fixtureText.length, text: () => new Promise(done => resolve = done) }] } });
    h.action('reload');
    resolve(fixtureText);
    await Promise.resolve();
    assert.equal(h.elements.preview.hidden, true);
    h.elements.import.emit('change', { target: { files: [{ size: fixtureText.length, text: async () => fixtureText }] } });
    await Promise.resolve();
    assert.equal(h.elements.preview.hidden, false);
    assert.equal(h.storage.getItem(WORKSPACE_KEY), null);
    h.action('apply-import');
    assert.equal(JSON.parse(h.storage.getItem(WORKSPACE_KEY)).equipment.length, 5);
    assert.equal(h.elements.preview.hidden, true);
});
test('entry mounts once per root and cleans up on navigation and BFCache transitions', () => {
    const source = readFileSync(new URL('../../resources/js/observing/workspace.js', import.meta.url), 'utf8').replace(/^import .*;\n/, '');
    const document = new Element(), window = new Element();
    document.readyState = 'complete';
    let root = {};
    document.querySelector = () => root;
    let mounts = 0, disposals = 0;
    vm.runInNewContext(source, { document, window, mountWorkspace: () => { mounts++; return { dispose: () => disposals++ }; } });
    assert.equal(mounts, 1);
    document.emit('livewire:navigated');
    assert.equal(mounts, 1);
    document.emit('livewire:navigating');
    root = null;
    document.emit('livewire:navigated');
    assert.equal(disposals, 1);
    root = {};
    document.emit('livewire:navigated');
    assert.equal(mounts, 2);
    window.emit('pagehide');
    window.emit('pageshow', { persisted: true });
    assert.equal(mounts, 3);
    assert.equal(disposals, 2);
});

test('saved user horizon points have editable text, explicit provenance and no immediate observer side effect', () => {
    const h = harness(); h.mount();
    const form = h.elements['site-form'];
    for (const [key, value] of Object.entries({ name: 'My site', latitude: '51.5', longitude: '-0.1', timezone: 'UTC', minAltitudeDeg: '20', horizonMask: '360, -5\n180, 30' })) form.elements.namedItem(key).value = value;
    h.root.emit('submit', { target: form, preventDefault() {} });
    const saved = JSON.parse(h.storage.getItem(WORKSPACE_KEY));
    assert.equal(saved.schemaVersion, 2);
    assert.deepEqual(saved.sites[0].horizonMask, [{ azimuthDeg: 0, minAltitudeDeg: -5 }, { azimuthDeg: 180, minAltitudeDeg: 30 }]);
    assert.equal(h.storage.getItem('observer_location'), null);
    assert.match(h.elements['sites-list'].children[0].children[1].textContent, /user-entered horizon points/);
    h.action('edit', { collection: 'sites', entryId: saved.sites[0].id });
    assert.equal(form.elements.namedItem('horizonMask').value, '0, -5\n180, 30');
});

test('camera form saves unknown pixels and edits the same profile without collecting unrelated optical fields', () => {
    const h = harness(); h.mount();
    const form = h.elements['equipment-form'];
    for (const [key, value] of Object.entries({ name: 'My camera', kind: 'camera', sensorWidthMm: '36', sensorHeightMm: '24', pixelSizeUm: '' })) form.elements.namedItem(key).value = value;
    h.root.emit('submit', { target: form, preventDefault() {} });
    const saved = JSON.parse(h.storage.getItem(WORKSPACE_KEY));
    const camera = saved.equipment[0];
    assert.equal(camera.pixelSizeUm, null);
    assert.equal(camera.sensorWidthMm, 36);
    assert.equal(Object.hasOwn(camera, 'focalLengthMm'), false);
    assert.match(h.elements['equipment-list'].children[0].children[1].textContent, /pixel size unknown/);
    h.action('edit', { collection: 'equipment', entryId: camera.id });
    assert.equal(form.elements.namedItem('pixelSizeUm').value, '');
    form.elements.namedItem('pixelSizeUm').value = '5';
    h.root.emit('submit', { target: form, preventDefault() {} });
    const edited = JSON.parse(h.storage.getItem(WORKSPACE_KEY)).equipment;
    assert.equal(edited.length, 1);
    assert.equal(edited[0].id, camera.id);
    assert.equal(edited[0].pixelSizeUm, 5);
});

function attachWhat3words(h) {
    const panel = new Element();
    panel.dataset = { endpoint: '/observatory/what3words', reverseEndpoint: '/observatory/what3words/coordinates', csrf: 'csrf-token' };
    h.elements.what3words = panel;
    h.elements['what3words-input'] = new Element();
    h.elements['what3words-result'] = new Element();
    h.elements['what3words-error'] = new Element();
    h.elements['what3words-locate'] = new Element();
}
async function flush() {
    for (let step = 0; step < 6; step++) await Promise.resolve();
}

test('what3words addresses are three words, with or without the slashes', () => {
    assert.equal(what3wordsAddress('  ///Filled.Count.Soap '), 'filled.count.soap');
    assert.equal(what3wordsAddress('https://what3words.com/filled.count.soap'), 'filled.count.soap');
    assert.equal(what3wordsAddress('www.google.com'), null);
    assert.equal(what3wordsAddress('not a location'), null);
    assert.equal(what3wordsAddress('///filled.count'), null);
});

test('locating a what3words address fills rounded coordinates and confirms the nearest place', async () => {
    const h = harness();
    attachWhat3words(h);
    const calls = [];
    mountWorkspace(h.root, {
        uuid: () => '00000000-0000-4000-8000-000000000009',
        confirm: () => true,
        fetch: async (url, options) => {
            calls.push({ url, options });
            return { ok: true, json: async () => ({ words: 'filled.count.soap', latitude: 51.520847, longitude: -0.195521, roundedLatitude: 51.52, roundedLongitude: -0.2, nearestPlace: 'Bayswater, London' }) };
        },
    });
    const input = h.elements['what3words-input'];
    input.value = '///Filled.Count.Soap';
    let prevented = false;
    input.emit('keydown', { key: 'Enter', preventDefault() { prevented = true; } });
    await flush();
    assert.equal(prevented, true);
    assert.equal(calls.length, 1);
    assert.equal(calls[0].url, '/observatory/what3words');
    assert.equal(JSON.parse(calls[0].options.body).words, 'filled.count.soap');
    assert.equal(calls[0].options.headers['X-CSRF-TOKEN'], 'csrf-token');
    assert.equal(calls[0].options.headers['X-Api-Key'], undefined);
    assert.equal(h.elements['site-form'].elements.namedItem('latitude').value, '51.52');
    assert.equal(h.elements['site-form'].elements.namedItem('longitude').value, '-0.2');
    assert.match(h.elements['what3words-result'].textContent, /Bayswater, London/);
    assert.match(h.elements['what3words-result'].textContent, /51\.520847, -0\.195521/);
    assert.equal(h.elements['what3words-error'].textContent, '');
    assert.equal(input.ariaInvalid, 'false');
    assert.equal(h.elements['what3words-locate'].disabled, false);
});

test('an invalid or failed what3words lookup does not change coordinates', async () => {
    const h = harness();
    attachWhat3words(h);
    let calls = 0;
    mountWorkspace(h.root, {
        uuid: () => '00000000-0000-4000-8000-000000000009',
        confirm: () => true,
        fetch: async () => {
            calls++;
            return { ok: false, json: async () => ({ message: 'That what3words address wasn\'t recognised — check the three words.' }) };
        },
    });
    const form = h.elements['site-form'];
    form.elements.namedItem('latitude').value = '1';
    form.elements.namedItem('longitude').value = '2';
    h.elements['what3words-input'].value = 'hello';
    h.action('locate-site');
    assert.equal(calls, 0);
    assert.match(h.elements['what3words-error'].textContent, /three words/);
    assert.equal(h.elements['what3words-input'].ariaInvalid, 'true');
    assert.equal(form.elements.namedItem('latitude').value, '1');

    h.elements['what3words-input'].value = '///not.real.words';
    h.action('locate-site');
    await flush();
    assert.equal(calls, 1);
    assert.match(h.elements['what3words-error'].textContent, /recognised/);
    assert.equal(form.elements.namedItem('latitude').value, '1');
    assert.equal(form.elements.namedItem('longitude').value, '2');
});

test('saved sites show an approximate what3words address when the lookup succeeds', async () => {
    const saved = JSON.stringify({
        schemaVersion: 2, equipment: [], activeSiteId: null,
        sites: [{ id: '00000000-0000-4000-8000-000000000006', name: 'Garden', latitude: 51.52, longitude: -0.2, timezone: 'UTC', minAltitudeDeg: 20, horizonMask: null }],
    });
    const h = harness(saved);
    attachWhat3words(h);
    mountWorkspace(h.root, {
        uuid: () => '00000000-0000-4000-8000-000000000009',
        confirm: () => true,
        fetch: async () => ({ ok: true, json: async () => ({ results: [{ latitude: 51.52, longitude: -0.2, words: 'index.home.raft', nearestPlace: 'Bayswater, London' }] }) }),
    });
    await flush();
    assert.match(h.elements['sites-list'].children[0].children[1].textContent, /approximate what3words \/\/\/index\.home\.raft/);
    assert.match(h.elements['sites-list'].children[0].children[1].textContent, /near Bayswater, London/);
});

test('a failed what3words decoration leaves the saved site usable', async () => {
    const saved = JSON.stringify({
        schemaVersion: 2, equipment: [], activeSiteId: null,
        sites: [{ id: '00000000-0000-4000-8000-000000000006', name: 'Garden', latitude: 51.52, longitude: -0.2, timezone: 'UTC', minAltitudeDeg: 20, horizonMask: null }],
    });
    const h = harness(saved);
    attachWhat3words(h);
    mountWorkspace(h.root, {
        uuid: () => '00000000-0000-4000-8000-000000000009',
        confirm: () => true,
        fetch: async () => { throw new Error('offline'); },
    });
    await flush();
    const details = h.elements['sites-list'].children[0].children[1].textContent;
    assert.match(details, /51\.52, -0\.20/);
    assert.doesNotMatch(details, /what3words/);
});

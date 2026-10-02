import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { mountWorkspace } from '../../resources/js/observing/workspace-ui.js';
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

import test from 'node:test';
import assert from 'node:assert/strict';
import { mountEquipmentSuggestions } from '../../resources/js/observing/equipment-suggestions-ui.js';
import { WORKSPACE_KEY, emptyWorkspace } from '../../resources/js/observing/workspace-store.js';
class Element {
    constructor() { this.value = ''; this.textContent = ''; this.children = []; this.events = new Map(); this.checked = false; }
    append(child) { this.children.push(child); }
    replaceChildren() { this.children = []; }
    addEventListener(type, fn) { this.events.set(type, fn); }
    removeEventListener(type) { this.events.delete(type); }
    emit(type, event = {}) { this.events.get(type)?.(event); }
}
const id = n => `00000000-0000-4000-8000-${String(n).padStart(12, '0')}`;
const text = node => [node.textContent, ...node.children.map(text)].join(' ');
function harness({ denied = false, targets = [] } = {}) {
    const root = new Element(); root.ownerDocument = { createElement: () => new Element(), createTextNode: value => Object.assign(new Element(), { textContent: value }) };
    const keys = ['controls', 'metadata-error', 'storage-error', 'instrument', 'accessory', 'mode', 'target', 'context', 'error', 'summary', 'results', 'order', 'temporary-inputs', 'aperture', 'focal', 'binocular-input', 'binocular-field', 'add-eyepiece', 'eyepiece-inputs', 'eye-focal', 'eye-afov', 'eye-stop', 'size-mode', 'size-inputs', 'diameter', 'size-source', 'reload'];
    const elements = Object.fromEntries(keys.map(key => [key, new Element()]));
    elements.mode.value = 'saved'; elements.order.value = 'lowest-power'; elements['size-mode'].value = 'catalogue';
    root.querySelector = selector => elements[selector.slice('[data-suggestions-'.length, -1)];
    let rows = [
        { id: id(1), name: 'Scope', kind: 'telescope', apertureMm: 200, focalLengthMm: 1000 },
        { id: id(2), name: '<img src=x onerror=alert(1)>', kind: 'eyepiece', focalLengthMm: 20, apparentFovDeg: 60, fieldStopMm: null },
        { id: id(3), name: 'Binoculars', kind: 'binocular', apertureMm: 50, magnification: 10 },
    ];
    let reads = 0, writes = 0;
    const storage = { getItem(key) { reads++; assert.equal(key, WORKSPACE_KEY); if (denied) throw new Error('private storage details'); return JSON.stringify({ ...emptyWorkspace(), equipment: rows }); }, setItem() { writes++; throw new Error('must not save'); } };
    const mounted = mountEquipmentSuggestions(root, { targets, storage });
    return { root, elements, mounted, select(key, value) { elements[key].value = value; root.emit('change'); }, input(key, value) { elements[key].value = value; root.emit('input'); }, check(key, value = true) { elements[key].checked = value; root.emit('change'); }, setRows(value) { rows = value; }, get rows() { return rows; }, get reads() { return reads; }, get writes() { return writes; } };
}
test('saved eyepiece output uses text nodes, does not save or read coordinates, and remains unknown without a size', () => {
    const h = harness(); h.select('instrument', id(1));
    assert.match(text(h.elements.results), /Magnification 50×; exit pupil 4 mm; true field 1.2°/);
    assert.match(text(h.elements.results), /Angular containment unknown/);
    assert.equal(h.elements.results.children[0].children[0].textContent, '<img src=x onerror=alert(1)>');
    assert.equal(h.writes, 0); assert.equal(h.reads, 1);
    assert.equal(h.elements.controls.disabled, false);
});
test('temporary telescope and eyepiece work when storage is unavailable, with validation and no persistence', () => {
    const h = harness({ denied: true });
    assert.match(h.elements['storage-error'].textContent, /could not be read/);
    assert.doesNotMatch(h.elements['storage-error'].textContent, /private storage/);
    h.select('mode', 'temporary'); h.input('aperture', '200'); h.input('focal', '1000');
    h.check('add-eyepiece'); h.input('eye-focal', '20'); h.input('eye-afov', '60');
    assert.match(text(h.elements.results), /50×/);
    h.input('focal', '0'); assert.match(h.elements.error.textContent, /positive/); assert.equal(h.elements.results.children.length, 0);
    h.input('focal', '2000'); assert.match(text(h.elements.results), /100×/);
    assert.equal(h.writes, 0);
});
test('same-ID instrument replacement clears stated binocular field and never carries stale containment', () => {
    const h = harness(); h.select('instrument', id(3)); h.input('binocular-field', '6.5'); h.select('size-mode', 'manual'); h.input('diameter', '60');
    assert.match(text(h.elements.results), /within this estimated circular field/);
    h.setRows([{ ...h.rows[2], apertureMm: 70, magnification: 15 }]); h.mounted.refresh();
    assert.equal(h.elements['binocular-field'].value, ''); assert.match(text(h.elements.results), /Angular containment unknown/);
    h.setRows([]); h.mounted.refresh(); assert.equal(h.elements.instrument.value, ''); assert.equal(h.elements.results.children.length, 0);
});
test('sourced extended size carries bands, quality flags, attribution and snapshot without a visibility claim', () => {
    const catalogue = { source: 'openngc', source_url: 'https://example.test/source', license_url: 'https://example.test/license', license: 'CC BY-SA 4.0', attribution: 'Author credit', snapshot_sha256: 'a'.repeat(64), appearance: { families: ['deep_sky'], magnitude: 0, magnitude_band: 'V', magnitude_flag: 'uncertain', magnitude_code: 'combined', major_axis_arcmin: 60, minor_axis_arcmin: null } };
    const h = harness({ targets: [{ id: 'openngc:NGC0224', name: 'M31', catalogue }] });
    h.select('instrument', id(1)); h.select('target', 'openngc:NGC0224');
    assert.match(text(h.elements.context), /Recorded magnitude: 0; band: V; uncertainty flag: uncertain; source code: combined/);
    assert.match(text(h.elements.context), /Author credit/); assert.match(text(h.elements.context), /not point-source visibility limits/);
    assert.match(text(h.elements.results), /83.33%/);
    h.select('size-mode', 'manual'); h.input('diameter', '120'); h.input('size-source', '<script>local source</script>');
    assert.match(text(h.elements.context), /User-entered/); assert.match(text(h.elements.results), /larger than/);
    assert.equal(h.writes, 0);
});
test('malformed metadata degrades only context, while equipment comparison remains usable', () => {
    const h = harness({ targets: [{ id: 'x', name: 'x', catalogue: { appearance: false } }] });
    assert.match(h.elements['metadata-error'].textContent, /unavailable/);
    h.select('instrument', id(1)); assert.match(text(h.elements.results), /50×/);
});
test('dispose removes listeners, disables controls and permits bfcache remount without duplicate options', () => {
    const h = harness(); const options = h.elements.instrument.children.length;
    h.mounted.refresh(); assert.equal(h.elements.instrument.children.length, options);
    h.mounted.dispose(); const before = h.reads; h.select('instrument', id(1)); h.mounted.refresh();
    assert.equal(h.root.events.size, 0); assert.equal(h.elements.controls.disabled, true); assert.equal(h.reads, before);
    assert.doesNotThrow(() => mountEquipmentSuggestions(null).dispose());
});

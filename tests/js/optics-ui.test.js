import test from 'node:test';
import assert from 'node:assert/strict';
import { mountOptics } from '../../resources/js/observing/optics-ui.js';

class Element {
    constructor() { this.value = ''; this.textContent = ''; this.children = []; this.attributes = {}; this.events = new Map(); }
    append(child) { this.children.push(child); }
    replaceChildren() { this.children = []; }
    setAttribute(key, value) { this.attributes[key] = value; }
    addEventListener(type, fn) { this.events.set(type, fn); }
    removeEventListener(type) { this.events.delete(type); }
    emit(type, event = {}) { this.events.get(type)?.(event); }
}
function harness() {
    const root = new Element();
    const doc = { createElement: () => new Element() };
    root.ownerDocument = doc;
    const keys = ['mode', 'camera', 'camera-input', 'pixel', 'camera-diagram', 'sensor-rectangle', 'camera-target', 'camera-description', 'instrument', 'eyepiece', 'accessory', 'binocular-input', 'binocular-field', 'diameter', 'error', 'focal', 'magnification', 'pupil', 'field', 'method', 'diagram', 'comparison', 'field-circle', 'target-circle', 'diagram-description'];
    const elements = Object.fromEntries(keys.map(key => [key, new Element()]));
    root.querySelector = selector => elements[selector.replace('[data-optics-', '').replace(']', '')];
    let rows = [
        { id: 'scope', name: 'Scope', kind: 'telescope', apertureMm: 200, focalLengthMm: 1000 },
        { id: 'eye', name: 'Eyepiece', kind: 'eyepiece', focalLengthMm: 20, apparentFovDeg: 60, fieldStopMm: null },
        { id: 'barlow', name: 'Barlow', kind: 'barlow', factor: 2 },
        { id: 'bino', name: 'Binoculars', kind: 'binocular', apertureMm: 50, magnification: 10 },
        { id: 'bino2', name: 'Second binoculars', kind: 'binocular', apertureMm: 70, magnification: 15 },
    ];
    const mounted = mountOptics(root, () => rows);
    return { root, elements, mounted, setRows(value) { rows = value; }, get rows() { return rows; }, select(key, value) { elements[key].value = value; root.emit('change', { target: elements[key] }); }, input(key, value) { elements[key].value = value; root.emit('input', { target: elements[key] }); } };
}
test('UI starts with unknown results and selection produces accessible geometric equivalents', () => {
    const h = harness();
    assert.equal(h.elements.magnification.textContent, 'Unknown');
    h.select('instrument', 'scope'); h.select('eyepiece', 'eye');
    h.input('diameter', '30');
    assert.equal(h.elements.magnification.textContent, '50×');
    assert.match(h.elements.field.textContent, /1.2° \(72 arcminutes\)/);
    assert.match(h.elements.method.textContent, /Approximation/);
    assert.match(h.elements.comparison.textContent, /41.67%/);
    assert.equal(h.elements['diagram-description'].textContent, h.elements.comparison.textContent);
    assert.equal(h.elements.diagram.hidden, false);
});
test('equipment changes recalculate the same explicitly entered target rather than preserving stale outputs', () => {
    const h = harness();
    h.select('instrument', 'scope'); h.select('eyepiece', 'eye'); h.input('diameter', '30');
    h.select('accessory', 'barlow');
    assert.equal(h.elements.magnification.textContent, '100×');
    assert.match(h.elements.comparison.textContent, /83.33%/);
    assert.equal(h.elements.diameter.value, '30');
    assert.equal(h.rows.some(row => 'diameter' in row || 'trueFovDeg' in row), false);
});
test('binocular field is unknown initially and clears when changing instruments', () => {
    const h = harness();
    h.select('instrument', 'bino');
    assert.equal(h.elements.pupil.textContent, '5 mm');
    assert.match(h.elements.field.textContent, /^Unknown/);
    assert.equal(h.elements.eyepiece.disabled, true);
    h.input('binocular-field', '6.5'); h.input('diameter', '60');
    assert.match(h.elements.field.textContent, /6.5°/);
    h.select('instrument', 'bino2');
    assert.equal(h.elements['binocular-field'].value, '');
    assert.match(h.elements.field.textContent, /^Unknown/);
    assert.equal(h.elements.diagram.hidden, true);
    h.select('instrument', 'scope');
    assert.equal(h.elements.eyepiece.disabled, false);
    assert.equal(h.elements['binocular-input'].hidden, true);
});
test('deleting or importing over selected equipment removes dangling selection and preview', () => {
    const h = harness();
    h.select('instrument', 'scope'); h.select('eyepiece', 'eye'); h.input('diameter', '30');
    h.setRows([]); h.mounted.refresh();
    assert.equal(h.elements.instrument.value, '');
    assert.equal(h.elements.eyepiece.value, '');
    assert.equal(h.elements.magnification.textContent, 'Unknown');
    assert.equal(h.elements.diagram.hidden, true);
});
test('invalid diameter hides stale preview and correcting it restores the current calculation', () => {
    const h = harness();
    h.select('instrument', 'scope'); h.select('eyepiece', 'eye'); h.input('diameter', '30');
    h.input('diameter', '-1');
    assert.equal(h.elements.diagram.hidden, true);
    assert.match(h.elements.error.textContent, /positive number/);
    h.input('diameter', '120');
    assert.equal(h.elements.error.textContent, '');
    assert.match(h.elements.comparison.textContent, /larger than/);
    assert.equal(h.elements.diagram.hidden, false);
});
test('tiny angular targets are not enlarged to a minimum visible size', () => {
    const h = harness();
    h.select('instrument', 'scope'); h.select('eyepiece', 'eye'); h.input('diameter', '0.00001');
    assert.equal(h.elements['target-circle'].attributes.visibility, 'hidden');
    assert.match(h.elements.comparison.textContent, /below the diagram’s display resolution/);
    h.input('diameter', '60');
    assert.equal(h.elements['target-circle'].attributes.visibility, 'visible');
});
test('mount cleanup prevents stale DOM updates and safely supports pages without calculator markup', () => {
    const h = harness();
    h.mounted.dispose();
    h.select('instrument', 'scope'); h.select('eyepiece', 'eye');
    assert.equal(h.elements.magnification.textContent, 'Unknown');
    assert.equal(h.root.events.size, 0);
    assert.doesNotThrow(() => mountOptics(null, () => []).dispose());
});

test('replacement equipment reusing an ID cannot inherit another binoculars stated field', () => {
    const h = harness();
    h.select('instrument', 'bino'); h.input('binocular-field', '6.5'); h.input('diameter', '60');
    h.setRows([{ id: 'bino', name: 'Replacement binoculars', kind: 'binocular', apertureMm: 70, magnification: 15 }]);
    h.mounted.refresh();
    assert.equal(h.elements.instrument.value, 'bino');
    assert.equal(h.elements['binocular-field'].value, '');
    assert.match(h.elements.field.textContent, /^Unknown/);
    assert.equal(h.elements.diagram.hidden, true);
});

test('camera mode gives rectangular field and truthful optional pixel values without eyepiece magnification', () => {
    const h = harness();
    h.setRows([...h.rows, { id: 'camera', name: 'Sensor', kind: 'camera', sensorWidthMm: 36, sensorHeightMm: 24, pixelSizeUm: null }]);
    h.mounted.refresh(); h.select('mode', 'camera'); h.select('instrument', 'scope'); h.select('camera', 'camera'); h.input('diameter', '30');
    assert.match(h.elements.field.textContent, /2.062° wide × 1.375° high/);
    assert.match(h.elements.pixel.textContent, /^Unknown/);
    assert.equal(h.elements.magnification.textContent, 'Not applicable to camera projection');
    assert.equal(h.elements.eyepiece.disabled, true);
    assert.equal(h.elements['camera-diagram'].hidden, false);
    assert.equal(h.elements.diagram.hidden, true);
    assert.match(h.elements.comparison.textContent, /rectangular field/);
    assert.equal(h.elements['camera-description'].textContent, h.elements.comparison.textContent);
    assert.equal(h.rows.at(-1).pixelSizeUm, null);
});
test('changing modes or removing the selected camera clears inappropriate results and diagrams', () => {
    const h = harness();
    h.setRows([...h.rows, { id: 'camera', name: 'Sensor', kind: 'camera', sensorWidthMm: 36, sensorHeightMm: 24, pixelSizeUm: 5 }]);
    h.mounted.refresh(); h.select('mode', 'camera'); h.select('instrument', 'scope'); h.select('camera', 'camera'); h.input('diameter', '30');
    assert.match(h.elements.pixel.textContent, /1.031 arcseconds/);
    h.input('diameter', '10800');
    assert.equal(h.elements['camera-diagram'].hidden, true);
    assert.match(h.elements.error.textContent, /smaller than 180/);
    h.input('diameter', '30');
    h.setRows(h.rows.filter(row => row.kind !== 'camera')); h.mounted.refresh();
    assert.equal(h.elements.camera.value, '');
    assert.match(h.elements.field.textContent, /^Unknown/);
    assert.equal(h.elements['camera-diagram'].hidden, true);
    h.select('mode', 'visual'); h.select('eyepiece', 'eye');
    assert.equal(h.elements.magnification.textContent, '50×');
    assert.equal(h.elements.camera.disabled, true);
    assert.equal(h.elements['camera-input'].hidden, true);
    assert.equal(h.elements['camera-diagram'].hidden, true);
});

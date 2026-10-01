import test from 'node:test';
import assert from 'node:assert/strict';
import { sessionExport, sessionCsv, mountNightSession } from '../../resources/js/observing/night-session.js';
function sample() {
    return { export_schema_version: 1, kind: 'public-universe-observing-session', reproducibility: {},
        input: { date: '2026-10-01', timezone: 'Europe/London', lat: 51.5, lon: -0.12, horizon_mask: [{ azimuth_deg: 0, min_altitude_deg: 10 }] },
        constraints: { horizon_mask: [{ azimuth_deg: 0, min_altitude_deg: 10 }], min_altitude_deg: 20 },
        method: { provider: 'jpl-de440s', kernel: { sha256: 'abc' } },
        targets: [{ id: 'moon', name: 'Moon', status: 'windows_found', windows: [{ start_utc: '2026-10-01T20:00:00Z', end_utc: '2026-10-01T20:00:01Z' }] },
            { id: 'saturn', name: 'Saturn', status: 'unresolved_grazing', windows: [] }] };
}
test('summary exports remove coordinates and terrain by default without mutating the displayed calculation', () => {
    const original = sample(), before = structuredClone(original), out = JSON.parse(sessionExport(original));
    assert.deepEqual(original, before); assert.equal(out.location_included, false);
    for (const key of ['lat', 'lon', 'horizon_mask']) assert.equal(Object.hasOwn(out.input, key), false);
    assert.equal(Object.hasOwn(out.constraints, 'horizon_mask'), false);
    assert.equal(out.reproducibility.inputs_complete, false);
    assert.deepEqual(out.method, original.method); assert.deepEqual(out.targets, original.targets);
});
test('only boolean true opts into location and repeatable inputs', () => {
    const original = sample(), out = JSON.parse(sessionExport(original, true));
    assert.deepEqual(out.input, original.input); assert.deepEqual(out.constraints, original.constraints);
    assert.equal(out.reproducibility.inputs_complete, true);
    assert.equal(JSON.parse(sessionExport(original, 'true')).location_included, false);
});
test('CSV preserves seconds and unresolved targets and neutralizes formula-bearing labels', () => {
    const original = sample(); original.targets[0].name = ' \t=HYPERLINK("bad")';
    const csv = sessionCsv(original);
    assert.match(csv, /"' \t=HYPERLINK\(""bad""\)"/);
    assert.match(csv, /2026-10-01T20:00:01Z/); assert.match(csv, /unresolved_grazing/);
    assert.doesNotMatch(csv, /51\.5|-0\.12/); assert.match(csv, /companion JSON/);
    assert.match(sessionCsv(original, true), /51\.5/);
});
test('summary formatting refuses oversized or unsupported data', () => {
    const data = sample(); data.targets[0].name = 'x'.repeat(160000);
    assert.throws(() => sessionExport(data), /limit/);
    assert.throws(() => sessionExport({ ...sample(), export_schema_version: 2 }), /not supported/);
});


test('download and print require explicit actions and reset location consent on teardown', () => {
    const handlers = new Map(), downloads = [], blobs = [], printed = [];
    const nodes = Object.fromEntries(['data', 'error', 'locations', 'controls', 'json', 'csv', 'print', 'status'].map(name => [name, {
        checked: true, hidden: true, textContent: name === 'data' ? JSON.stringify(sample()) : '',
        addEventListener(event, fn) { handlers.set(`${name}:${event}`, fn); },
        removeEventListener(event) { handlers.delete(`${name}:${event}`); },
    }]));
    let locationHidden = true;
    const privateNode = { classList: { toggle(name, hide) { assert.equal(name, 'print:hidden'); locationHidden = hide; } } };
    const view = { URL: { createObjectURL(blob) { blobs.push(blob); return 'blob:session'; }, revokeObjectURL() {} },
        setTimeout(fn) { fn(); }, print() { printed.push(locationHidden); } };
    const doc = { defaultView: view, createElement() { return { click() { downloads.push(this.download); } }; } };
    const root = { ownerDocument: doc, querySelector(selector) { return nodes[selector.slice('[data-night-session-'.length, -1)]; }, querySelectorAll() { return [privateNode]; } };
    const mounted = mountNightSession(root);
    assert.equal(nodes.locations.checked, false); assert.equal(nodes.controls.hidden, false);
    assert.deepEqual(downloads, []); assert.equal(locationHidden, true);
    handlers.get('json:click')(); assert.deepEqual(downloads, ['public-universe-night-2026-10-01.json']);
    assert.equal(blobs.length, 1); assert.match(nodes.status.textContent, /omitted/);
    nodes.locations.checked = true; handlers.get('locations:change')();
    handlers.get('print:click')(); assert.deepEqual(printed, [false]);
    mounted.dispose(); assert.equal(locationHidden, true); assert.equal(nodes.locations.checked, false); assert.equal(handlers.size, 0);
});

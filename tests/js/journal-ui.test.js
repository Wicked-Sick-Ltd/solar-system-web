import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { mountJournal } from '../../resources/js/observing/journal-ui.js';
import { JOURNAL_KEY, emptyJournal, parseJournal } from '../../resources/js/observing/journal-store.js';
import { WORKSPACE_KEY, LOCATION_KEY } from '../../resources/js/observing/workspace-store.js';

// Small mounted-DOM harness: select defaults, live controls, bubbling is not
// mocked as persistence. Every assertion inspects actual module output/storage.
class Element {
    constructor(tag = 'div') { this.tagName = tag; this.children = []; this.attributes = {}; this.listeners = new Map(); this.style = {}; this._text = ''; this._value = ''; this.hidden = false; this.disabled = false; this.checked = false; }
    set textContent(value) { this._text = String(value); this.children = []; }
    get textContent() { return this._text + this.children.map(child => child.textContent).join(''); }
    set value(value) { this._value = String(value); }
    get value() { return this.tagName === 'select' ? (this.options.find(option => option.value === this._value) ?? this.options[0])?.value ?? '' : this._value; }
    get options() { return this.children.filter(child => child.tagName === 'option'); }
    append(...children) { this.children.push(...children); }
    replaceChildren(...children) { this.children = [...children]; this._text = ''; this._value = ''; }
    setAttribute(key, value) { this.attributes[key] = String(value); }
    addEventListener(type, listener) { if (!this.listeners.has(type)) this.listeners.set(type, new Set()); this.listeners.get(type).add(listener); }
    removeEventListener(type, listener) { this.listeners.get(type)?.delete(listener); }
    async emit(type, extra = {}) {
        const event = { target: this, currentTarget: this, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; }, ...extra };
        await Promise.all([...(this.listeners.get(type) ?? [])].map(listener => listener(event)));
        return event;
    }
    click() { return this.emit('click'); }
    focus() { this.focused = true; }
    all() { return this.children.flatMap(child => [child, ...child.all()]); }
    querySelectorAll(selector) {
        if (selector === 'input[name="equipmentIds"]:checked') return this.all().filter(child => child.tagName === 'input' && child.name === 'equipmentIds' && child.checked);
        return [];
    }
    reset() { for (const field of Object.values(this.elements ?? {})) field.value = ''; }
}
const id = value => `00000000-0000-4000-8000-${String(value).padStart(12, '0')}`;
const reference = (name = 'Saturn', key = 'planet-saturn') => ({ catalogue: 'solar', id: key, label: name });
const workspace = () => JSON.parse(readFileSync(new URL('../fixtures/observing/workspace-v1.json', import.meta.url)));
function documentWithList() {
    return { ...emptyJournal(), lists: [{ id: id(100), name: 'Synthetic night list', items: [
        { id: id(101), target: reference(), status: 'planned' },
        { id: id(102), target: reference('Jupiter', 'planet-jupiter'), status: 'planned' },
    ] }] };
}
function disk(initial = {}) {
    const values = new Map(Object.entries(initial));
    return { values, writes: [], deniedRead: false, deniedWrite: false,
        getItem(key) { if (this.deniedRead) throw new Error('Storage access denied'); return values.get(key) ?? null; },
        setItem(key, value) { if (this.deniedWrite) throw new Error('Quota exceeded'); this.writes.push([key, value]); values.set(key, value); },
        removeItem(key) { values.delete(key); },
    };
}
function harness({ journal = null, store = null, url = 'https://publicuniverse.test/observing-journal' } = {}) {
    store ??= disk(journal === null ? {} : { [JOURNAL_KEY]: typeof journal === 'string' ? journal : JSON.stringify(journal) });
    const elements = Object.fromEntries(['error', 'status', 'lists', 'entries', 'empty-lists', 'empty-entries', 'undo', 'reload', 'equipment', 'include-locations', 'json', 'csv', 'print', 'import', 'import-preview', 'import-description', 'apply', 'cancel-import', 'raw', 'correction-panel', 'correction-title', 'correction-reference', 'correction-list', 'correction-observation', 'cancel-correction'].map(name => [name, new Element()]));
    elements['import-preview'].hidden = true;
    for (const name of ['list-form', 'target-form', 'entry-form', 'correction-form']) {
        const form = new Element('form');
        form.elements = Object.fromEntries(['listName', 'catalogue', 'targetId', 'targetLabel', 'observedAtUtc', 'timezone', 'outcome', 'notes'].map(field => [field, new Element('input')]));
        form.elements.listId = new Element('select'); form.elements.siteId = new Element('select');
        form.elements.catalogue.value = 'solar'; form.elements.timezone.value = 'UTC'; form.elements.outcome.value = 'seen';
        form.append(...Object.values(form.elements)); elements[name] = form;
    }
    elements['entry-form'].append(elements.equipment);
    let counter = 1000;
    const blobs = [], revoked = [], timers = [], downloads = [];
    const view = { location: { href: url }, localStorage: store, crypto: { randomUUID: () => id(counter++) },
        URL: { createObjectURL(blob) { blobs.push(blob); return `blob:test-${blobs.length}`; }, revokeObjectURL(value) { revoked.push(value); } },
        setTimeout(callback) { timers.push(callback); return timers.length; }, print() { view.prints++; }, prints: 0,
    };
    const doc = { defaultView: view, createTextNode: value => Object.assign(new Element('#text'), { textContent: value }), createElement(tag) {
        const result = new Element(tag);
        if (tag === 'a') result.click = () => downloads.push({ href: result.href, name: result.download });
        return result;
    } };
    const root = new Element(); root.ownerDocument = doc;
    const controls = [new Element('fieldset'), new Element('fieldset'), new Element('fieldset')]; controls.forEach(control => control.disabled = true);
    root.querySelector = selector => elements[selector.slice('[data-journal-'.length, -1)];
    root.querySelectorAll = selector => selector === 'fieldset[data-journal-controls]' ? controls : [];
    const mounted = mountJournal(root, store);
    return { root, elements, store, mounted, controls, blobs, revoked, timers, downloads, view,
        read() { const raw = store.values.get(JOURNAL_KEY); return raw === undefined ? emptyJournal() : parseJournal(raw); },
        button(label) { const found = [...elements.lists.all(), ...elements.entries.all()].find(el => el.tagName === 'button' && el.textContent === label); assert.ok(found, `Missing button ${label}`); return found; },
        statusSelect(label) { return elements.lists.all().find(el => el.tagName === 'select' && el.attributes['aria-label'] === `Status for ${label}`); },
    };
}
function fillObservation(h) {
    const fields = h.elements['entry-form'].elements;
    fields.targetId.value = 'planet-saturn'; fields.targetLabel.value = 'Saturn'; fields.observedAtUtc.value = '2026-10-01T22:30:00Z'; fields.notes.value = 'Synthetic test observation.';
    return fields;
}
function deferred() { let resolve; const promise = new Promise(done => { resolve = done; }); return { promise, resolve }; }
async function chooseFile(h, data) { h.elements.import.files = [{ size: data.length, text: async () => data }]; await h.elements.import.emit('change'); }

test('URL prefill never saves a target or location and listeners prevent native form submission', async () => {
    const h = harness({ url: 'https://publicuniverse.test/observing-journal?catalogue=solar&target=planet-saturn&label=Saturn' });
    assert.equal(h.elements['target-form'].elements.targetId.value, 'planet-saturn');
    assert.equal(h.elements['entry-form'].elements.targetLabel.value, 'Saturn');
    assert.match(h.elements.status.textContent, /nothing has been saved automatically/);
    assert.equal(h.store.writes.length, 0); assert.equal(h.store.values.has(LOCATION_KEY), false);
    assert.ok(h.controls.every(control => !control.disabled));
    h.elements['list-form'].elements.listName.value = 'Synthetic list';
    const event = await h.elements['list-form'].emit('submit');
    assert.equal(event.defaultPrevented, true); assert.equal(h.read().lists[0].name, 'Synthetic list');
});

test('list ordering, status and removal are persisted independently of actual observations', async () => {
    const h = harness({ journal: documentWithList() });
    assert.equal(h.button('Move earlier: Saturn').disabled, true);
    assert.equal(h.button('Move later: Jupiter').disabled, true);
    await h.button('Move later: Saturn').click();
    assert.deepEqual(h.read().lists[0].items.map(row => row.target.label), ['Jupiter', 'Saturn']);
    const select = h.statusSelect('Saturn'); select.value = 'observed'; await select.emit('change');
    assert.equal(h.read().lists[0].items[1].status, 'observed'); assert.equal(h.read().observations.length, 0);
    await h.button('Remove Jupiter from list').click(); assert.equal(h.read().lists[0].items.length, 1);
    await h.elements.undo.click(); assert.equal(h.read().lists[0].items.length, 2);
    await h.button('Remove list Synthetic night list').click(); assert.equal(h.read().lists.length, 0);
    await h.elements.undo.click(); assert.equal(h.read().lists.length, 1);
});

test('initial corrupt or denied storage remains visibly reported and is never overwritten', () => {
    const corrupt = harness({ journal: '{broken' });
    assert.match(corrupt.elements.error.textContent, /JSON|readable/);
    assert.equal(corrupt.store.values.get(JOURNAL_KEY), '{broken'); assert.equal(corrupt.store.writes.length, 0);
    const store = disk(); store.deniedRead = true;
    const denied = harness({ store });
    assert.match(denied.elements.error.textContent, /denied/); assert.equal(store.writes.length, 0);
});

test('quota failure restores the displayed saved status instead of retaining an unsaved observation claim', async () => {
    const h = harness({ journal: documentWithList() }); h.store.deniedWrite = true;
    const select = h.statusSelect('Saturn'); select.value = 'observed'; await select.emit('change');
    assert.match(h.elements.error.textContent, /Quota/);
    assert.equal(h.read().lists[0].items[0].status, 'planned');
    assert.equal(h.statusSelect('Saturn').value, 'planned');
});

test('stale tabs cannot overwrite newer records and reload restores the current journal', async () => {
    const h = harness({ journal: documentWithList() });
    const next = documentWithList(); next.lists[0].name = 'Newer tab name';
    h.store.values.set(JOURNAL_KEY, JSON.stringify(next));
    await h.button('Remove Saturn from list').click();
    assert.match(h.elements.error.textContent, /another tab/); assert.equal(h.read().lists[0].items.length, 2);
    await h.elements.reload.click(); assert.match(h.elements.lists.textContent, /Newer tab name/);
    assert.equal(h.store.writes.length, 0);
});

test('imports require explicit replacement and undo restores the prior journal without changing workspace or observer location', async () => {
    const h = harness({ journal: documentWithList() });
    h.store.values.set(WORKSPACE_KEY, JSON.stringify(workspace())); h.store.values.set(LOCATION_KEY, 'retained location marker');
    const original = h.store.values.get(JOURNAL_KEY), profiles = h.store.values.get(WORKSPACE_KEY);
    await chooseFile(h, JSON.stringify(emptyJournal()));
    assert.equal(h.elements['import-preview'].hidden, false); assert.equal(h.store.values.get(JOURNAL_KEY), original);
    await h.elements.apply.click(); assert.equal(h.read().lists.length, 0);
    await h.elements.undo.click(); assert.equal(h.read().lists.length, 1);
    assert.equal(h.store.values.get(WORKSPACE_KEY), profiles); assert.equal(h.store.values.get(LOCATION_KEY), 'retained location marker');
});

test('newer file selection, cancel, reload and navigation invalidate pending import reads', async () => {
    for (const action of ['replace', 'cancel', 'reload', 'dispose']) {
        const h = harness({ journal: documentWithList() }), first = deferred();
        h.elements.import.files = [{ size: 20, text: () => first.promise }];
        const pending = h.elements.import.emit('change');
        if (action === 'replace') await chooseFile(h, JSON.stringify(documentWithList()));
        if (action === 'cancel') await h.elements['cancel-import'].click();
        if (action === 'reload') await h.elements.reload.click();
        if (action === 'dispose') h.mounted.dispose();
        first.resolve(JSON.stringify(emptyJournal())); await pending;
        if (action === 'replace') assert.match(h.elements['import-description'].textContent, /1 lists/);
        else assert.equal(h.elements['import-preview'].hidden, true);
        assert.equal(h.store.writes.length, 0);
    }
});

test('deleted selected equipment refuses a save and leaves notes and existing history intact', async () => {
    const store = disk({ [WORKSPACE_KEY]: JSON.stringify(workspace()) }), h = harness({ store });
    const fields = fillObservation(h), checkbox = h.elements.equipment.all().find(el => el.tagName === 'input'); checkbox.checked = true;
    const changed = workspace(); changed.equipment = [];
    store.values.set(WORKSPACE_KEY, JSON.stringify(changed));
    await h.elements['entry-form'].emit('submit');
    assert.match(h.elements.error.textContent, /no longer exists/);
    assert.equal(h.read().observations.length, 0); assert.equal(fields.notes.value, 'Synthetic test observation.');
});

test('recorded setup survives later profile deletion while default JSON and CSV exports omit sites and protect formulas', async () => {
    const setup = workspace(), store = disk({ [WORKSPACE_KEY]: JSON.stringify(setup) }), h = harness({ store });
    const fields = fillObservation(h); fields.siteId.value = setup.sites[0].id; fields.notes.value = '=HYPERLINK("test")\nSynthetic notes';
    h.elements.equipment.all().find(el => el.tagName === 'input').checked = true;
    await h.elements['entry-form'].emit('submit');
    assert.equal(h.read().observations[0].equipmentAndSite.sites.length, 1);
    store.values.set(WORKSPACE_KEY, JSON.stringify({ schemaVersion: 1, equipment: [], sites: [], activeSiteId: null }));
    await h.elements.reload.click();
    assert.match(h.elements.entries.textContent, /Example London site/);
    const printedSite = h.elements.entries.all().find(element => element.tagName === 'span' && element.textContent.includes('Example London site'));
    assert.equal(printedSite.className, 'print:hidden');
    await h.elements.json.click();
    const exported = parseJournal(await h.blobs[0].text());
    assert.equal(exported.observations[0].equipmentAndSite.sites.length, 0);
    assert.equal(exported.observations[0].equipmentAndSite.equipment.length, 1);
    await h.elements.csv.click(); const csv = await h.blobs[1].text();
    assert.match(csv, /'=HYPERLINK/); assert.equal(csv.includes('Example London site'), false);
    h.elements['include-locations'].checked = true; await h.elements['include-locations'].emit('change');
    assert.equal(printedSite.className, '');
    await h.elements.json.click();
    assert.equal(parseJournal(await h.blobs[2].text()).observations[0].equipmentAndSite.sites.length, 1);
    assert.equal(h.read().observations[0].equipmentAndSite.sites.length, 1);
    h.mounted.dispose(); h.timers.forEach(callback => callback());
    assert.equal(h.revoked.length, 3);
});

test('quota failures preserve observation input and dispose removes mounted form/listener actions', async () => {
    const h = harness(); const fields = fillObservation(h); h.store.deniedWrite = true;
    await h.elements['entry-form'].emit('submit'); assert.match(h.elements.error.textContent, /Quota/);
    assert.equal(fields.notes.value, 'Synthetic test observation.'); assert.equal(h.read().observations.length, 0);
    h.mounted.dispose(); h.store.deniedWrite = false;
    for (const element of Object.values(h.elements)) assert.equal([...element.listeners.values()].reduce((sum, set) => sum + set.size, 0), 0);
    await h.elements['entry-form'].emit('submit'); assert.equal(h.store.writes.length, 0);
});


test('list rename preserves stable list and target identities, can cancel and supports undo', async () => {
    const original = documentWithList(), h = harness({ journal: original });
    await h.button('Rename list Synthetic night list').click();
    const form = h.elements['correction-form'];
    assert.equal(form.elements.listName.focused, true);
    form.elements.listName.value = 'Cancelled name';
    await h.elements['cancel-correction'].click();
    assert.deepEqual(h.read(), original); assert.equal(h.store.writes.length, 0);
    await h.button('Rename list Synthetic night list').click();
    form.elements.listName.value = 'Corrected list';
    const event = await form.emit('submit');
    assert.equal(event.defaultPrevented, true);
    assert.equal(h.read().lists[0].name, 'Corrected list');
    assert.equal(h.read().lists[0].id, original.lists[0].id);
    assert.deepEqual(h.read().lists[0].items, original.lists[0].items);
    assert.equal(h.elements['correction-panel'].hidden, true);
    assert.equal(h.button('Rename list Corrected list').focused, true);
    await h.elements.undo.click(); assert.deepEqual(h.read(), original);
});

test('observation correction preserves original target and historical snapshot even after current profiles change', async () => {
    const setup = workspace(), h = harness({ store: disk({ [WORKSPACE_KEY]: JSON.stringify(setup) }) });
    const fields = fillObservation(h); fields.siteId.value = setup.sites[0].id;
    h.elements.equipment.all().find(el => el.tagName === 'input').checked = true;
    await h.elements['entry-form'].emit('submit');
    const original = h.read().observations[0];
    h.store.values.set(WORKSPACE_KEY, '{unreadable newer workspace');
    await h.button(`Correct observation of Saturn at ${original.observedAtUtc}`).click();
    const form = h.elements['correction-form'];
    assert.equal(form.elements.notes.value, original.notes);
    form.elements.observedAtUtc.value = '2026-10-02T01:00:05Z';
    form.elements.timezone.value = 'Europe/London'; form.elements.outcome.value = 'uncertain';
    form.elements.notes.value = 'Corrected notes';
    // Extra/malicious form fields must never replace identity or historical data.
    form.elements.targetId.value = 'planet-mars'; form.elements.targetLabel.value = 'Mars';
    await form.emit('submit');
    const saved = h.read().observations[0];
    assert.deepEqual(saved, { ...original, observedAtUtc: '2026-10-02T01:00:05Z', timezone: 'Europe/London', outcome: 'uncertain', notes: 'Corrected notes' });
    assert.equal(h.read().observations.length, 1);
    await h.elements.undo.click(); assert.deepEqual(h.read().observations[0], original);
});

test('invalid correction values and quota failure keep saved data and pending fields until explicit cancellation', async () => {
    const h = harness(); fillObservation(h); await h.elements['entry-form'].emit('submit');
    const original = h.read();
    await h.button('Correct observation of Saturn at 2026-10-01T22:30:00Z').click();
    const form = h.elements['correction-form'];
    for (const value of ['2026-02-30T22:00:00Z', '2026-10-02T01:00:00+01:00', '10000-01-01T00:00:00Z']) {
        form.elements.observedAtUtc.value = value; await form.emit('submit');
        assert.deepEqual(h.read(), original); assert.equal(form.elements.observedAtUtc.value, value);
        assert.equal(h.elements['correction-panel'].hidden, false); assert.match(h.elements.error.textContent, /UTC/);
    }
    form.elements.observedAtUtc.value = '2026-10-01T23:00:00Z';
    form.elements.timezone.value = '+01:00'; await form.emit('submit'); assert.match(h.elements.error.textContent, /IANA/);
    form.elements.timezone.value = 'UTC'; form.elements.notes.value = 'x'.repeat(4001); await form.emit('submit');
    assert.match(h.elements.error.textContent, /too long/); assert.deepEqual(h.read(), original);
    form.elements.notes.value = 'Pending correction'; h.store.deniedWrite = true;
    await form.emit('submit'); assert.match(h.elements.error.textContent, /Quota/);
    assert.deepEqual(h.read(), original); assert.equal(form.elements.notes.value, 'Pending correction');
    h.store.deniedWrite = false; await form.emit('submit');
    assert.equal(h.read().observations[0].notes, 'Pending correction');
});

test('pending corrections block import, reload and other writes; stale-tab failure retains them for copying', async () => {
    const h = harness({ journal: documentWithList() });
    await h.button('Rename list Synthetic night list').click();
    const form = h.elements['correction-form']; form.elements.listName.value = 'Pending rename';
    await h.elements.reload.click(); assert.match(h.elements.error.textContent, /Save or cancel/);
    await h.button('Remove Saturn from list').click(); assert.match(h.elements.error.textContent, /Save or cancel/);
    await chooseFile(h, JSON.stringify(emptyJournal())); assert.match(h.elements.error.textContent, /Save or cancel/);
    assert.equal(h.elements['import-preview'].hidden, true); assert.equal(h.store.writes.length, 0);
    const newer = documentWithList(); newer.lists[0].name = 'Another tab';
    h.store.values.set(JOURNAL_KEY, JSON.stringify(newer));
    await form.emit('submit'); assert.match(h.elements.error.textContent, /another tab/);
    assert.deepEqual(h.read(), newer); assert.equal(form.elements.listName.value, 'Pending rename');
    await h.elements['cancel-correction'].click(); await h.elements.reload.click();
    assert.match(h.elements.lists.textContent, /Another tab/);
});

test('opening a correction invalidates an earlier pending file read and exports only saved records', async () => {
    const h = harness({ journal: documentWithList() }), file = deferred();
    h.elements.import.files = [{ size: 10, text: () => file.promise }]; const pending = h.elements.import.emit('change');
    await h.button('Rename list Synthetic night list').click();
    h.elements['correction-form'].elements.listName.value = 'Unsaved name';
    file.resolve(JSON.stringify(emptyJournal())); await pending;
    assert.equal(h.elements['import-preview'].hidden, true);
    await h.elements.json.click();
    assert.equal(parseJournal(await h.blobs[0].text()).lists[0].name, 'Synthetic night list');
    assert.match(h.elements.status.textContent, /open correction is excluded/);
    h.mounted.dispose(); assert.ok(h.controls.every(control => control.disabled));
});

test('raw recovery preserves corrupt and unsupported storage exactly without parsing, redaction or writes', async () => {
    for (const raw of ['{broken private notes', '{"schemaVersion":999,"privateSite":"test"}', 'unpaired: \ud800']) {
        const h = harness({ journal: raw });
        await h.elements.raw.click();
        const recovery = JSON.parse(await h.blobs[0].text());
        assert.equal(recovery.storageKey, JOURNAL_KEY); assert.equal(recovery.originalValue, raw);
        assert.equal(h.store.values.get(JOURNAL_KEY), raw); assert.equal(h.store.writes.length, 0);
        assert.match(h.elements.status.textContent, /without validation or redaction/);
        assert.equal(h.downloads[0].name, 'public-universe-journal-recovery.json');
    }
    const empty = harness(); await empty.elements.raw.click(); assert.match(empty.elements.error.textContent, /no stored journal/);
    const store = disk(); store.deniedRead = true; const denied = harness({ store });
    await denied.elements.raw.click(); assert.match(denied.elements.error.textContent, /denied/); assert.equal(denied.blobs.length, 0);
});


test('bfcache remount clears an old editor instead of presenting fields with no active record', async () => {
    const h = harness({ journal: documentWithList() });
    await h.button('Rename list Synthetic night list').click();
    h.elements['correction-form'].elements.listName.value = 'Unsaved prior page';
    h.mounted.dispose();
    const restored = mountJournal(h.root, h.store);
    assert.equal(h.elements['correction-panel'].hidden, true);
    assert.equal(h.elements['correction-form'].elements.listName.value, '');
    assert.equal(h.elements['correction-list'].disabled, true);
    assert.equal(h.elements['correction-observation'].disabled, true);
    assert.equal(h.read().lists[0].name, 'Synthetic night list');
    await h.button('Rename list Synthetic night list').click();
    assert.equal(h.elements['correction-form'].elements.listName.value, 'Synthetic night list');
    restored.dispose();
});

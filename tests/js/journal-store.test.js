import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { emptyJournal, validateJournal, parseJournal, journalExport, journalCsv, snapshotSetup, openJournal, JOURNAL_KEY, JOURNAL_MAX_BYTES } from '../../resources/js/observing/journal-store.js';

const id = n => `00000000-0000-4000-8000-${String(n).padStart(12, '0')}`;
const target = () => ({ catalogue: 'solar', id: 'planet-saturn', label: 'Saturn' });
const setup = () => JSON.parse(readFileSync(new URL('../fixtures/observing/workspace-v1.json', import.meta.url)));
function example() {
    const workspace = setup();
    return { schemaVersion: 1, lists: [{ id: id(1), name: 'Autumn nights', items: [{ id: id(2), target: target(), status: 'planned' }] }],
        observations: [{ id: id(3), target: target(), observedAtUtc: '2026-10-01T22:30:00Z', timezone: 'Europe/London', outcome: 'seen', notes: 'Rings visible briefly between clouds.',
            equipmentAndSite: snapshotSetup(workspace, [workspace.equipment[0].id], workspace.sites[0].id) }] };
}
function storage() {
    const values = new Map();
    return { getItem: key => values.get(key) ?? null, setItem: (key, value) => values.set(key, value), removeItem: key => values.delete(key) };
}

test('round-trips unavailable catalogue references without live lookups or identity changes', () => {
    const doc = example();
    doc.lists[0].items.push({ id: id(4), target: { catalogue: 'starter', id: 'openngc:NGC0224', label: 'M 31' }, status: 'skipped' });
    assert.deepEqual(parseJournal(journalExport(doc, true)), validateJournal(doc));
});
test('historical equipment and site snapshots do not follow later profile edits', () => {
    const workspace = setup();
    const snapshot = snapshotSetup(workspace, [workspace.equipment[0].id], workspace.sites[0].id);
    const initial = JSON.stringify(snapshot);
    workspace.equipment[0].name = 'Edited telescope'; workspace.sites[0].latitude = -32;
    assert.equal(JSON.stringify(snapshot), initial);
    assert.throws(() => snapshotSetup(workspace, [id(999)], null));
    assert.throws(() => snapshotSetup(workspace, [], id(999)));
});
test('exports omit site names and coordinates by default, without deleting saved context', () => {
    const doc = example(), before = JSON.stringify(doc);
    const output = parseJournal(journalExport(doc));
    assert.equal(output.observations[0].equipmentAndSite.sites.length, 0);
    assert.equal(output.observations[0].equipmentAndSite.activeSiteId, null);
    assert.equal(output.observations[0].equipmentAndSite.equipment.length, 1);
    assert.equal(JSON.stringify(doc), before);
    assert.equal(parseJournal(journalExport(doc, true)).observations[0].equipmentAndSite.sites.length, 1);
});
test('CSV quotes multiline notes and neutralises spreadsheet formulas including leading spaces', () => {
    const doc = example(); doc.observations[0].notes = '  =HYPERLINK("malicious")\nmore notes';
    const csv = journalCsv(doc);
    assert.ok(csv.includes('"\'=HYPERLINK(""malicious"")\nmore notes"'));
    assert.ok(csv.includes('"[]"')); // Sites omitted.
    for (const value of ['+1', '-1', '@SUM(1)']) {
        doc.observations[0].target.label = value;
        assert.ok(journalCsv(doc).includes(`"'${value}"`));
    }
});
test('stale-tab saves refuse to overwrite newer changes', () => {
    const disk = storage(), first = openJournal(disk), second = openJournal(disk);
    first.save(example());
    assert.throws(() => second.save(emptyJournal()), /another tab/);
    assert.equal(parseJournal(disk.getItem(JOURNAL_KEY)).lists.length, 1);
});
test('denied storage reports failure and preserves in-memory and prior saved records', () => {
    const disk = storage(), journal = openJournal(disk);
    disk.setItem = () => { throw new Error('Quota exceeded'); };
    assert.throws(() => journal.save(example()), /Quota/);
    assert.deepEqual(journal.read(), emptyJournal());
    assert.equal(disk.getItem(JOURNAL_KEY), null);
});
test('records are isolated from caller mutations and malformed saved state is never silently reset', () => {
    const disk = storage(), journal = openJournal(disk), doc = example();
    journal.save(doc); doc.lists[0].name = 'Changed';
    const output = journal.read(); output.lists[0].name = 'Also changed';
    assert.equal(journal.read().lists[0].name, 'Autumn nights');
    disk.setItem(JOURNAL_KEY, '{corrupt');
    assert.throws(() => openJournal(disk), /JSON/);
    assert.equal(disk.getItem(JOURNAL_KEY), '{corrupt');
});
for (const [name, change] of [
    ['future schema', doc => doc.schemaVersion = 2],
    ['unknown fields', doc => doc.secret = 'no'],
    ['boolean target', doc => doc.observations[0].target = true],
    ['URL identity', doc => doc.observations[0].target.id = 'https://example.com'],
    ['false time', doc => doc.observations[0].observedAtUtc = '2026-02-30T22:30:00Z'],
    ['unlabelled local time', doc => doc.observations[0].observedAtUtc = '2026-10-25T01:30:00'],
    ['invalid zone', doc => doc.observations[0].timezone = 'Wrong/Zone'],
    ['fixed-offset zone without IANA identity', doc => doc.observations[0].timezone = '+01:00'],
    ['unbounded notes', doc => doc.observations[0].notes = 'x'.repeat(4001)],
    ['duplicate records', doc => doc.observations[0].id = doc.lists[0].id],
    ['duplicate target per list', doc => doc.lists[0].items.push({ ...doc.lists[0].items[0], id: id(99) })],
    ['invalid status', doc => doc.lists[0].items[0].status = 'visible'],
    ['invalid outcome', doc => doc.observations[0].outcome = 'guaranteed'],
    ['malformed snapshot', doc => doc.observations[0].equipmentAndSite = {}],
]) test(`rejects ${name}`, () => { const doc = example(); change(doc); assert.throws(() => validateJournal(doc)); });
test('rejects oversized imports before parsing and counts global listed targets', () => {
    assert.throws(() => parseJournal(' '.repeat(JOURNAL_MAX_BYTES + 1)), /1 MiB/);
    const doc = example(); doc.lists = Array.from({ length: 21 }, (_, i) => ({ id: id(i + 100), name: 'List', items: [] }));
    assert.throws(() => validateJournal(doc), /20 lists/);
});

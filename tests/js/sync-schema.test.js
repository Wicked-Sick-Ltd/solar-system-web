import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { validateWorkspace } from '../../resources/js/observing/workspace-store.js';
import { validateJournal } from '../../resources/js/observing/journal-store.js';

const cases = JSON.parse(readFileSync(new URL('../fixtures/observing-sync/schema-cases.json', import.meta.url), 'utf8'));
for (const fixture of cases) test(`shared sync schema: ${fixture.name}`, () => {
    const validate = () => ({ equipmentWorkspace: validateWorkspace(fixture.payload.equipmentWorkspace), journal: validateJournal(fixture.payload.journal) });
    if (!fixture.valid) { assert.throws(validate); return; }
    const canonical = validate();
    assert.equal(canonical.equipmentWorkspace.schemaVersion, 2);
    assert.deepEqual(validateJournal(canonical.journal), canonical.journal);
    if (fixture.name.startsWith('U0085')) assert.equal(canonical.journal.observations[0].notes, '\u0085Private\u0085');
});

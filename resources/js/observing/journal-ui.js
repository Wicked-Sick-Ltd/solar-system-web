import { loadWorkspace } from './workspace-store.js';
import { JOURNAL_KEY, JOURNAL_MAX_BYTES, emptyJournal, openJournal, parseJournal, journalExport, journalCsv, snapshotSetup, targetReference } from './journal-store.js';

export function mountJournal(root, storage = null) {
    const doc = root.ownerDocument, view = doc.defaultView;
    const get = name => root.querySelector(`[data-journal-${name}]`);
    let session, current = emptyJournal(), pendingImport = null, undo = null, disposed = false, importSequence = 0;
    const cleanups = [];
    let correction = null;
    const correctionButtons = new Map();
    let privateSiteNodes = [];
    function updatePrintPrivacy() { for (const site of privateSiteNodes) site.className = get('include-locations').checked ? '' : 'print:hidden'; }
    function on(element, event, handler) { element.addEventListener(event, handler); cleanups.push(() => element.removeEventListener(event, handler)); }
    function node(tag, value, className = '') { const element = doc.createElement(tag); element.textContent = value; element.className = className; return element; }
    function button(label, action) {
        const element = node('button', label, 'min-h-11 rounded border px-3 py-2 text-sm print:hidden'); element.type = 'button';
        element.addEventListener('click', () => attempt(action)); return element;
    }
    function attempt(action) {
        if (disposed) return;
        get('error').textContent = '';
        try { action(); } catch (error) { get('error').textContent = `${error.message} Nothing new was saved.`; }
    }
    function requireNoCorrection() {
        if (correction) throw new Error('Save or cancel the open correction before changing or reloading other records.');
    }
    function commit(next, undoable = false, fromCorrection = false) {
        if (!fromCorrection) requireNoCorrection();
        if (!session) throw new Error('Reload readable saved data before making changes.');
        const before = current;
        current = session.save(next);
        undo = undoable ? before : null;
        render(); get('status').textContent = 'Saved in this browser.';
    }
    function edit(action, undoable = false) { const next = structuredClone(current); action(next); commit(next, undoable); }
    function target(form) {
        return targetReference({ catalogue: form.elements.catalogue.value, id: form.elements.targetId.value.trim(), label: form.elements.targetLabel.value.trim() });
    }
    function render() {
        privateSiteNodes = [];
        correctionButtons.clear();
        get('lists').replaceChildren(); get('entries').replaceChildren();
        const select = get('target-form').elements.listId, selected = select.value;
        select.replaceChildren();
        for (const list of current.lists) {
            const option = node('option', list.name); option.value = list.id; select.append(option);
            const article = node('article', '', 'surface space-y-3 p-4'); article.append(node('h3', list.name, 'text-xl'));
            const rename = button(`Rename list ${list.name}`, () => startCorrection('list', list.id));
            correctionButtons.set(list.id, rename); article.append(rename);
            article.append(button(`Remove list ${list.name}`, () => edit(next => { next.lists = next.lists.filter(row => row.id !== list.id); }, true)));
            const ordered = doc.createElement('ol'); ordered.className = 'space-y-3';
            list.items.forEach((item, index) => {
                const row = node('li', '', 'space-y-2 rounded border p-3');
                row.append(node('p', `${item.target.label} · ${item.target.catalogue}:${item.target.id}`));
                const state = doc.createElement('select'); state.setAttribute('aria-label', `Status for ${item.target.label}`); state.className = 'rounded border p-2';
                for (const value of ['planned', 'observed', 'skipped']) { const opt = node('option', value); opt.value = value; state.append(opt); } state.value = item.status;
                state.addEventListener('change', () => {
                    const requested = state.value;
                    state.value = item.status; // Keep the saved value visible if persistence fails.
                    attempt(() => edit(next => { next.lists.find(l => l.id === list.id).items.find(i => i.id === item.id).status = requested; }));
                });
                row.append(state);
                for (const [label, delta] of [['Move earlier', -1], ['Move later', 1]]) {
                    const move = button(`${label}: ${item.target.label}`, () => edit(next => {
                        const items = next.lists.find(l => l.id === list.id).items;
                        [items[index], items[index + delta]] = [items[index + delta], items[index]];
                    })); move.disabled = index + delta < 0 || index + delta >= list.items.length; row.append(move);
                }
                row.append(button(`Remove ${item.target.label} from list`, () => edit(next => {
                    const chosen = next.lists.find(l => l.id === list.id); chosen.items = chosen.items.filter(i => i.id !== item.id);
                }, true)));
                ordered.append(row);
            });
            article.append(ordered); get('lists').append(article);
        }
        if ([...select.options].some(option => option.value === selected)) select.value = selected;
        for (const entry of [...current.observations].reverse()) {
            const article = node('article', '', 'surface space-y-3 p-4');
            article.append(node('h3', entry.target.label, 'text-xl'), node('p', `${entry.observedAtUtc} · ${entry.timezone} · ${entry.outcome}`));
            const notes = node('p', entry.notes); notes.style.whiteSpace = 'pre-wrap'; article.append(notes);
            const setup = entry.equipmentAndSite;
            const equipmentLine = node('p', `Recorded equipment: ${setup?.equipment.map(row => row.name).join(', ') || 'not supplied'}.`);
            const siteLine = node('span', ` Site: ${setup?.sites[0]?.name ?? 'not supplied'}.`);
            privateSiteNodes.push(siteLine); equipmentLine.append(siteLine); article.append(equipmentLine);
            const correct = button(`Correct observation of ${entry.target.label} at ${entry.observedAtUtc}`, () => startCorrection('observation', entry.id));
            correctionButtons.set(entry.id, correct); article.append(correct);
            article.append(button(`Remove observation of ${entry.target.label}`, () => edit(next => { next.observations = next.observations.filter(row => row.id !== entry.id); }, true)));
            get('entries').append(article);
        }
        get('empty-lists').hidden = current.lists.length > 0;
        get('empty-entries').hidden = current.observations.length > 0;
        get('undo').hidden = undo === null;
        updatePrintPrivacy();
    }
    function reload() { requireNoCorrection(); session = openJournal(storage); current = session.read(); undo = null; render(); get('status').textContent = 'Loaded from this browser.'; }
    function startCorrection(kind, id) {
        requireNoCorrection();
        const record = (kind === 'list' ? current.lists : current.observations).find(row => row.id === id);
        if (!record) throw new Error('The selected record is unavailable. Reload before correcting it.');
        correction = { kind, id };
        importSequence++; pendingImport = null; get('import-preview').hidden = true;
        const form = get('correction-form'); form.reset();
        const list = kind === 'list';
        get('correction-title').textContent = list ? 'Rename observing list' : 'Correct recorded observation';
        get('correction-reference').textContent = list ? record.name : `${record.target.label} · ${record.target.catalogue}:${record.target.id}`;
        get('correction-list').hidden = !list; get('correction-list').disabled = !list;
        get('correction-observation').hidden = list; get('correction-observation').disabled = list;
        if (list) form.elements.listName.value = record.name;
        else for (const key of ['observedAtUtc', 'timezone', 'outcome', 'notes']) form.elements[key].value = record[key];
        get('correction-panel').hidden = false;
        (list ? form.elements.listName : form.elements.observedAtUtc).focus();
        get('status').textContent = 'Correction opened. Nothing changes until you save; other record changes wait until you save or cancel.';
    }
    function closeCorrection() {
        const id = correction?.id;
        correction = null; get('correction-panel').hidden = true; get('correction-form').reset();
        get('correction-list').disabled = true; get('correction-observation').disabled = true;
        correctionButtons.get(id)?.focus();
    }
    function fillSetup() {
        const workspace = loadWorkspace(storage), form = get('entry-form');
        form.elements.siteId.replaceChildren(node('option', 'No site snapshot'));
        form.elements.siteId.options[0].value = '';
        for (const site of workspace.sites) { const option = node('option', site.name); option.value = site.id; form.elements.siteId.append(option); }
        get('equipment').replaceChildren();
        for (const equipment of workspace.equipment) {
            const label = doc.createElement('label'), checkbox = doc.createElement('input'); checkbox.type = 'checkbox'; checkbox.name = 'equipmentIds'; checkbox.value = equipment.id;
            label.className = 'inline-flex min-h-11 items-center gap-2 rounded border p-2'; label.append(checkbox, doc.createTextNode(equipment.name)); get('equipment').append(label);
        }
    }
    function download(csv = false) {
        if (!session) throw new Error('Load a readable journal before exporting.');
        const locations = get('include-locations').checked;
        const body = csv ? journalCsv(current, locations) : journalExport(current, locations);
        saveFile(body, csv ? 'public-universe-observations.csv' : 'public-universe-journal-v1.json', csv ? 'text/csv;charset=utf-8' : 'application/json');
        get('status').textContent = 'Saved records exported; any open correction is excluded. Keep the downloaded file private; notes may contain personal information.';
    }
    function saveFile(body, name, type) {
        const url = view.URL.createObjectURL(new Blob([body], { type }));
        const anchor = doc.createElement('a'); anchor.href = url; anchor.download = name; anchor.click();
        view.setTimeout(() => view.URL.revokeObjectURL(url), 1000);
    }
    on(get('raw'), 'click', () => attempt(() => {
        storage ??= view.localStorage;
        const raw = storage.getItem(JOURNAL_KEY);
        if (raw === null) throw new Error('There is no stored journal to recover.');
        // A JSON string envelope preserves even unpaired UTF-16 code units.
        // This is an original-data recovery file, not a valid journal import.
        saveFile(JSON.stringify({ storageKey: JOURNAL_KEY, originalValue: raw }), 'public-universe-journal-recovery.json', 'application/json');
        get('status').textContent = 'Original storage copied into a recovery envelope without validation or redaction. It may include private sites and notes. This file is not a journal import; no stored data was changed.';
    }));
    on(get('correction-form'), 'submit', event => { event.preventDefault(); attempt(() => {
        if (!correction) return;
        const next = structuredClone(current), form = event.currentTarget;
        const record = (correction.kind === 'list' ? next.lists : next.observations).find(row => row.id === correction.id);
        if (!record) throw new Error('The record is no longer available.');
        if (correction.kind === 'list') record.name = form.elements.listName.value;
        else for (const key of ['observedAtUtc', 'timezone', 'outcome', 'notes']) record[key] = form.elements[key].value;
        // ID, target and the historical setup are never copied from editable inputs.
        commit(next, true, true); closeCorrection();
        get('status').textContent = 'Correction saved in this browser. Record identity and any historical equipment/site snapshot were preserved. Undo can restore the previous saved record.';
    }); });
    on(get('cancel-correction'), 'click', () => attempt(() => {
        closeCorrection(); get('status').textContent = 'Correction cancelled. Saved records are unchanged.';
    }));
    on(get('list-form'), 'submit', event => { event.preventDefault(); attempt(() => {
        const form = event.currentTarget;
        edit(next => next.lists.push({ id: view.crypto.randomUUID(), name: form.elements.listName.value, items: [] })); form.reset();
    }); });
    on(get('target-form'), 'submit', event => { event.preventDefault(); attempt(() => {
        const form = event.currentTarget, reference = target(form);
        edit(next => { const list = next.lists.find(row => row.id === form.elements.listId.value); if (!list) throw new Error('Create and choose a list first.'); list.items.push({ id: view.crypto.randomUUID(), target: reference, status: 'planned' }); });
    }); });
    on(get('entry-form'), 'submit', event => { event.preventDefault(); attempt(() => {
        const form = event.currentTarget;
        const equipment = [...form.querySelectorAll('input[name="equipmentIds"]:checked')].map(input => input.value);
        const siteId = form.elements.siteId.value || null;
        const snapshot = equipment.length || siteId ? snapshotSetup(loadWorkspace(storage), equipment, siteId) : null;
        const record = { id: view.crypto.randomUUID(), target: target(form), observedAtUtc: form.elements.observedAtUtc.value,
            timezone: form.elements.timezone.value, outcome: form.elements.outcome.value, notes: form.elements.notes.value, equipmentAndSite: snapshot };
        edit(next => next.observations.push(record)); form.elements.notes.value = '';
    }); });
    on(get('reload'), 'click', () => attempt(() => { requireNoCorrection(); importSequence++; pendingImport = null; get('import-preview').hidden = true; reload(); fillSetup(); }));
    on(get('undo'), 'click', () => attempt(() => { if (undo) commit(undo); }));
    on(get('json'), 'click', () => attempt(() => download()));
    on(get('csv'), 'click', () => attempt(() => download(true)));
    on(get('include-locations'), 'change', updatePrintPrivacy);
    on(get('print'), 'click', () => view.print());
    on(get('import'), 'change', async event => {
        if (correction) { get('error').textContent = 'Save or cancel the open correction before importing a journal.'; return; }
        const sequence = ++importSequence;
        pendingImport = null; get('import-preview').hidden = true;
        const file = event.target.files?.[0]; if (!file) return;
        try {
            if (file.size > JOURNAL_MAX_BYTES) throw new Error('Choose a JSON file no larger than 1 MiB.');
            const candidate = parseJournal(await file.text()); if (disposed || sequence !== importSequence) return;
            pendingImport = candidate;
            get('import-description').textContent = `${candidate.lists.length} lists and ${candidate.observations.length} observations. Applying replaces this browser’s journal; equipment/site profiles are unchanged.`;
            get('import-preview').hidden = false;
        } catch (error) { if (!disposed && sequence === importSequence) get('error').textContent = error.message; }
    });
    on(get('apply'), 'click', () => attempt(() => { if (pendingImport) { importSequence++; commit(pendingImport, true); pendingImport = null; get('import-preview').hidden = true; } }));
    on(get('cancel-import'), 'click', () => { importSequence++; pendingImport = null; get('import-preview').hidden = true; });
    // A bfcache restore can reuse DOM from an earlier mounted instance.
    // Unsaved correction/import previews do not survive navigation.
    closeCorrection(); get('import-preview').hidden = true;
    let initialReady = false;
    attempt(() => { storage ??= view.localStorage; reload(); fillSetup(); initialReady = true; });
    if (initialReady) attempt(() => {
        const query = new URL(view.location.href).searchParams;
        if (!query.has('target')) return;
        const reference = targetReference({ catalogue: query.get('catalogue'), id: query.get('target'), label: query.get('label') });
        for (const form of [get('target-form'), get('entry-form')]) {
            form.elements.catalogue.value = reference.catalogue;
            form.elements.targetId.value = reference.id;
            form.elements.targetLabel.value = reference.label;
        }
        get('status').textContent = `${reference.label} is ready in the forms below. Choose a list or enter your actual observation; nothing has been saved automatically.`;
    });
    // Enable only after listeners prevent accidental native submission.
    root.querySelectorAll('fieldset[data-journal-controls]').forEach(fieldset => fieldset.disabled = false);
    return { dispose() { disposed = true; importSequence++; for (const cleanup of cleanups) cleanup(); root.querySelectorAll('fieldset[data-journal-controls]').forEach(fieldset => fieldset.disabled = true); } };
}

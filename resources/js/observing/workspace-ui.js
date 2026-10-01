import {
    WORKSPACE_KEY, LOCATION_KEY, MAX_BYTES, emptyWorkspace, loadWorkspace, saveWorkspace,
    parseWorkspace, validateWorkspace, putEntry, removeEntry, activateSite, activeLocationMatches,
} from './workspace-store.js';

// A browser-only controller: no fetch, Livewire actions or account identifiers.
export function workspaceController(storage) {
    let expected = null;
    let available = false;
    let workspace = emptyWorkspace();
    function assertCurrent() {
        if (!available) throw new Error('Browser storage is unavailable or unreadable. Export a backup before replacing it.');
        if (storage.getItem(WORKSPACE_KEY) !== expected) throw new Error('Your workspace changed in another tab. Reload the workspace before saving.');
    }
    function remember(next) { workspace = next; expected = storage.getItem(WORKSPACE_KEY); return workspace; }
    return {
        get value() { return workspace; },
        load() {
            available = false;
            workspace = loadWorkspace(storage);
            expected = storage.getItem(WORKSPACE_KEY);
            available = true;
            return workspace;
        },
        save(collection, entry) {
            assertCurrent();
            // Editing a site does not silently change the observer's location.
            // Explicit "Use this site" is needed after changing coordinates.
            return remember(saveWorkspace(storage, putEntry(workspace, collection, entry)));
        },
        remove(collection, entryId) {
            assertCurrent();
            // Keep observer_location: a previously chosen location is an
            // independent setting, and deleting a named site must not clear a
            // newer selection made elsewhere. The UI explains this explicitly.
            return remember(saveWorkspace(storage, removeEntry(workspace, collection, entryId)));
        },
        activate(entryId) { assertCurrent(); return remember(activateSite(storage, workspace, entryId)); },
        import(text) { return parseWorkspace(text); },
        replace(preview) {
            assertCurrent();
            // Imports restore records, never silently activate imported locations.
            return remember(saveWorkspace(storage, { ...validateWorkspace(preview), activeSiteId: null }));
        },
        export() {
            if (!available) throw new Error('The workspace could not be loaded. Use Download original stored data for recovery.');
            // Use the same compact encoding bounded by the importer.
            return JSON.stringify(validateWorkspace(workspace));
        },
        rawBackup() { return storage.getItem(WORKSPACE_KEY); },
        reset() {
            // Also allows explicit recovery from an unsupported/corrupt document.
            storage.removeItem(WORKSPACE_KEY);
            available = true;
            return remember(emptyWorkspace());
        },
        isActive(entryId) { return workspace.activeSiteId === entryId && activeLocationMatches(storage, workspace); },
    };
}

function numeric(form, key, optional = false) {
    const text = form.elements.namedItem(key).value.trim();
    if (optional && text === '') return null;
    if (!/^[+-]?(?:[0-9]+\.?[0-9]*|\.[0-9]+)(?:[eE][+-]?[0-9]+)?$/.test(text)) throw new Error('Enter numbers using a decimal point; leave unknown optional values blank.');
    return Number(text);
}
function equipmentFromForm(form, uuid) {
    const kind = form.elements.namedItem('kind').value;
    const out = { id: form.elements.namedItem('entryId').value || uuid(), name: form.elements.namedItem('name').value, kind };
    if (['telescope', 'binocular'].includes(kind)) out.apertureMm = numeric(form, 'apertureMm');
    if (['telescope', 'eyepiece'].includes(kind)) out.focalLengthMm = numeric(form, 'focalLengthMm');
    if (kind === 'binocular') out.magnification = numeric(form, 'magnification');
    if (kind === 'eyepiece') {
        out.apparentFovDeg = numeric(form, 'apparentFovDeg', true);
        out.fieldStopMm = numeric(form, 'fieldStopMm', true);
    }
    if (['barlow', 'reducer'].includes(kind)) out.factor = numeric(form, 'factor');
    return out;
}
export function equipmentSummary(entry) {
    switch (entry.kind) {
        case 'telescope': return `Telescope · ${entry.apertureMm} mm aperture · ${entry.focalLengthMm} mm focal length`;
        case 'binocular': return `Binoculars · ${entry.magnification}× · ${entry.apertureMm} mm aperture`;
        case 'eyepiece': return `Eyepiece · ${entry.focalLengthMm} mm · apparent field ${entry.apparentFovDeg === null ? 'unknown' : `${entry.apparentFovDeg}°`} · field stop ${entry.fieldStopMm === null ? 'unknown' : `${entry.fieldStopMm} mm`}`;
        default: return `${entry.kind === 'barlow' ? 'Barlow' : 'Reducer'} · ${entry.factor}× factor`;
    }
}

export function mountWorkspace(root, options = {}) {
    const doc = root.ownerDocument;
    const view = doc.defaultView;
    let storage;
    try { storage = options.storage ?? view.localStorage; } catch { /* Storage can be blocked before getItem. */ }
    const controller = workspaceController(storage);
    const uuid = options.uuid ?? (() => view.crypto.randomUUID());
    const confirm = options.confirm ?? (message => view.confirm(message));
    const listeners = [];
    let disposed = false;
    let preview = null;
    let importGeneration = 0;
    const urls = new Set();
    const get = key => root.querySelector(`[data-workspace-${key}]`);
    const equipmentForm = get('equipment-form');
    const siteForm = get('site-form');
    function listen(target, type, handler) { target.addEventListener(type, handler); listeners.push(() => target.removeEventListener(type, handler)); }
    function message(text, error = false) {
        const target = get(error ? 'error' : 'status');
        get(error ? 'status' : 'error').textContent = '';
        target.textContent = text;
    }
    function attempt(action) {
        try { action(); } catch (error) { message(`${error.message || 'Browser storage failed.'} No successful save was confirmed.`, true); }
    }
    function kindFields() {
        const kind = equipmentForm.elements.namedItem('kind').value;
        equipmentForm.querySelectorAll('[data-kinds]').forEach(group => {
            const shown = group.dataset.kinds.split(' ').includes(kind);
            group.hidden = !shown;
            group.querySelectorAll('input').forEach(input => { input.disabled = !shown; });
        });
    }
    function resetForm(form) {
        form.reset();
        form.elements.namedItem('entryId').value = '';
        form.querySelector('[type="submit"]').textContent = form === equipmentForm ? 'Save equipment' : 'Save site';
        if (form === equipmentForm) kindFields();
    }
    function edit(collection, entryId) {
        const entry = controller.value[collection].find(item => item.id === entryId);
        if (!entry) throw new Error('The entry no longer exists.');
        const form = collection === 'equipment' ? equipmentForm : siteForm;
        resetForm(form);
        for (const [key, value] of Object.entries(entry)) {
            const input = form.elements.namedItem(key === 'id' ? 'entryId' : key);
            if (input) input.value = value ?? '';
        }
        if (form === equipmentForm) kindFields();
        form.querySelector('[type="submit"]').textContent = 'Save changes';
        form.elements.namedItem('name').focus();
    }
    function button(label, action, collection, entryId) {
        const el = doc.createElement('button');
        el.type = 'button';
        el.className = 'min-h-11 rounded-lg border px-3 py-2 text-sm';
        el.style.borderColor = 'var(--border)';
        el.textContent = label;
        el.dataset.workspaceAction = action;
        el.dataset.collection = collection;
        el.dataset.entryId = entryId;
        return el;
    }
    function render() {
        for (const collection of ['equipment', 'sites']) {
            const list = get(`${collection}-list`);
            list.replaceChildren();
            get(`${collection}-empty`).hidden = controller.value[collection].length > 0;
            for (const entry of controller.value[collection]) {
                const item = doc.createElement('li');
                item.className = 'rounded-lg border p-4';
                item.style.borderColor = 'var(--border)';
                const heading = doc.createElement('h3');
                heading.className = 'font-medium';
                heading.textContent = entry.name;
                const details = doc.createElement('p');
                details.className = 'mt-1 text-sm';
                details.style.color = 'var(--muted)';
                details.textContent = collection === 'equipment' ? equipmentSummary(entry)
                    : `${entry.latitude.toFixed(2)}, ${entry.longitude.toFixed(2)} · ${entry.timezone} · minimum altitude ${entry.minAltitudeDeg}°`;
                const actions = doc.createElement('div');
                actions.className = 'mt-3 flex flex-wrap gap-2';
                actions.append(button(`Edit ${entry.name}`, 'edit', collection, entry.id), button(`Delete ${entry.name}`, 'delete', collection, entry.id));
                if (collection === 'sites') actions.append(button(controller.isActive(entry.id) ? `${entry.name} is active — use again` : `Use ${entry.name}`, 'activate', collection, entry.id));
                item.append(heading, details, actions);
                list.append(item);
            }
        }
    }
    function download(text, filename) {
        const url = view.URL.createObjectURL(new Blob([text], { type: 'application/json' }));
        urls.add(url);
        const link = doc.createElement('a');
        link.href = url;
        link.download = filename;
        link.click();
        // Retain until teardown so browsers have time to start the download.
    }
    function closePreview() { preview = null; get('preview').hidden = true; }
    function load() {
        importGeneration++;
        controller.load();
        render();
        resetForm(equipmentForm);
        resetForm(siteForm);
        closePreview();
        message('Workspace loaded from this browser.');
    }
    // Prevent native form submission before enabling any controls. Personal
    // field values must never fall back to a URL query or a Livewire payload.
    listen(root, 'submit', event => {
        event.preventDefault();
        if (disposed) return;
        attempt(() => {
            if (event.target === equipmentForm) controller.save('equipment', equipmentFromForm(equipmentForm, uuid));
            else if (event.target === siteForm) controller.save('sites', {
                id: siteForm.elements.namedItem('entryId').value || uuid(), name: siteForm.elements.namedItem('name').value,
                latitude: numeric(siteForm, 'latitude'), longitude: numeric(siteForm, 'longitude'),
                timezone: siteForm.elements.namedItem('timezone').value.trim(), minAltitudeDeg: numeric(siteForm, 'minAltitudeDeg'),
            });
            else return;
            render();
            resetForm(event.target);
            message('Saved in this browser. Editing a site does not change the active observing location until you choose Use.');
        });
    });
    listen(equipmentForm.elements.namedItem('kind'), 'change', kindFields);
    listen(root, 'click', event => {
        const control = event.target.closest('[data-workspace-action]');
        if (!control || !root.contains(control) || disposed) return;
        attempt(() => {
            const { workspaceAction: action, collection, entryId } = control.dataset;
            if (action === 'edit') edit(collection, entryId);
            if (action === 'cancel-equipment') resetForm(equipmentForm);
            if (action === 'cancel-site') resetForm(siteForm);
            if (action === 'delete' && confirm('Delete this saved entry? Your existing observer location will remain available in Your settings.')) {
                controller.remove(collection, entryId); render(); message('Entry deleted.');
            }
            if (action === 'activate') {
                controller.activate(entryId); render();
                view.dispatchEvent(new view.CustomEvent('observer-location-changed'));
                message('This site is now the active approximate location for sky calculations. Timezone and minimum altitude are saved preferences; current sky calculations do not apply them.');
            }
            if (action === 'reload') load();
            if (action === 'export') { download(controller.export(), 'public-universe-workspace.json'); message('Backup prepared. It includes private site names and coordinates.'); }
            if (action === 'raw-backup') {
                const raw = controller.rawBackup();
                if (raw === null) throw new Error('There is no saved workspace to back up.');
                download(raw, 'public-universe-workspace-recovery.json'); message('The original stored document was copied without modification.');
            }
            if (action === 'cancel-import') { importGeneration++; closePreview(); }
            if (action === 'apply-import' && preview) { controller.replace(preview); closePreview(); render(); message('Workspace replaced. Choose Use on a site to change your observing location.'); }
            if (action === 'reset' && confirm('Delete all equipment and named sites saved in this browser? Export a backup first if you want to keep them. Your independent observer location is not removed.')) {
                importGeneration++; controller.reset(); closePreview(); render(); resetForm(equipmentForm); resetForm(siteForm); message('Workspace removed from this browser.');
            }
        });
    });
    listen(get('import'), 'change', async event => {
        const generation = ++importGeneration;
        closePreview();
        const file = event.target.files?.[0];
        if (!file) return;
        try {
            if (file.size > MAX_BYTES) throw new Error('Choose a JSON file no larger than 128 KiB.');
            const text = await file.text();
            if (disposed || generation !== importGeneration) return;
            preview = controller.import(text);
            get('preview-summary').textContent = `${preview.equipment.length} equipment entries and ${preview.sites.length} sites. This replaces your existing workspace. Your active observing location will not change.`;
            get('preview-details').textContent = [...preview.equipment.map(item => `${item.name}: ${equipmentSummary(item)}`), ...preview.sites.map(item => `${item.name}: ${item.latitude.toFixed(2)}, ${item.longitude.toFixed(2)}, ${item.timezone}, minimum altitude ${item.minAltitudeDeg}°`)].join('\n') || 'Empty workspace.';
            get('preview').hidden = false;
            message('Import ready for review. Nothing has been changed.');
        } catch (error) { if (!disposed && generation === importGeneration) message(error.message, true); }
        finally { if (!disposed && generation === importGeneration) event.target.value = ''; }
    });
    listen(view, 'storage', event => {
        if (event.key === WORKSPACE_KEY || event.key === null) message('Workspace storage changed in another tab. Reload before editing; existing form text is preserved until then.', true);
        if (event.key === LOCATION_KEY) attempt(render);
    });
    root.querySelectorAll('[data-workspace-enabled]').forEach(control => { control.disabled = false; });
    kindFields();
    attempt(load);
    return {
        dispose() {
            disposed = true;
            importGeneration++;
            listeners.forEach(remove => remove());
            urls.forEach(url => view.URL.revokeObjectURL(url));
            // A BFCache/navigation snapshot must not contain enabled native forms.
            root.querySelectorAll('[data-workspace-enabled]').forEach(control => { control.disabled = true; });
        },
    };
}

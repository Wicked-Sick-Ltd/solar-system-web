import { validateWorkspace } from './workspace-store.js';

export const JOURNAL_KEY = 'public_universe_journal_v1';
export const JOURNAL_MAX_BYTES = 1048576;
const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
const bytes = value => new TextEncoder().encode(value).length;
const fail = message => { throw new Error(message); };
function shape(value, keys) {
    if (!value || typeof value !== 'object' || Array.isArray(value)
        || keys.some(key => !Object.hasOwn(value, key))
        || Object.keys(value).some(key => !keys.includes(key))) fail('The journal contains missing or unsupported fields.');
}
function uuid(value) {
    if (typeof value !== 'string' || !UUID.test(value)) fail('A valid record identifier is required.');
    return value.toLowerCase();
}
function text(value, maximum, empty = false) {
    if (typeof value !== 'string' || value.length > maximum || (!empty && !value.trim())
        || /[\u0000-\u0008\u000b\u000c\u000e-\u001f\u007f]/.test(value)) fail('Text is missing, too long or contains unsupported control characters.');
    return value.trim();
}
function choice(value, choices) {
    if (!choices.includes(value)) fail('An unsupported journal choice was supplied.');
    return value;
}
function instant(value) {
    if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/.test(value)
        || !Number.isFinite(Date.parse(value)) || new Date(value).toISOString().replace('.000Z', 'Z') !== value) fail('Use a real UTC date and time: YYYY-MM-DDTHH:MM:SSZ.');
    return value;
}
function timezone(value) {
    if (typeof value !== 'string' || value.length > 100) fail('A recognised IANA timezone is required.');
    try { return new Intl.DateTimeFormat('en', { timeZone: value }).resolvedOptions().timeZone; }
    catch { fail('A recognised IANA timezone is required.'); }
}
export function targetReference(value) {
    shape(value, ['catalogue', 'id', 'label']);
    const catalogue = choice(value.catalogue, ['solar', 'starter', 'exoplanet']);
    if (typeof value.id !== 'string' || !/^[A-Za-z0-9][A-Za-z0-9:_.+\-]{0,159}$/.test(value.id)) fail('Use an exact catalogue identifier, not a name or URL.');
    return { catalogue, id: value.id, label: text(value.label, 200) };
}
export function emptyJournal() { return { schemaVersion: 1, lists: [], observations: [] }; }
export function validateJournal(value) {
    shape(value, ['schemaVersion', 'lists', 'observations']);
    if (value.schemaVersion !== 1) fail('This journal version is not supported. Nothing was changed.');
    if (!Array.isArray(value.lists) || value.lists.length > 20 || !Array.isArray(value.observations) || value.observations.length > 1000) fail('The journal supports up to 20 lists and 1,000 observations.');
    const ids = [];
    let itemCount = 0;
    const lists = value.lists.map(list => {
        shape(list, ['id', 'name', 'items']);
        if (!Array.isArray(list.items) || list.items.length > 200) fail('A list supports up to 200 targets.');
        const id = uuid(list.id); ids.push(id);
        const targets = new Set();
        const items = list.items.map(item => {
            shape(item, ['id', 'target', 'status']);
            const id = uuid(item.id); ids.push(id); itemCount++;
            const target = targetReference(item.target);
            const key = `${target.catalogue}:${target.id}`;
            if (targets.has(key)) fail('A target can appear only once in the same list.');
            targets.add(key);
            return { id, target, status: choice(item.status, ['planned', 'observed', 'skipped']) };
        });
        return { id, name: text(list.name, 100), items };
    });
    if (itemCount > 2000) fail('The journal supports at most 2,000 listed targets in total.');
    const observations = value.observations.map(observation => {
        shape(observation, ['id', 'target', 'observedAtUtc', 'timezone', 'outcome', 'notes', 'equipmentAndSite']);
        const id = uuid(observation.id); ids.push(id);
        // Copy selected specifications into each observation. Later edits to
        // live profiles cannot alter this historical context.
        const snapshot = observation.equipmentAndSite === null ? null : validateWorkspace(observation.equipmentAndSite);
        if (snapshot && snapshot.sites.length > 1) fail('An observation can snapshot at most one observing site.');
        return { id, target: targetReference(observation.target), observedAtUtc: instant(observation.observedAtUtc),
            timezone: timezone(observation.timezone), outcome: choice(observation.outcome, ['seen', 'not_seen', 'uncertain']),
            notes: text(observation.notes, 4000, true), equipmentAndSite: snapshot };
    });
    if (new Set(ids).size !== ids.length) fail('Journal record identifiers must be unique.');
    const out = { schemaVersion: 1, lists, observations };
    if (bytes(JSON.stringify(out)) > JOURNAL_MAX_BYTES) fail('The journal exceeds its 1 MiB storage limit. Export and remove older records before adding more.');
    return out;
}
export function parseJournal(value) {
    if (typeof value !== 'string' || bytes(value) > JOURNAL_MAX_BYTES) fail('Choose a journal JSON file no larger than 1 MiB.');
    let parsed;
    try { parsed = JSON.parse(value); } catch { fail('This is not a readable journal JSON file.'); }
    return validateJournal(parsed);
}
export function snapshotSetup(workspace, equipmentIds, siteId) {
    const clean = validateWorkspace(workspace);
    if (!Array.isArray(equipmentIds) || new Set(equipmentIds).size !== equipmentIds.length
        || equipmentIds.some(id => !clean.equipment.some(row => row.id === id))
        || (siteId !== null && !clean.sites.some(row => row.id === siteId))) fail('Some selected equipment or site no longer exists. Reload before recording.');
    return validateWorkspace({ ...clean, equipment: clean.equipment.filter(row => equipmentIds.includes(row.id)),
        sites: clean.sites.filter(row => row.id === siteId), activeSiteId: siteId });
}
export function journalExport(journal, includeLocations = false) {
    const clean = validateJournal(journal);
    if (!includeLocations) for (const observation of clean.observations) {
        if (observation.equipmentAndSite) {
            observation.equipmentAndSite.sites = [];
            observation.equipmentAndSite.activeSiteId = null;
        }
    }
    return JSON.stringify(clean);
}
function csvCell(value) {
    let text = String(value ?? '');
    // Quoting alone does not prevent spreadsheet formula execution.
    if (/^[\s]*[=+\-@]/.test(text) || /^[\t\r\n]/.test(text)) text = `'${text}`;
    return `"${text.replaceAll('"', '""')}"`;
}
export function journalCsv(journal, includeLocations = false) {
    const clean = parseJournal(journalExport(journal, includeLocations));
    const rows = [['catalogue', 'target_id', 'target_label', 'observed_at_utc', 'timezone', 'outcome', 'notes', 'equipment_json', 'site_json']];
    for (const observation of clean.observations) rows.push([observation.target.catalogue, observation.target.id,
        observation.target.label, observation.observedAtUtc, observation.timezone, observation.outcome, observation.notes,
        JSON.stringify(observation.equipmentAndSite?.equipment ?? []), JSON.stringify(observation.equipmentAndSite?.sites ?? [])]);
    return rows.map(row => row.map(csvCell).join(',')).join('\r\n') + '\r\n';
}
export function openJournal(storage) {
    let original = storage.getItem(JOURNAL_KEY);
    let current = original === null ? emptyJournal() : parseJournal(original);
    return {
        read: () => validateJournal(current),
        save(next) {
            const clean = validateJournal(next);
            if (storage.getItem(JOURNAL_KEY) !== original) fail('This journal changed in another tab. Reload before saving; your pending change has not been saved.');
            const encoded = JSON.stringify(clean);
            storage.setItem(JOURNAL_KEY, encoded); // Quota/denial propagates: no false success.
            original = encoded; current = clean;
            return validateJournal(current);
        },
    };
}

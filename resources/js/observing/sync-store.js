import { WORKSPACE_KEY, emptyWorkspace, parseWorkspace, validateWorkspace } from './workspace-store.js';
import { JOURNAL_KEY, emptyJournal, parseJournal, validateJournal } from './journal-store.js';

const fail = message => { throw new Error(message); };
export function syncPayload(value) {
    if (!value || typeof value !== 'object' || Array.isArray(value)
        || Object.keys(value).length !== 2 || !Object.hasOwn(value, 'equipmentWorkspace') || !Object.hasOwn(value, 'journal')) fail('The account copy is not a supported observing document.');
    return { equipmentWorkspace: validateWorkspace(value.equipmentWorkspace), journal: validateJournal(value.journal) };
}
export function previewGuest(storage) {
    const workspace = storage.getItem(WORKSPACE_KEY), journal = storage.getItem(JOURNAL_KEY);
    return { original: { workspace, journal }, payload: {
        equipmentWorkspace: workspace === null ? emptyWorkspace() : parseWorkspace(workspace),
        journal: journal === null ? emptyJournal() : parseJournal(journal),
    } };
}
export function assertGuestUnchanged(storage, preview) {
    if (storage.getItem(WORKSPACE_KEY) !== preview.original.workspace || storage.getItem(JOURNAL_KEY) !== preview.original.journal) fail('This browser’s data changed after the preview. Preview it again before continuing.');
}
export function restoreGuest(storage, preview, payload) {
    const clean = syncPayload(payload);
    // Restoring profiles does not activate coordinates for observing calculations.
    clean.equipmentWorkspace.activeSiteId = null;
    const writes = [[WORKSPACE_KEY, JSON.stringify(clean.equipmentWorkspace), preview.original.workspace],
        [JOURNAL_KEY, JSON.stringify(clean.journal), preview.original.journal]];
    assertGuestUnchanged(storage, preview);
    const completed = [];
    let changed = false;
    try {
        for (const write of writes) {
            if (storage.getItem(write[0]) !== write[2]) { changed = true; fail('Concurrent browser change.'); }
            storage.setItem(write[0], write[1]); completed.push(write);
        }
    } catch {
        let recovered = true;
        for (const [key, encoded, before] of completed.reverse()) {
            try {
                if (storage.getItem(key) !== encoded) { recovered = false; continue; }
                before === null ? storage.removeItem(key) : storage.setItem(key, before);
            } catch { recovered = false; }
        }
        fail(recovered
            ? changed ? 'Browser records changed during replacement. Newer records were kept and earlier writes were restored. Preview again.' : 'The browser refused the replacement. Previous records were restored.'
            : 'The browser refused the replacement and could not restore every record. Check equipment and journal before continuing.');
    }
    return clean;
}
export function describePayload(payload) {
    const clean = syncPayload(payload);
    const count = (value, noun) => `${value} ${noun}${value === 1 ? '' : 's'}`;
    return `${count(clean.equipmentWorkspace.equipment.length, 'equipment profile')}, ${count(clean.equipmentWorkspace.sites.length, 'site')}, ${count(clean.journal.lists.length, 'list')} and ${count(clean.journal.observations.length, 'observation')}.`;
}
export function validateAccountCopy(value, scope) {
    if (!value || !Number.isSafeInteger(value.revision) || value.revision < 0 || value.accountScope !== scope
        || !['empty', 'saved', 'deleted'].includes(value.state)
        || (value.state === 'empty' && value.revision !== 0)
        || (value.state !== 'empty' && value.revision === 0)
        || (value.state !== 'saved' && value.payload !== null)) fail('The account preview could not be verified. Check it again.');
    return { revision: value.revision, state: value.state, payload: value.state === 'saved' ? syncPayload(value.payload) : null };
}
const errors = {
    revision_conflict: 'The account copy changed elsewhere. Check it again before choosing what to keep.',
    account_changed: 'The signed-in account changed. Reload this page before continuing.',
    authentication_required: 'Your sign-in has expired. Sign in and reopen this page.',
    session_expired: 'Your session has expired. Reload this page before continuing.',
    request_too_large: 'This observing document is too large to synchronize.',
    invalid_document: 'This observing document did not pass validation. Nothing was uploaded.',
};
export async function accountRequest({ fetcher, endpoint, scope, csrf, signal }, method = 'GET', document = null) {
    const response = await fetcher(endpoint, { method, credentials: 'same-origin', cache: 'no-store', redirect: 'error', signal,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Observing-Account': scope },
        ...(document === null ? {} : { body: JSON.stringify(document) }),
    });
    if (response.redirected) fail('Reopen the synchronization page after signing in.');
    // Bound decoded bytes even when Content-Length is absent or compressed.
    const reader = response.body?.getReader();
    if (!reader) fail('The account response could not be read.');
    const chunks = []; let length = 0;
    try {
        while (true) {
            const { value, done } = await reader.read(); if (done) break;
            length += value.byteLength;
            if (length > 1572864) { await reader.cancel(); fail('The account response exceeded its size limit.'); }
            chunks.push(value);
        }
    } finally { reader.releaseLock(); }
    const joined = new Uint8Array(length); let offset = 0;
    for (const chunk of chunks) { joined.set(chunk, offset); offset += chunk.length; }
    let value;
    try { value = JSON.parse(new TextDecoder('utf-8', { fatal: true }).decode(joined)); }
    catch { fail('The account response was not readable. Check the account copy before trying again.'); }
    if (!response.ok) fail(errors[value?.error] ?? 'Account synchronization is unavailable. Check the account copy before trying again.');
    return value;
}

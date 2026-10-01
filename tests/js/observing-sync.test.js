import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { WORKSPACE_KEY, LOCATION_KEY, emptyWorkspace } from '../../resources/js/observing/workspace-store.js';
import { JOURNAL_KEY, emptyJournal } from '../../resources/js/observing/journal-store.js';
import { accountRequest, previewGuest, restoreGuest, validateAccountCopy } from '../../resources/js/observing/sync-store.js';
import { mountObservingSync } from '../../resources/js/observing/sync-ui.js';

const payload = () => ({ equipmentWorkspace: emptyWorkspace(), journal: emptyJournal() });
const saved = (revision = 1) => ({ revision, state: 'saved', payload: payload(), accountScope: 'account-a' });
function storage(initial = {}) {
    const data = new Map(Object.entries(initial));
    return { data, writes: [], getItem(key) { return data.get(key) ?? null; },
        setItem(key, value) { this.writes.push(key); data.set(key, value); }, removeItem(key) { data.delete(key); } };
}
const json = (body, status = 200) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
function harness() {
    const elements = Object.fromEntries(['guest', 'account', 'error', 'status', 'preview', 'check', 'upload', 'restore', 'delete', 'upload-consent', 'restore-consent', 'delete-consent'].map(name => [name, {
        checked: false, disabled: true, textContent: '', handlers: new Map(),
        addEventListener(type, callback) { this.handlers.set(type, callback); }, removeEventListener(type) { this.handlers.delete(type); },
        async click() { await this.handlers.get('click')?.(); },
    }]));
    const disk = storage(), requests = [], replies = [];
    const view = { setTimeout, clearTimeout };
    const root = { ownerDocument: { defaultView: view }, dataset: { accountScope: 'account-a', endpoint: '/account/observing-workspace', csrf: 'test-token' },
        querySelector(selector) { return elements[selector.slice('[data-sync-'.length, -1)]; } };
    const mounted = mountObservingSync(root, { storage: disk, fetcher: async (endpoint, options) => {
        requests.push({ endpoint, ...options }); const reply = replies.shift(); if (reply instanceof Error) throw reply; return typeof reply === 'function' ? reply() : reply;
    } });
    return { elements, disk, requests, replies, mounted };
}

test('mounting and local preview never read the account or write guest records', async () => {
    const h = harness(); assert.equal(h.requests.length, 0); assert.equal(h.elements.upload.disabled, true);
    await h.elements.preview.click(); assert.equal(h.requests.length, 0); assert.equal(h.disk.writes.length, 0);
    assert.match(h.elements.guest.textContent, /0 equipment/); h.mounted.dispose();
});
test('strict account preview binds revisions and payload to the current account', () => {
    assert.equal(validateAccountCopy(saved(), 'account-a').revision, 1);
    for (const value of [{ ...saved(), accountScope: 'account-b' }, { ...saved(), revision: 0 },
        { revision: 0, state: 'empty', payload: {}, accountScope: 'account-a' }, { ...saved(), payload: {} }]) {
        assert.throws(() => validateAccountCopy(value, 'account-a'));
    }
});
test('upload requires explicit previews and consent and passes CSRF account scope and revision', async () => {
    const h = harness(); await h.elements.preview.click(); h.replies.push(json(saved(3))); await h.elements.check.click();
    assert.equal(h.disk.writes.length, 0); await h.elements.upload.click(); assert.equal(h.requests.length, 1);
    h.elements['upload-consent'].checked = true; h.replies.push(json({ revision: 4, state: 'saved' })); await h.elements.upload.click();
    const request = h.requests[1]; assert.equal(request.method, 'PUT'); assert.equal(request.cache, 'no-store'); assert.equal(request.redirect, 'error');
    assert.equal(request.headers['X-Observing-Account'], 'account-a'); assert.equal(request.headers['X-CSRF-TOKEN'], 'test-token');
    assert.equal(JSON.parse(request.body).expectedRevision, 3); assert.equal(h.disk.writes.length, 0);
    assert.match(h.elements.status.textContent, /not uploaded automatically/); h.mounted.dispose();
});
test('stale browser preview is rejected before an upload or replacement', async () => {
    const h = harness(); await h.elements.preview.click(); h.replies.push(json(saved())); await h.elements.check.click();
    h.disk.setItem(JOURNAL_KEY, JSON.stringify(emptyJournal())); h.elements['upload-consent'].checked = true;
    await h.elements.upload.click(); assert.equal(h.requests.length, 1); assert.match(h.elements.error.textContent, /changed after/);
    assert.throws(() => restoreGuest(h.disk, { original: { workspace: null, journal: null } }, payload()), /changed after/); h.mounted.dispose();
});
test('uncertain mutation failures invalidate the revision and require a fresh account check', async () => {
    const h = harness(); await h.elements.preview.click(); h.replies.push(json(saved())); await h.elements.check.click();
    h.elements['upload-consent'].checked = true; h.replies.push(new TypeError('raw private network path'));
    await h.elements.upload.click(); assert.equal(h.elements.upload.disabled, true);
    assert.match(h.elements.error.textContent, /may have reached/); assert.doesNotMatch(h.elements.error.textContent, /private network/); h.mounted.dispose();
});
test('restore rechecks account identity/revision before touching shared guest records', async () => {
    for (const response of [json(saved(2)), json({ error: 'account_changed' }, 409)]) {
        const h = harness(); await h.elements.preview.click(); h.replies.push(json(saved())); await h.elements.check.click();
        h.elements['restore-consent'].checked = true; h.replies.push(response); await h.elements.restore.click();
        assert.equal(h.disk.writes.length, 0); assert.match(h.elements.error.textContent, /changed/); h.mounted.dispose();
    }
});
test('restoring profiles preserves observer location and requires explicit site reactivation', () => {
    const workspace = JSON.parse(readFileSync(new URL('../fixtures/observing/workspace-v2.json', import.meta.url)));
    workspace.activeSiteId = workspace.sites[0].id;
    const disk = storage({ [LOCATION_KEY]: 'existing observer location' }), preview = previewGuest(disk);
    restoreGuest(disk, preview, { equipmentWorkspace: workspace, journal: emptyJournal() });
    assert.equal(JSON.parse(disk.getItem(WORKSPACE_KEY)).activeSiteId, null);
    assert.equal(disk.getItem(LOCATION_KEY), 'existing observer location'); assert.equal(workspace.activeSiteId, workspace.sites[0].id);
});
test('a failed second-key replacement restores the first key and reports rollback failure honestly', () => {
    for (const rollbackFails of [false, true]) {
        const disk = storage(), preview = previewGuest(disk), originalSet = disk.setItem.bind(disk);
        disk.setItem = (key, value) => { if (key === JOURNAL_KEY) throw new Error('quota'); originalSet(key, value); };
        if (rollbackFails) disk.removeItem = () => { throw new Error('denied'); };
        assert.throws(() => restoreGuest(disk, preview, payload()), rollbackFails ? /could not restore/ : /Previous records were restored/);
        if (!rollbackFails) assert.equal(disk.getItem(WORKSPACE_KEY), null);
    }
});
test('account deletion never deletes browser guest records', async () => {
    const h = harness(); h.replies.push(json(saved(5))); await h.elements.check.click();
    h.elements['delete-consent'].checked = true; h.replies.push(json({ revision: 6, state: 'deleted' })); await h.elements.delete.click();
    assert.equal(h.requests[1].method, 'DELETE'); assert.equal(JSON.parse(h.requests[1].body).expectedRevision, 5);
    assert.equal(h.disk.writes.length, 0); assert.match(h.elements.account.textContent, /revision 6/); h.mounted.dispose();
});
test('an interleaved journal edit is kept while the earlier profile write is rolled back', () => {
    const disk = storage(), preview = previewGuest(disk), originalSet = disk.setItem.bind(disk);
    disk.setItem = (key, value) => {
        originalSet(key, value);
        if (key === WORKSPACE_KEY) disk.data.set(JOURNAL_KEY, 'newer concurrent journal');
    };
    assert.throws(() => restoreGuest(disk, preview, payload()), /Newer records were kept/);
    assert.equal(disk.getItem(JOURNAL_KEY), 'newer concurrent journal');
    assert.equal(disk.getItem(WORKSPACE_KEY), null);
});
test('leaving the page clears previews and aborts pending requests', async () => {
    const h = harness(); await h.elements.preview.click(); let complete;
    h.replies.push(() => new Promise(resolve => { complete = resolve; })); const pending = h.elements.check.click();
    h.mounted.dispose(); assert.equal(h.requests[0].signal.aborted, true);
    complete(json(saved())); await pending;
    assert.equal(h.elements.account.textContent, ''); assert.equal(h.elements.guest.textContent, ''); assert.equal(h.disk.writes.length, 0);
});
test('decoded response streams are bounded without relying on Content-Length', async () => {
    let cancelled = false;
    const response = new Response(new ReadableStream({ pull(controller) { controller.enqueue(new Uint8Array(262144)); }, cancel() { cancelled = true; } }));
    await assert.rejects(accountRequest({ fetcher: async () => response, endpoint: '/account/observing-workspace', scope: 'account-a', csrf: 'test', signal: new AbortController().signal }), /size limit/);
    assert.equal(cancelled, true);
});

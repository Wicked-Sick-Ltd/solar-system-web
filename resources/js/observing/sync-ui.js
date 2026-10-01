import { accountRequest, assertGuestUnchanged, describePayload, previewGuest, restoreGuest, validateAccountCopy } from './sync-store.js';

export function mountObservingSync(root, { storage, fetcher } = {}) {
    const view = root.ownerDocument.defaultView, get = name => root.querySelector(`[data-sync-${name}]`);
    const scope = root.dataset.accountScope, endpoint = root.dataset.endpoint, csrf = root.dataset.csrf;
    let guest = null, account = null, busy = false, disposed = false, controller = null;
    const cleanups = [];
    function on(name, action) {
        const element = get(name), handler = () => run(action);
        element.addEventListener('click', handler); cleanups.push(() => element.removeEventListener('click', handler));
    }
    function resetConsent() { for (const name of ['upload-consent', 'restore-consent', 'delete-consent']) get(name).checked = false; }
    function render() {
        get('guest').textContent = guest ? describePayload(guest.payload) : 'No browser preview loaded.';
        get('account').textContent = account ? `Account revision ${account.revision}: ${account.payload ? describePayload(account.payload) : account.state === 'deleted' ? 'account copy deleted' : 'no saved account copy'}` : 'No account preview loaded.';
        get('upload').disabled = busy || !guest || !account;
        get('restore').disabled = busy || !guest || !account?.payload;
        get('delete').disabled = busy || !account?.payload;
        get('preview').disabled = busy; get('check').disabled = busy;
    }
    async function run(action) {
        if (disposed || busy) return;
        busy = true; get('error').textContent = ''; get('status').textContent = ''; render();
        try { storage ??= view.localStorage; await action(); }
        catch (error) { if (!disposed) get('error').textContent = error.message; }
        finally { busy = false; if (!disposed) render(); }
    }
    async function request(method = 'GET', body = null) {
        controller = new AbortController();
        const timeout = view.setTimeout(() => controller?.abort(), 20000);
        try {
            return await accountRequest({ fetcher: fetcher ?? view.fetch.bind(view), endpoint, scope, csrf, signal: controller.signal }, method, body);
        } catch (error) {
            // A network failure can occur after a successful server write. Drop
            // the revision so a retry always starts with an explicit fresh GET.
            account = null; resetConsent();
            if (error instanceof TypeError) throw new Error('The connection failed. Check the account copy before trying again; the previous request may have reached it.');
            throw error;
        } finally { view.clearTimeout(timeout); controller = null; }
    }
    on('preview', () => { guest = previewGuest(storage); resetConsent(); get('status').textContent = 'Browser preview ready. Nothing was sent to your account.'; });
    on('check', async () => {
        account = null; resetConsent();
        const result = await request(); if (disposed) return;
        account = validateAccountCopy(result, scope);
        get('status').textContent = 'Account preview loaded for this page only. Browser records are unchanged.';
    });
    on('upload', async () => {
        if (!guest || !account || !get('upload-consent').checked) throw new Error('Confirm that you want to upload this browser preview, including any private sites and notes.');
        assertGuestUnchanged(storage, guest);
        const revision = account.revision, payload = guest.payload;
        const result = await request('PUT', { expectedRevision: revision, payload }); if (disposed) return;
        account = null; resetConsent();
        if (result.revision !== revision + 1 || result.state !== 'saved') throw new Error('The upload response could not be verified. Check the account copy before trying again.');
        account = { revision: result.revision, state: 'saved', payload };
        get('status').textContent = 'This preview was saved to your account. Later browser edits are not uploaded automatically.';
    });
    on('restore', async () => {
        if (!guest || !account?.payload || !get('restore-consent').checked) throw new Error('Confirm replacement of this browser’s equipment, sites, lists and journal.');
        const preview = account;
        const checked = validateAccountCopy(await request(), scope); if (disposed) return;
        if (checked.revision !== preview.revision || checked.state !== 'saved') { account = null; resetConsent(); throw new Error('The account copy changed after preview. Check it again before replacing browser records.'); }
        restoreGuest(storage, guest, preview.payload); guest = previewGuest(storage); resetConsent();
        get('status').textContent = 'Account preview copied into this browser’s shared guest records. Activate a site explicitly before using it for calculations.';
    });
    on('delete', async () => {
        if (!account?.payload || !get('delete-consent').checked) throw new Error('Confirm deletion of the account copy. Export a backup first if you need it.');
        const revision = account.revision;
        const result = await request('DELETE', { expectedRevision: revision }); if (disposed) return;
        account = null; resetConsent();
        if (result.revision !== revision + 1 || result.state !== 'deleted') throw new Error('The deletion response could not be verified. Check the account copy before trying again.');
        account = { revision: result.revision, state: 'deleted', payload: null };
        get('status').textContent = 'Account copy deleted. This browser’s guest records are unchanged.';
    });
    resetConsent(); render();
    return { dispose() {
        disposed = true; controller?.abort(); account = null; guest = null;
        resetConsent(); get('account').textContent = ''; get('guest').textContent = '';
        get('status').textContent = ''; get('error').textContent = '';
        for (const cleanup of cleanups) cleanup();
    } };
}

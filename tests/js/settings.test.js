import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

// Exercise the shipped Alpine methods; only server-translated labels are substituted.
const view = readFileSync(new URL('../../resources/views/livewire/settings-page.blade.php', import.meta.url), 'utf8');
const script = view.slice(view.indexOf('<script>') + 8, view.indexOf('</script>'))
    .replace(/@js\(__\('([^']*)'\)\)/g, (_match, label) => JSON.stringify(label));
function harness() {
    let factory, location = new URL('https://publicuniverse.test/settings');
    const window = new EventTarget();
    Object.defineProperty(window, 'location', { get: () => location });
    const storage = new Map(), timers = new Map(), applications = [];
    let timerId = 0;
    const history = {
        state: { snapshotIdx: 'livewire-history-entry', url: location.href },
        writes: 0,
        replaceState(state, _title, url) { this.state = state; this.writes++; location = new URL(url, location); },
    };
    const clipboard = { writeText: async () => {} };
    window.applyTheme = theme => { applications.push(theme); storage.set('theme', theme); };
    runInNewContext(script, {
        Alpine: { data(name, callback) { assert.equal(name, 'settingsPage'); factory = callback; } },
        window, history, navigator: { clipboard }, URL, URLSearchParams, atob, btoa,
        escape, unescape, encodeURIComponent, decodeURIComponent,
        localStorage: { getItem: key => storage.get(key) ?? null, setItem: (key, value) => storage.set(key, value), removeItem: key => storage.delete(key) },
        setTimeout(callback) { timers.set(++timerId, callback); return timerId; },
        clearTimeout(id) { timers.delete(id); },
    });
    return {
        create: () => factory('https://publicuniverse.test/settings'), window, history, storage, applications, timers, clipboard,
        share(payload) { location.hash = 's=' + Buffer.from(JSON.stringify(payload)).toString('base64url'); },
        get location() { return location; },
    };
}
const plain = value => JSON.parse(JSON.stringify(value));

test('share imports require confirmation and remove only the token without erasing Livewire history', () => {
    const h = harness(), page = h.create();
    const state = h.history.state;
    h.share({ theme: 'light', location: { lat: 51.234, lon: -1.234 }, preferences: { timeFormat: '24' } });
    page.load();
    assert.equal(page.pendingImport, true);
    assert.equal(h.location.hash, '');
    assert.equal(h.history.state, state);
    assert.equal(h.storage.size, 0, 'preview never applies local settings');
    assert.deepEqual(plain(page.importData.location), { lat: 51.23, lon: -1.23 });
    page.applyImport();
    assert.deepEqual(h.applications, ['light']);
    assert.equal(h.storage.get('observer_location'), '{"lat":51.23,"lon":-1.23}');
    assert.equal(h.storage.get('preferences'), '{"timeFormat":"24"}');
    assert.equal(page.pendingImport, false);
    assert.match(page.shareUrl(), /^https:\/\/publicuniverse\.test\/settings#s=[A-Za-z0-9_-]+$/);
    page.destroy();
});

test('invalid coordinate and preference types cannot manufacture a location or time format', () => {
    const h = harness(), page = h.create();
    for (const value of [null, false, true, '', ' ', [], [12], {}, '0x10', 'Infinity', '1e309', 90.001, -90.001]) {
        assert.deepEqual(plain(page.cleanPayload({ location: { lat: value, lon: 0 } })), {}, String(value));
    }
    for (const value of [[24], { timeFormat: '24' }, true]) {
        assert.deepEqual(plain(page.cleanPayload({ preferences: { timeFormat: value } })), {});
    }
    assert.deepEqual(plain(page.cleanPayload({ location: { lat: 0, lon: '0' }, preferences: { timeFormat: 24 } })),
        { location: { lat: 0, lon: 0 }, preferences: { timeFormat: '24' } });
    assert.deepEqual(plain(page.cleanPayload({ location: { lat: '5.1e1', lon: '-1.25' } })), { location: { lat: 51, lon: -1.25 } });
});

test('invalid remembered settings are normalized for display and new share links', () => {
    const h = harness(), page = h.create();
    h.storage.set('theme', 'unknown-theme');
    h.storage.set('observer_location', JSON.stringify({ lat: 100, lon: 0 }));
    h.storage.set('preferences', JSON.stringify({ timeFormat: ['24'] }));
    page.load();
    assert.equal(page.theme, 'dark');
    assert.equal(page.location, null);
    assert.equal(page.prefs.timeFormat, 'auto');
    assert.equal(page.decodeToken(page.shareUrl().split('#s=')[1]).location, undefined);
    page.destroy();
});

test('destroyed settings pages cannot consume share fragments on other pages or duplicate remount listeners', () => {
    const h = harness(), old = h.create();
    old.load(); old.load();
    old.destroy();
    h.share({ theme: 'light' });
    h.window.dispatchEvent(new Event('hashchange'));
    assert.notEqual(h.location.hash, '', 'detached component must leave another page fragment intact');
    assert.equal(old.pendingImport, false);
    const current = h.create();
    current.load();
    assert.equal(current.pendingImport, true);
    const writes = h.history.writes;
    h.share({ theme: 'dark' });
    h.window.dispatchEvent(new Event('hashchange'));
    assert.equal(h.history.writes, writes + 1);
    assert.equal(old.pendingImport, false);
    current.destroy();
});

test('copy feedback timers and late clipboard responses are cancelled on destruction', async () => {
    const h = harness(), page = h.create();
    await page.copyShare();
    assert.equal(h.timers.size, 1);
    await page.copyShare();
    assert.equal(h.timers.size, 1);
    page.destroy();
    assert.equal(h.timers.size, 0);
    const late = h.create();
    let complete;
    h.clipboard.writeText = () => new Promise(resolve => { complete = resolve; });
    const copying = late.copyShare();
    late.destroy();
    complete();
    await copying;
    assert.equal(late.copied, false);
    assert.equal(h.timers.size, 0);
});

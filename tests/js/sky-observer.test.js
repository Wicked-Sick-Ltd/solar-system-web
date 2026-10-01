import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const view = readFileSync(new URL('../../resources/views/livewire/sky-observer.blade.php', import.meta.url), 'utf8');
const script = view.slice(view.indexOf('<script>') + 8, view.indexOf('</script>'))
    .replace(/@js\(__\('([^']*)'\)\)/g, (_match, label) => JSON.stringify(label))
    .replace('@js($lat === null)', 'true');
const deferred = () => { let resolve, reject; const promise = new Promise((yes, no) => { resolve = yes; reject = no; }); return { promise, resolve, reject }; };
const flush = async () => { for (let i = 0; i < 12; i++) await Promise.resolve(); };
function harness() {
    let factory;
    const storage = new Map(), geo = [], calls = [];
    const window = new EventTarget(), document = new EventTarget();
    const wire = {
        lat: 42, lon: 17,
        async setLocation(lat, lon) { calls.push(['location', lat, lon]); return { lat, lon }; },
        async setFromText(text) { calls.push(['text', text]); return null; },
        async forget() { calls.push(['forget']); },
    };
    runInNewContext(script, {
        Alpine: { data(name, callback) { assert.equal(name, 'skyObserver'); factory = callback; } },
        window, document, $wire: wire,
        navigator: { geolocation: { getCurrentPosition(success, failure, options) { geo.push({ success, failure, options }); } } },
        localStorage: { getItem: key => storage.get(key) ?? null, setItem: (key, value) => storage.set(key, value), removeItem: key => storage.delete(key) },
    });
    return { page: factory(), storage, geo, calls, wire, window, document };
}
const position = (latitude = 51.23456, longitude = -1.23456) => ({ coords: { latitude, longitude } });

test('one automatic initialization restores only valid rounded saved coordinates without prompting', async () => {
    assert.match(view, /<div x-data="skyObserver\(\)">/);
    const h = harness();
    h.storage.set('observer_location', JSON.stringify({ lat: 51.23456, lon: -1.23456 }));
    h.page.init(); h.page.init(); await flush();
    assert.deepEqual(h.calls, [['location', 51.23, -1.23]]);
    assert.equal(h.geo.length, 0);
    for (const value of [{ lat: 90.001, lon: 0 }, { lat: 0, lon: -180.001 }, { lat: null, lon: 0 }, { lat: '51', lon: 0 }, { lat: [], lon: 0 }]) {
        const bad = harness(); bad.storage.set('observer_location', JSON.stringify(value)); bad.page.init(); await flush();
        assert.equal(bad.calls.length, 0);
    }
});

test('explicit geolocation rounds before sending and persists only accepted coordinates', async () => {
    const h = harness(); h.page.init(); h.page.locate();
    assert.equal(h.geo.length, 1); assert.equal(h.page.busy, true);
    assert.equal(h.geo[0].options.timeout, 10000);
    h.geo[0].success(position()); await flush();
    assert.deepEqual(h.calls, [['location', 51.23, -1.23]]);
    assert.equal(h.storage.get('observer_location'), '{"lat":51.23,"lon":-1.23}');
    assert.equal(h.page.busy, false);
});

test('forget and navigation invalidate pending geolocation success and failure callbacks', async () => {
    for (const stop of ['forget', 'destroy', 'navigate', 'pagehide']) {
        const h = harness(); h.page.init(); h.page.locate();
        if (stop === 'navigate') h.document.dispatchEvent(new Event('livewire:navigating'));
        else if (stop === 'pagehide') h.window.dispatchEvent(new Event('pagehide'));
        else await h.page[stop]();
        h.geo[0].success(position()); h.geo[0].failure(); await flush();
        assert.equal(h.storage.size, 0, stop);
        assert.deepEqual(h.calls, stop === 'forget' ? [['forget']] : [], stop);
        assert.equal(h.page.geoError, '', stop);
        assert.equal(h.page.busy, false, stop);
    }
});

test('failed or edited text never persists prior wire coordinates', async () => {
    const h = harness(); h.page.init(); h.page.text = 'invalid'; await h.page.submitText();
    assert.equal(h.storage.size, 0);
    const pending = deferred(); h.wire.setFromText = () => pending.promise;
    h.page.text = '51, -1'; const request = h.page.submitText(); await flush();
    h.page.text = 'different input'; pending.resolve({ lat: 51, lon: -1 }); await request;
    assert.equal(h.storage.size, 0);
    h.wire.setFromText = async () => ({ lat: 51.23456, lon: -1.23456 });
    await h.page.submitText();
    assert.equal(h.storage.get('observer_location'), '{"lat":51.23,"lon":-1.23}');
});

test('forget removes storage immediately and follows pending lookup even if a newer invalid submission arrives', async () => {
    const h = harness(), pending = deferred(); h.page.init();
    h.storage.set('observer_location', '{"lat":42,"lon":17}');
    h.wire.setFromText = text => { h.calls.push(['text', text]); return pending.promise; };
    h.page.text = '51, -1'; const first = h.page.submitText(); await flush();
    const forgotten = h.page.forget(); assert.equal(h.page.text, ''); await h.page.submitText();
    assert.equal(h.storage.size, 0); assert.deepEqual(h.calls, [['text', '51, -1']]);
    pending.resolve({ lat: 51, lon: -1 }); await first; await forgotten;
    assert.deepEqual(h.calls, [['text', '51, -1'], ['forget']]);
    assert.equal(h.storage.size, 0);
});

test('newer text wins over in-flight lookup and pending geolocation', async () => {
    const h = harness(), pending = deferred(); h.page.init(); h.page.locate();
    h.wire.setFromText = text => { h.calls.push(['text', text]); return text === 'first' ? pending.promise : Promise.resolve({ lat: 12, lon: 34 }); };
    h.page.text = 'first'; const first = h.page.submitText(); await flush();
    h.page.text = 'second'; const second = h.page.submitText();
    h.geo[0].success(position());
    assert.deepEqual(h.calls, [['text', 'first']]);
    pending.resolve({ lat: 51, lon: -1 }); await first; await second;
    assert.deepEqual(h.calls, [['text', 'first'], ['text', 'second']]);
    assert.equal(h.storage.get('observer_location'), '{"lat":12,"lon":34}');
});

test('destroy drops queued requests and late lookup results; a fresh component remains independent', async () => {
    const h = harness(), pending = deferred(); h.page.init();
    h.wire.setFromText = text => { h.calls.push(['text', text]); return pending.promise; };
    h.page.text = 'first'; const first = h.page.submitText(); await flush();
    h.page.text = 'second'; const second = h.page.submitText(); h.page.destroy();
    pending.resolve({ lat: 51, lon: -1 }); await first; await second;
    assert.equal(h.storage.size, 0); assert.deepEqual(h.calls, [['text', 'first']]);
    await h.page.submitText(); h.page.locate(); assert.equal(h.geo.length, 0);
});

test('failed RPCs and invalid geolocation never persist data and allow a later retry', async () => {
    const h = harness(); h.page.init(); h.page.locate();
    h.geo[0].success(position(Infinity, 0)); await flush();
    assert.equal(h.calls.length, 0); assert.equal(h.page.manual, true);
    h.wire.setFromText = async () => { throw new Error('offline'); };
    h.page.text = '51, -1'; await h.page.submitText();
    assert.equal(h.storage.size, 0); assert.match(h.page.geoError, /try again/); assert.equal(h.page.busy, false);
    h.wire.setFromText = async () => ({ lat: 0, lon: 0 }); await h.page.submitText();
    assert.equal(h.page.geoError, ''); assert.equal(h.storage.get('observer_location'), '{"lat":0,"lon":0}');
});

test('back-forward cache restoration permits a new explicit attempt without reviving old callbacks', async () => {
    const h = harness(); h.page.init(); h.page.locate();
    h.window.dispatchEvent(new Event('pagehide'));
    const restored = new Event('pageshow'); Object.defineProperty(restored, 'persisted', { value: true });
    h.window.dispatchEvent(restored); h.geo[0].success(position()); await flush();
    assert.equal(h.calls.length, 0);
    h.page.locate(); h.geo[1].success(position(12, 34)); await flush();
    assert.equal(h.storage.get('observer_location'), '{"lat":12,"lon":34}');
    h.page.destroy(); h.window.dispatchEvent(restored); h.page.locate();
    assert.equal(h.geo.length, 2, 'destroy removes restoration listener');
});

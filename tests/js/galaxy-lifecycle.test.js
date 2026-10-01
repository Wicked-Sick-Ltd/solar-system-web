import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mountGalaxyPage } from '../../resources/js/galaxy-loader.js';

function deferred() {
    let resolve, reject;
    const promise = new Promise((yes, no) => { resolve = yes; reject = no; });
    return { promise, resolve, reject };
}
class Element extends EventTarget {
    attributes = new Map();
    disabled = true;
    hidden = false;
    textContent = '';
    contains(target) { return target === this; }
    focus() { this.focused = true; }
    setAttribute(name, value) { this.attributes.set(name, value); }
}
function harness() {
    const nodes = new Map();
    const document = new EventTarget();
    document.body = new Element();
    document.documentElement = new Element();
    document.activeElement = document.body;
    const root = {
        ownerDocument: document,
        dataset: { dataUrl: '/galaxy/data' },
        querySelector(selector) {
            if (!nodes.has(selector)) nodes.set(selector, new Element());
            return nodes.get(selector);
        },
    };
    const imports = [], requests = [], mounts = [];
    let disposals = 0;
    const page = mountGalaxyPage(root, {
        loadRenderer() {
            const imported = deferred();
            imports.push(imported);
            return imported.promise;
        },
        fetchMap(url, options) {
            const request = { ...deferred(), url, options };
            requests.push(request);
            return request.promise;
        },
    });
    const module = {
        mountGalaxy(element, hosts, options) {
            mounts.push({ element, hosts, options });
            return () => { disposals++; };
        },
    };
    function ready(index = 0, hosts = [{ id: 'host' }]) {
        imports[index].resolve(module);
        requests[index].resolve({ ok: true, json: async () => ({ hosts }) });
    }
    return { page, document, nodes, imports, requests, mounts, ready, get disposals() { return disposals; } };
}

test('no renderer or catalogue request until explicit activation; duplicate clicks load once', async () => {
    const h = harness();
    assert.equal(h.nodes.get('[data-map-instruction]').hidden, false);
    assert.equal(h.imports.length, 0);
    assert.equal(h.requests.length, 0);
    h.nodes.get('[data-load-map]').dispatchEvent(new Event('click'));
    const completion = h.page.load();
    assert.equal(h.imports.length, 1);
    assert.equal(h.requests.length, 1);
    assert.equal(h.requests[0].options.headers.Accept, 'application/json');
    assert.equal(h.nodes.get('[data-load-map]').disabled, true);
    h.ready();
    await completion;
    assert.equal(h.mounts.length, 1);
    assert.equal(h.nodes.get('[data-load-map]').hidden, true);
    assert.equal(h.nodes.get('[data-map-instruction]').hidden, true);
    h.page.load();
    assert.equal(h.requests.length, 1);
    h.page.dispose();
    h.page.dispose();
    assert.equal(h.disposals, 1);
    assert.equal(h.nodes.get('[data-map-instruction]').hidden, false);
    assert.equal(h.nodes.get('[data-system]').disabled, true);
});

test('navigation aborts data fetch and a late dynamic import cannot create a renderer', async () => {
    const h = harness();
    const completion = h.page.load();
    h.page.dispose();
    assert.equal(h.requests[0].options.signal.aborted, true);
    h.ready();
    await completion;
    assert.equal(h.mounts.length, 0);
    h.nodes.get('[data-load-map]').dispatchEvent(new Event('click'));
    assert.equal(h.requests.length, 1, 'activation listener removed');
});

test('failure after navigation cannot alter stale DOM or a new page', async () => {
    const old = harness();
    const completion = old.page.load();
    old.page.dispose();
    const current = harness();
    const previousText = old.nodes.get('[data-map-status]').textContent;
    old.requests[0].reject(new Error('late failure'));
    old.imports[0].reject(new Error('late chunk failure'));
    await completion;
    assert.equal(old.nodes.get('[data-map-status]').textContent, previousText);
    assert.equal(current.requests.length, 0);
    assert.equal(current.nodes.get('[data-load-map]').disabled, false);
    current.page.dispose();
});

for (const failure of ['network', 'chunk', 'invalid-json', 'status', 'mount']) {
    test(`${failure} failure preserves accessible fallback and permits a retry`, async () => {
        const h = harness();
        const completion = h.page.load();
        if (failure === 'chunk') h.imports[0].reject(new Error('missing chunk'));
        else if (failure === 'mount') h.imports[0].resolve({ mountGalaxy() { throw new Error('GPU failure'); } });
        else h.imports[0].resolve({ mountGalaxy() { assert.fail('must not mount failed data'); } });
        if (failure === 'network') h.requests[0].reject(new Error('offline'));
        else h.requests[0].resolve({ ok: failure !== 'status', json: async () => failure === 'invalid-json' ? { hosts: null } : { hosts: [] } });
        await completion;
        assert.match(h.nodes.get('[data-map-status]').textContent, /accessible system directory/);
        assert.equal(h.requests[0].options.signal.aborted, true);
        assert.equal(h.nodes.get('[data-load-map]').disabled, false);
        assert.equal(h.nodes.get('[data-load-map]').hidden, false);
        assert.equal(h.nodes.get('[data-map-instruction]').hidden, false);
        const retry = h.page.load();
        h.ready(1);
        await retry;
        assert.equal(h.mounts.length, 1);
        assert.equal(h.nodes.get('[data-map-instruction]').hidden, true);
        h.page.dispose();
    });
}


test('keyboard activation continues focus into the map after disabling the load button', async () => {
    const h = harness();
    h.document.activeElement = h.nodes.get('[data-load-map]');
    const completion = h.page.load();
    h.document.activeElement = h.document.body;
    h.ready();
    await completion;
    assert.equal(h.mounts[0].options.focusOnReady, true);
    h.page.dispose();
});

for (const movement of ['focusin', 'pointerdown', 'keydown']) {
    test(`a visitor who moves elsewhere via ${movement} while loading keeps their focus`, async () => {
        const h = harness();
        h.document.activeElement = h.nodes.get('[data-load-map]');
        const completion = h.page.load();
        const elsewhere = new Element();
        const event = new Event(movement);
        Object.defineProperty(event, 'target', { value: elsewhere });
        h.document.dispatchEvent(event);
        h.document.activeElement = h.document.body;
        h.ready();
        await completion;
        assert.equal(h.mounts[0].options.focusOnReady, false);
        h.page.dispose();
    });
}

test('load failure restores the retry button only if the visitor is still waiting', async () => {
    for (const moveAway of [false, true]) {
        const h = harness();
        h.document.activeElement = h.nodes.get('[data-load-map]');
        const completion = h.page.load();
        h.document.activeElement = h.document.body;
        if (moveAway) h.document.dispatchEvent(new Event('keydown'));
        h.imports[0].reject(new Error('offline'));
        await completion;
        assert.equal(h.nodes.get('[data-load-map]').focused === true, !moveAway);
        h.page.dispose();
    }
});

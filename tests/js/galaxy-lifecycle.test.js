import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

// Exercise the entry point with a small DOM harness; no WebGL is needed to
// verify request scheduling or stale network failures during navigation.
const source = readFileSync(new URL('../../resources/js/galaxy.js', import.meta.url), 'utf8')
    .replace(/^import .*;\n/gm, '');

function harness(readyState) {
    const listeners = new Map();
    const nodes = new Map();
    const requests = [];
    const root = {
        dataset: { dataUrl: '/galaxy/data' },
        querySelector(selector) {
            if (!nodes.has(selector)) nodes.set(selector, { disabled: false, textContent: 'Loading' });
            return nodes.get(selector);
        },
    };
    runInNewContext(source, {
        document: {
            readyState,
            querySelector: () => root,
            addEventListener(name, listener) {
                if (!listeners.has(name)) listeners.set(name, []);
                listeners.get(name).push(listener);
            },
        },
        AbortController,
        fetch: () => new Promise((resolve, reject) => requests.push({ resolve, reject })),
    });
    return {
        requests, nodes,
        fire: name => Promise.all((listeners.get(name) ?? []).map(listener => listener())),
    };
}

for (const readyState of ['loading', 'complete']) {
    test(`one map request per navigation when document is ${readyState}`, async () => {
        const page = harness(readyState);
        await page.fire('DOMContentLoaded');
        const firstLoad = page.fire('livewire:navigated');
        assert.equal(page.requests.length, 1);
        page.requests[0].reject(new Error('Offline'));
        await firstLoad;
        assert.equal(page.nodes.get('[data-system]').disabled, true);

        await page.fire('livewire:navigating');
        const secondLoad = page.fire('livewire:navigated');
        assert.equal(page.requests.length, 2);
        page.requests[1].reject(new Error('Offline'));
        await secondLoad;
    });
}

test('failure from a previous navigation cannot disable the current map', async () => {
    const page = harness('loading');
    const firstLoad = page.fire('livewire:navigated');
    await page.fire('livewire:navigating');
    const secondLoad = page.fire('livewire:navigated');
    page.requests[0].reject(new Error('Previous request failed late'));
    await firstLoad;
    assert.equal(page.nodes.get('[data-system]').disabled, false);
    assert.equal(page.nodes.get('[data-map-status]').textContent, 'Loading');

    page.requests[1].reject(new Error('Current request failed'));
    await secondLoad;
    assert.equal(page.nodes.get('[data-system]').disabled, true);
});

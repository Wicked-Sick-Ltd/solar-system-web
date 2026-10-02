import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const source = readFileSync(new URL('../../resources/js/observing/shortlist.js', import.meta.url), 'utf8').replace(/^import .*;\n/gm, '');
test('shortlist binds one local site copier per mount, cancels it on navigation and refreshes after history restore', () => {
    const document = new EventTarget(), window = new EventTarget();
    let root = {}, mounts = 0, disposals = 0;
    document.readyState = 'complete';
    document.querySelector = selector => { assert.equal(selector, '[data-shortlist-form]'); return root; };
    runInNewContext(source, { document, window, fetch() { throw new Error('No automatic network request'); }, mountNightSites(form) {
        assert.equal(form, root); mounts++; return { dispose() { disposals++; } };
    } });
    assert.equal(mounts, 1);
    document.dispatchEvent(new Event('livewire:navigated')); assert.equal(mounts, 1);
    document.dispatchEvent(new Event('livewire:navigating')); assert.equal(disposals, 1);
    root = null; document.dispatchEvent(new Event('livewire:navigated')); assert.equal(mounts, 1);
    root = {}; document.dispatchEvent(new Event('livewire:navigated')); assert.equal(mounts, 2);
    window.dispatchEvent(new Event('pagehide')); assert.equal(disposals, 2);
    window.dispatchEvent(Object.assign(new Event('pageshow'), { persisted: true })); assert.equal(mounts, 3);
    window.dispatchEvent(new Event('pageshow')); assert.equal(mounts, 3);
});

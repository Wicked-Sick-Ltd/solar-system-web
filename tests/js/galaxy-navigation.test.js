import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const source = readFileSync(new URL('../../resources/js/galaxy.js', import.meta.url), 'utf8').replace(/^import .*;\n/gm, '');

test('Livewire navigation and browser history each bind one loader and dispose the previous page', () => {
    const document = new EventTarget();
    const window = new EventTarget();
    let hasRoot = true, mounts = 0, disposals = 0, navigations = 0;
    document.querySelector = () => hasRoot ? {} : null;
    document.addEventListener('livewire:navigated', () => { navigations++; });
    runInNewContext(source, { document, window, mountGalaxyPage() {
        mounts++;
        return { dispose() { disposals++; } };
    } });
    document.dispatchEvent(new Event('DOMContentLoaded'));
    assert.equal(mounts, 0);
    document.dispatchEvent(new Event('livewire:navigated'));
    assert.equal(mounts, 1);
    document.dispatchEvent(new Event('livewire:navigating'));
    assert.equal(disposals, 1);
    hasRoot = false;
    document.dispatchEvent(new Event('livewire:navigated'));
    assert.equal(mounts, 1);
    hasRoot = true;
    document.dispatchEvent(new Event('livewire:navigated'));
    assert.equal(mounts, 2);
    window.dispatchEvent(new Event('pagehide'));
    assert.equal(disposals, 2);
    const restored = new Event('pageshow');
    restored.persisted = true;
    window.dispatchEvent(restored);
    assert.equal(mounts, 3);
    assert.equal(navigations, 3, 'history restoration must not synthesize a global Livewire event');
    window.dispatchEvent(new Event('pageshow'));
    assert.equal(mounts, 3);
});

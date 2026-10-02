import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { mountNightSites } from '../../resources/js/observing/night-sites.js';
import { WORKSPACE_KEY } from '../../resources/js/observing/workspace-store.js';

function harness() {
    const workspace = JSON.parse(readFileSync(new URL('../fixtures/observing/workspace-v2.json', import.meta.url)));
    let saved = JSON.stringify(workspace);
    const elements = Object.fromEntries(['sites', 'site-choice', 'site-apply', 'site-status', 'site-error'].map(name => [name, {
        value: '', children: [], hidden: true, textContent: '', listeners: new Set(),
        append(option) { this.children.push(option); if (!this.value) this.value = option.value; },
        replaceChildren() { this.children = []; this.value = ''; },
        addEventListener(type, listener) { this.listeners.add(listener); }, removeEventListener(type, listener) { this.listeners.delete(listener); },
        click() { for (const listener of this.listeners) listener(); },
    }]));
    const storage = { getItem(key) { assert.equal(key, WORKSPACE_KEY); return saved; }, setItem() { throw new Error('No planning-form action may save a profile or activate a site'); } };
    const form = { elements: Object.fromEntries(['lat', 'lon', 'timezone', 'min_altitude_deg', 'horizon'].map(name => [name, { value: 'existing input' }])),
        ownerDocument: { defaultView: { localStorage: storage }, createElement() { return { value: '', textContent: '' }; } },
        querySelector(selector) { return elements[selector.slice('[data-night-'.length, -1)]; } };
    return { form, elements, storage, workspace, setSaved(value) { saved = value; } };
}

test('saved sites require explicit copy and do not activate or persist coordinates', () => {
    const h = harness(), mounted = mountNightSites(h.form, h.storage);
    assert.equal(h.form.elements.lat.value, 'existing input');
    h.elements['site-apply'].click();
    assert.equal(h.form.elements.lat.value, String(h.workspace.sites[0].latitude));
    assert.equal(h.form.elements.timezone.value, h.workspace.sites[0].timezone);
    assert.match(h.elements['site-status'].textContent, /nothing was saved or activated/);
    mounted.dispose(); assert.equal(h.elements['site-apply'].listeners.size, 0);
});
test('bfcache remount rebuilds the list instead of duplicating options', () => {
    const h = harness(); mountNightSites(h.form, h.storage).dispose();
    mountNightSites(h.form, h.storage);
    assert.equal(h.elements['site-choice'].children.length, h.workspace.sites.length);
    assert.equal(h.elements['site-apply'].listeners.size, 1);
});
test('deleted sites and malformed storage preserve manual form input and report a usable fallback', () => {
    const h = harness(); mountNightSites(h.form, h.storage);
    h.setSaved(JSON.stringify({ ...h.workspace, sites: [], activeSiteId: null })); h.elements['site-apply'].click();
    assert.equal(h.form.elements.lat.value, 'existing input'); assert.match(h.elements['site-error'].textContent, /no longer saved/);
    h.setSaved('{bad json'); h.elements['site-apply'].click(); assert.match(h.elements['site-error'].textContent, /manually/);
});

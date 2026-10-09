import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const view = readFileSync(new URL('../../resources/views/components/cookie-banner.blade.php', import.meta.url), 'utf8');
const script = view.slice(view.indexOf('<script>') + 8, view.indexOf('</script>'));

function harness({ cookie = '', href = 'https://publicuniverse.test/objects/mars', metaLocation = 'https://publicuniverse.test/from-meta' } = {}) {
    const calls = [];
    const scripts = [];
    let cookieStore = cookie;
    const loc = new URL(href);
    const locationBag = {
        get href() { return loc.href; },
        set href(v) { Object.assign(loc, new URL(v, loc.href)); },
        get protocol() { return loc.protocol; },
        get host() { return loc.host; },
        get hostname() { return loc.hostname; },
        get port() { return loc.port; },
        get pathname() { return loc.pathname; },
        get search() { return loc.search; },
        get hash() { return loc.hash; },
        set hash(v) { loc.hash = v; },
        get origin() { return loc.origin; },
        toString() { return loc.href; },
    };
    const document = {
        head: { appendChild(node) { scripts.push(node); } },
        listeners: {},
        addEventListener(name, fn) { (this.listeners[name] ??= []).push(fn); },
        createElement() { return { async: false, src: '' }; },
        querySelector(selector) {
            if (selector === 'meta[name="ga-measurement-id"]') {
                return { getAttribute: () => 'G-TEST1234' };
            }
            if (selector === 'meta[name="ga-page-location"]') {
                return { getAttribute: () => metaLocation };
            }
            return null;
        },
    };
    Object.defineProperty(document, 'cookie', {
        get: () => cookieStore,
        set: (v) => { cookieStore = v; },
        configurable: true,
    });

    const window = {
        dataLayer: [],
        __gaLoaded: false,
        __gaNavigateBound: false,
        gtag() { calls.push(Array.from(arguments)); this.dataLayer.push(arguments); },
    };

    const ctx = { window, document, location: locationBag, URL, encodeURIComponent, decodeURIComponent };
    runInNewContext(script + '\nthis.factory = cookieConsent;', ctx);

    return {
        create: () => ctx.factory(),
        window,
        document,
        calls,
        scripts,
        get cookie() { return cookieStore; },
        navigate(url) {
            loc.href = new URL(url, loc.href).href;
            for (const fn of document.listeners['livewire:navigated'] ?? []) fn();
        },
    };
}

test('accepting analytics grants Consent Mode storage and injects gtag.js once', () => {
    const h = harness();
    const banner = h.create();
    banner.init();
    assert.equal(banner.open, true);
    assert.equal(h.scripts.length, 0);

    banner.choose('all');
    assert.equal(banner.open, false);
    assert.match(h.cookie, /cookie_consent=all/);
    assert.equal(h.window.__gaLoaded, true);
    assert.equal(h.scripts.length, 1);
    assert.match(h.scripts[0].src, /googletagmanager\.com\/gtag\/js\?id=G-TEST1234/);

    const consentUpdate = h.calls.find((c) => c[0] === 'consent' && c[1] === 'update');
    assert.ok(consentUpdate);
    assert.equal(consentUpdate[2].analytics_storage, 'granted');

    const config = h.calls.find((c) => c[0] === 'config');
    assert.equal(config[1], 'G-TEST1234');
    assert.equal(config[2].anonymize_ip, true);
    // Prefer the live address bar (meta is stale after wire:navigate).
    assert.equal(config[2].page_location, 'https://publicuniverse.test/objects/mars');

    banner.choose('all');
    assert.equal(h.scripts.length, 1);
});

test('accepting analytics after wire:navigate configs the current URL, not the stale meta', () => {
    const h = harness({
        href: 'https://publicuniverse.test/',
        metaLocation: 'https://publicuniverse.test/',
    });
    const banner = h.create();
    banner.init();
    assert.equal(banner.open, true);

    // Catalogue pagination before consent: address bar moved, meta did not.
    h.navigate('https://publicuniverse.test/catalogue?page=2');
    h.calls.length = 0;

    banner.choose('all');
    const config = h.calls.find((c) => c[0] === 'config');
    assert.ok(config);
    assert.equal(config[2].page_location, 'https://publicuniverse.test/catalogue?page=2');
});

test('prior consent does not double-count the landing page when livewire:navigated fires on boot', () => {
    const h = harness({
        cookie: 'cookie_consent=all',
        href: 'https://publicuniverse.test/objects/mars',
        metaLocation: 'https://publicuniverse.test/objects/mars',
    });
    const banner = h.create();
    banner.init();

    // Livewire emits navigated on initial load as well as after wire:navigate.
    for (const fn of h.document.listeners['livewire:navigated'] ?? []) fn();

    const pageViews = h.calls.filter((c) => c[0] === 'event' && c[1] === 'page_view');
    const configs = h.calls.filter((c) => c[0] === 'config');
    assert.equal(configs.length, 1);
    assert.equal(pageViews.length, 0, 'config already sent the landing page_view');

    h.navigate('https://publicuniverse.test/objects/planet-saturn');
    assert.equal(h.calls.filter((c) => c[0] === 'event' && c[1] === 'page_view').length, 1);
});

test('essential-only never loads Google scripts', () => {
    const h = harness();
    const banner = h.create();
    banner.choose('essential');
    assert.match(h.cookie, /cookie_consent=essential/);
    assert.equal(h.window.__gaLoaded, false);
    assert.equal(h.scripts.length, 0);
    assert.equal(h.calls.length, 0);
});

test('prior all consent loads analytics on init without showing the banner', () => {
    const h = harness({ cookie: 'cookie_consent=all' });
    const banner = h.create();
    banner.init();
    assert.equal(banner.open, false);
    assert.equal(h.window.__gaLoaded, true);
    assert.equal(h.scripts.length, 1);
});

test('livewire:navigated sends a redacted page_view after analytics has loaded', () => {
    const h = harness({ cookie: 'cookie_consent=all', href: 'https://publicuniverse.test/' });
    const banner = h.create();
    banner.init();
    h.calls.length = 0;

    h.navigate('https://publicuniverse.test/objects/planet-saturn?s=SECRETTOKEN#s=fragment');
    assert.equal(h.calls.length, 1);
    assert.equal(h.calls[0][0], 'event');
    assert.equal(h.calls[0][1], 'page_view');
    assert.equal(h.calls[0][2].send_to, 'G-TEST1234');
    assert.equal(h.calls[0][2].page_location, 'https://publicuniverse.test/objects/planet-saturn?s=redacted');
    assert.equal(h.calls[0][2].page_path, '/objects/planet-saturn?s=redacted');
    assert.doesNotMatch(h.calls[0][2].page_location, /SECRETTOKEN|fragment/);
});

test('livewire:navigated does nothing before analytics consent', () => {
    const h = harness();
    const banner = h.create();
    banner.init();
    for (const fn of h.document.listeners['livewire:navigated'] ?? []) fn();
    assert.equal(h.calls.length, 0);
    assert.equal(h.document.listeners['livewire:navigated'], undefined);
});

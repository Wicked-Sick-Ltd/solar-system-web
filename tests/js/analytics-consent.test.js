import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const source = readFileSync(new URL('../../resources/js/analytics-consent.js', import.meta.url), 'utf8');

function boot(id = 'G-TEST1234', gtm = null, referrer = '', consent = null, scriptAlreadyPresent = false, cookies = null, meta = {}) {
    const state = {
        id,
        gtm,
        page: 'https://publicuniverse.net/objects/mars',
        reloads: 0,
    };
    const scripts = [];
    const jar = new Map();
    const storage = new Map();
    const localStorage = {
        getItem(key) { return storage.has(key) ? storage.get(key) : null; },
        setItem(key, value) { storage.set(key, String(value)); },
        removeItem(key) { storage.delete(key); },
    };
    const document = new EventTarget();
    const docListeners = [];
    const addDocumentListener = document.addEventListener.bind(document);
    document.addEventListener = (type, fn, options) => {
        docListeners.push({ type, fn });
        return addDocumentListener(type, fn, options);
    };
    document.title = 'Mars';
    document.head = { appendChild(node) { scripts.push(node); } };
    document.createElement = () => {
        const node = { async: false, src: '' };
        node.setAttribute = (name, value) => { node[name] = value; };
        return node;
    };
    document.getElementById = () => null;
    document.querySelector = (selector) => {
        if (selector === 'meta[name="ga-measurement-id"]') return state.id ? { getAttribute: () => state.id } : null;
        if (selector === 'meta[name="gtm-container-id"]') return state.gtm ? { getAttribute: () => state.gtm } : null;
        if (selector === 'meta[name="ga-page-location"]') return { getAttribute: () => state.page };
        const named = /^meta\[name="([^"]+)"\]$/.exec(selector);
        if (named && Object.prototype.hasOwnProperty.call(meta, named[1])) return { getAttribute: () => meta[named[1]] };
        return null;
    };
    Object.defineProperty(document, 'cookie', {
        get() {
            return [...jar.entries()].map(([name, value]) => `${name}=${value}`).join('; ');
        },
        set(raw) {
            const pair = raw.split(';')[0];
            const eq = pair.indexOf('=');
            const name = pair.slice(0, eq).trim();
            if (/Max-Age=0/.test(raw)) jar.delete(name);
            else jar.set(name, pair.slice(eq + 1));
        },
    });
    document.referrer = referrer;
    const historyListeners = [];
    const history = {
        pushState() {},
        replaceState() {},
    };
    const location = {
        protocol: 'https:',
        hostname: 'publicuniverse.net',
        origin: 'https://publicuniverse.net',
        pathname: '/objects/mars',
        href: 'https://publicuniverse.net/objects/mars?s=SECRET#s=SECRET',
        reload() { state.reloads += 1; },
    };
    const window = {
        document,
        location,
        history,
        addEventListener(type, fn) { historyListeners.push({ type, fn }); },
        localStorage,
    };
    if (cookies) {
        Object.entries(cookies).forEach(([name, value]) => jar.set(name, value));
    }
    if (consent) localStorage.setItem('pu_cookie_consent', consent);
    if (scriptAlreadyPresent) window.__puGtagScriptTag = true;
    runInNewContext(source, { window, document, location, URL });
    const commands = () => window.dataLayer.map((entry) => Array.from(entry));
    const pageViews = () => commands().filter((entry) => entry[0] === 'event' && entry[1] === 'page_view');
    const events = (name) => commands().filter((entry) => entry[0] === 'event' && entry[1] === name);
    const click = (attrs, type = 'click', button = 0) => {
        const anchor = {
            tagName: 'A',
            parentNode: null,
            getAttribute: (key) => (Object.prototype.hasOwnProperty.call(attrs, key) ? attrs[key] : null),
            hasAttribute: (key) => Object.prototype.hasOwnProperty.call(attrs, key),
        };
        const child = { tagName: 'SPAN', parentNode: anchor };
        docListeners.filter((entry) => entry.type === type).forEach((entry) => entry.fn({ target: child, type, button }));
    };
    const emit = (detail) => {
        historyListeners.filter((entry) => entry.type === 'pu-analytics').forEach((entry) => entry.fn({ detail }));
    };
    return { state, scripts, jar, storage, document, window, history, historyListeners, commands, pageViews, events, click, emit };
}

test('container initialization publishes the redacted page location and disables automatic page views', () => {
    const { state, document, window, commands, pageViews } = boot(null, 'GTM-M8THNDPH');
    const sets = () => commands().filter((entry) => entry[0] === 'set' && entry[1] && typeof entry[1] === 'object' && entry[1].page_location);
    const plain = () => window.dataLayer.filter((entry) => entry && entry.page_location && entry[0] === undefined);

    assert.equal(sets().length, 1);
    assert.equal(sets()[0][1].page_location, state.page);
    assert.equal(sets()[0][1].send_page_view, false);
    assert.equal(plain().length, 1);
    assert.equal(plain()[0].page_location, 'https://publicuniverse.net/objects/mars');
    assert.equal(plain()[0].page_referrer, '');
    assert.equal(sets()[0][1].page_referrer, '');
    assert.equal(JSON.stringify(sets()).includes('SECRET'), false);
    assert.equal(plain()[0].page_location.includes('SECRET'), false);
    assert.equal(window.__gaLoaded, true);
    assert.equal(pageViews().length, 1);

    const prior = state.page;
    state.page = 'https://publicuniverse.net/observe/night?lat=redacted&lon=redacted';
    document.dispatchEvent(new Event('livewire:navigated'));
    assert.equal(sets().at(-1)[1].page_location, state.page);
    assert.equal(sets().at(-1)[1].send_page_view, false);
    assert.equal(sets().at(-1)[1].page_referrer, prior);
    assert.equal(plain().at(-1).page_location, state.page);
    assert.equal(plain().at(-1).page_referrer, prior);
    assert.equal(pageViews().length, 2);
    assert.equal(pageViews().at(-1)[2].page_location, state.page);
    assert.equal(JSON.stringify(sets()).includes('12.34567'), false);
});

test('container initialization redacts coordinates in a same-origin page referrer', () => {
    const referrer = 'https://publicuniverse.test/api?p[lat]=51.5&p[lon]=-0.1';
    const { document, window, commands, pageViews } = boot(null, 'GTM-M8THNDPH', referrer);
    const plain = () => window.dataLayer.filter((entry) => entry && Object.prototype.hasOwnProperty.call(entry, 'page_referrer') && entry[0] === undefined);
    const sets = () => commands().filter((entry) => entry[0] === 'set' && entry[1] && typeof entry[1] === 'object' && Object.prototype.hasOwnProperty.call(entry[1], 'page_referrer'));

    assert.equal(plain().length, 1);
    const published = plain()[0].page_referrer;
    assert.equal(published.includes('51.5'), false);
    assert.equal(published.includes('-0.1'), false);
    assert.match(decodeURIComponent(published), /p\[lat\]=redacted/);
    assert.match(decodeURIComponent(published), /p\[lon\]=redacted/);
    assert.equal(published.startsWith('https://publicuniverse.test/api?'), true);
    assert.equal(sets()[0][1].page_referrer, published);
    assert.equal(JSON.stringify(window.dataLayer).includes('51.5'), false);
    assert.equal(JSON.stringify(window.dataLayer).includes('-0.1'), false);
    assert.equal(pageViews().length, 1);

    document.dispatchEvent(new Event('livewire:navigated'));
    assert.equal(plain().at(-1).page_referrer, published);
    assert.equal(sets().at(-1)[1].page_referrer, published);
    assert.equal(JSON.stringify(window.dataLayer).includes('51.5'), false);

    window.publicUniverseAnalytics.choose('analytics');
    assert.equal(pageViews().length, 1);
    assert.equal(pageViews()[0][2].page_referrer, published);
    assert.equal(JSON.stringify(pageViews()).includes('51.5'), false);
    assert.equal(JSON.stringify(pageViews()).includes('-0.1'), false);

    const located = boot(null, 'GTM-M8THNDPH', 'https://publicuniverse.net/observe/night?lat=12.34567&lon=1.23456&targets=moon');
    const locatedReferrer = located.window.dataLayer.find((entry) => entry && entry.page_referrer && entry[0] === undefined).page_referrer;
    assert.equal(decodeURIComponent(locatedReferrer).includes('lat=redacted'), true);
    assert.equal(decodeURIComponent(locatedReferrer).includes('lon=redacted'), true);
    assert.equal(decodeURIComponent(locatedReferrer).includes('targets=moon'), true);
    assert.equal(locatedReferrer.includes('12.34567'), false);
    assert.equal(locatedReferrer.includes('1.23456'), false);

    const shared = boot(null, 'GTM-M8THNDPH', 'https://publicuniverse.net/settings?s=SECRET');
    const sharedReferrer = shared.window.dataLayer.find((entry) => entry && entry.page_referrer && entry[0] === undefined).page_referrer;
    assert.equal(decodeURIComponent(sharedReferrer).includes('s=redacted'), true);
    assert.equal(sharedReferrer.includes('SECRET'), false);

    const empty = boot(null, 'GTM-M8THNDPH', '');
    const emptyReferrer = empty.window.dataLayer.find((entry) => entry && Object.prototype.hasOwnProperty.call(entry, 'page_referrer') && entry[0] === undefined);
    assert.equal(emptyReferrer.page_referrer, '');

    const broken = boot(null, 'GTM-M8THNDPH', 'not a url');
    const brokenReferrer = broken.window.dataLayer.find((entry) => entry && Object.prototype.hasOwnProperty.call(entry, 'page_referrer') && entry[0] === undefined);
    assert.equal(brokenReferrer.page_referrer, '');
});

test('container initialization redacts bracketed descendants of sensitive query parameters', () => {
    const referrer = 'https://publicuniverse.test/api?lat[]=51.5&lon[]=9.87654&p[lat][]=51.5&p[lon][]=1.23456&s[]=PRIVATE_SETTINGS&targets=moon';
    const { window } = boot(null, 'GTM-M8THNDPH', referrer);
    const published = window.dataLayer.find((entry) => entry && entry.page_referrer && entry[0] === undefined).page_referrer;

    assert.equal(published, 'https://publicuniverse.test/api?lat=redacted&lon=redacted&p%5Blat%5D=redacted&p%5Blon%5D=redacted&s=redacted&targets=moon');
    assert.equal(published.includes('51.5'), false);
    assert.equal(published.includes('9.87654'), false);
    assert.equal(published.includes('1.23456'), false);
    assert.equal(published.includes('PRIVATE_SETTINGS'), false);
    assert.equal(JSON.stringify(window.dataLayer).includes('51.5'), false);
    assert.equal(JSON.stringify(window.dataLayer).includes('PRIVATE_SETTINGS'), false);

    const nestedReferrer = 'https://publicuniverse.test/api?p[][lat]=51.5&p[][lon]=1.23456&p[0][date]=2026-10-10&p[latitude]=9&latitude=kept';
    const nested = boot(null, 'GTM-M8THNDPH', nestedReferrer);
    const nestedPublished = nested.window.dataLayer.find((entry) => entry && entry.page_referrer && entry[0] === undefined).page_referrer;

    assert.equal(nestedPublished, 'https://publicuniverse.test/api?p%5B0%5D%5Blat%5D=redacted&p%5B0%5D%5Bdate%5D=2026-10-10&p%5B1%5D%5Blon%5D=redacted&p%5Blatitude%5D=9&latitude=kept');
    assert.equal(nestedPublished.includes('51.5'), false);
    assert.equal(nestedPublished.includes('1.23456'), false);
    assert.equal(JSON.stringify(nested.window.dataLayer).includes('51.5'), false);
    assert.equal(JSON.stringify(nested.window.dataLayer).includes('1.23456'), false);
});

test('trailing text after a bracketed query key is ignored and redacted like PHP', () => {
    const referrer = 'https://publicuniverse.test/api?p[lat]extra=51.5&p[lon]extra=-0.1&lat[0]extra=51.5';
    const expected = 'https://publicuniverse.test/api?p%5Blat%5D=redacted&p%5Blon%5D=redacted&lat=redacted';
    const { window, history } = boot(null, 'GTM-M8THNDPH', referrer);
    const plain = () => window.dataLayer.filter((entry) => entry && entry.page_location && entry[0] === undefined);
    const published = window.dataLayer.find((entry) => entry && entry.page_referrer && entry[0] === undefined).page_referrer;

    assert.equal(published, expected);
    assert.equal(published.includes('51.5'), false);
    assert.equal(published.includes('-0.1'), false);
    assert.equal(JSON.stringify(window.dataLayer).includes('51.5'), false);
    assert.equal(JSON.stringify(window.dataLayer).includes('-0.1'), false);

    history.pushState({}, '', 'https://publicuniverse.net/privacy?p[lat]extra=51.5&p[lon]extra=-0.1&lat[0]extra=51.5');
    assert.equal(plain().at(-1).page_location, 'https://publicuniverse.net/privacy?p%5Blat%5D=redacted&p%5Blon%5D=redacted&lat=redacted');
    assert.equal(plain().at(-1).page_location.includes('51.5'), false);
    assert.equal(plain().at(-1).page_location.includes('-0.1'), false);
    assert.equal(JSON.stringify(window.dataLayer).includes('extra'), false);
});

test('back to the homepage keeps the previous page as page_referrer', () => {
    const { state, document, window, history, historyListeners, pageViews } = boot(null, 'GTM-M8THNDPH');
    const plain = () => window.dataLayer.filter((entry) => entry && entry.page_location && entry[0] === undefined);
    const mars = 'https://publicuniverse.test/objects/mars';
    const home = 'https://publicuniverse.test';

    state.page = mars;
    document.dispatchEvent(new Event('livewire:navigated'));
    window.publicUniverseAnalytics.choose('analytics');
    const viewsOnMars = pageViews().length;
    assert.equal(pageViews().at(-1)[2].page_location, mars);

    window.location.href = 'https://publicuniverse.test/';
    const pop = historyListeners.find((entry) => entry.type === 'popstate');
    assert.ok(pop);
    pop.fn();
    assert.equal(plain().at(-1).page_location, home);
    assert.equal(plain().at(-1).page_referrer, mars);

    state.page = home;
    document.dispatchEvent(new Event('livewire:navigated'));
    assert.equal(plain().at(-1).page_location, home);
    assert.equal(plain().at(-1).page_referrer, mars);
    assert.equal(pageViews().length, viewsOnMars + 1);
    assert.equal(pageViews().at(-1)[2].page_location, home);
    assert.equal(pageViews().at(-1)[2].page_referrer, mars);
    assert.equal(pageViews().at(-1)[2].page_referrer.endsWith('/'), false);

    history.pushState({}, '', 'https://publicuniverse.test/objects/mars/?lat=12.34567');
    assert.equal(plain().at(-1).page_location, 'https://publicuniverse.test/objects/mars?lat=redacted');
    assert.equal(plain().at(-1).page_location.includes('12.34567'), false);
    assert.equal(pageViews().length, viewsOnMars + 1);
});

test('Livewire navigation uses the previous redacted page as page_referrer', () => {
    const { state, document, window, commands, pageViews } = boot(null, 'GTM-M8THNDPH', 'https://publicuniverse.test/api?p[lat]=51.5&p[lon]=-0.1');
    const plain = () => window.dataLayer.filter((entry) => entry && Object.prototype.hasOwnProperty.call(entry, 'page_referrer') && entry[0] === undefined);
    const sets = () => commands().filter((entry) => entry[0] === 'set' && entry[1] && typeof entry[1] === 'object' && Object.prototype.hasOwnProperty.call(entry[1], 'page_referrer'));
    const prior = state.page;

    assert.match(decodeURIComponent(plain()[0].page_referrer), /p\[lat\]=redacted/);
    assert.equal(plain()[0].page_referrer.includes('51.5'), false);

    document.dispatchEvent(new Event('livewire:navigated'));
    assert.equal(plain().at(-1).page_referrer, plain()[0].page_referrer);

    state.page = 'https://publicuniverse.net/privacy';
    document.dispatchEvent(new Event('livewire:navigated'));
    assert.equal(plain().at(-1).page_location, state.page);
    assert.equal(plain().at(-1).page_referrer, prior);
    assert.equal(sets().at(-1)[1].page_referrer, prior);
    assert.equal(plain().at(-1).page_referrer.includes('51.5'), false);

    window.publicUniverseAnalytics.choose('analytics');
    assert.equal(pageViews().length, 2);
    assert.equal(pageViews()[1][2].page_location, state.page);
    assert.equal(pageViews()[1][2].page_referrer, prior);

    const next = 'https://publicuniverse.net/about';
    state.page = next;
    document.dispatchEvent(new Event('livewire:navigated'));
    assert.equal(plain().at(-1).page_referrer, 'https://publicuniverse.net/privacy');
    assert.equal(pageViews().at(-1)[2].page_referrer, 'https://publicuniverse.net/privacy');
    assert.equal(pageViews().at(-1)[2].page_location, next);
});

test('navigate, pushState, and navigated keep the origin as page_referrer for a bracketed destination', () => {
    const { state, document, window, history, pageViews } = boot(null, 'GTM-M8THNDPH');
    const plain = () => window.dataLayer.filter((entry) => entry && entry.page_location && entry[0] === undefined);
    const origin = state.page;
    const destination = 'https://publicuniverse.net/privacy?lat[]=51.5';
    const phpDestination = 'https://publicuniverse.net/privacy?lat=redacted';

    window.publicUniverseAnalytics.choose('analytics');
    assert.equal(pageViews().length, 1);
    assert.equal(pageViews()[0][2].page_location, origin);

    document.dispatchEvent(new CustomEvent('livewire:navigate', { detail: { url: destination } }));
    history.pushState({}, '', destination);
    state.page = phpDestination;
    document.dispatchEvent(new Event('livewire:navigated'));

    const published = plain().filter((entry) => entry.page_location === phpDestination);
    assert.equal(published.length, 3);
    published.forEach((entry) => {
        assert.equal(entry.page_referrer, origin);
    });
    assert.equal(plain().at(-1).page_location, phpDestination);
    assert.equal(pageViews().length, 2);
    assert.equal(pageViews().at(-1)[2].page_location, phpDestination);
    assert.equal(pageViews().at(-1)[2].page_referrer, origin);
    assert.equal(JSON.stringify(window.dataLayer).includes('51.5'), false);
});

test('successive history wrappers keep their own delegates and publish once', () => {
    const { window, history } = boot(null, 'GTM-M8THNDPH');
    const plain = () => window.dataLayer.filter((entry) => entry && entry.page_location && entry[0] === undefined);
    const pushOrder = [];
    const firstPush = history.pushState;
    history.pushState = function () {
        pushOrder.push('push-first');
        return firstPush.apply(this, arguments);
    };
    const secondPush = history.pushState;
    history.pushState = function () {
        pushOrder.push('push-second');
        return secondPush.apply(this, arguments);
    };
    const beforePush = plain().length;
    history.pushState({}, '', 'https://publicuniverse.net/privacy?lat[]=51.5');
    assert.deepEqual(pushOrder, ['push-second', 'push-first']);
    assert.equal(plain().length, beforePush + 1);
    assert.equal(plain().at(-1).page_location, 'https://publicuniverse.net/privacy?lat=redacted');
    assert.equal(plain().at(-1).page_location.includes('51.5'), false);

    const replaceOrder = [];
    const firstReplace = history.replaceState;
    history.replaceState = function () {
        replaceOrder.push('replace-first');
        return firstReplace.apply(this, arguments);
    };
    const secondReplace = history.replaceState;
    history.replaceState = function () {
        replaceOrder.push('replace-second');
        return secondReplace.apply(this, arguments);
    };
    const beforeReplace = plain().length;
    history.replaceState({}, '', 'https://publicuniverse.net/about?lon[]=1.2');
    assert.deepEqual(replaceOrder, ['replace-second', 'replace-first']);
    assert.equal(plain().length, beforeReplace + 1);
    assert.equal(plain().at(-1).page_location, 'https://publicuniverse.net/about?lon=redacted');
    assert.equal(plain().at(-1).page_location.includes('1.2'), false);
    assert.equal(JSON.stringify(window.dataLayer).includes('51.5'), false);
});

test('history changes publish the redacted page before later listeners and do not page-view before consent', () => {
    const { state, document, window, history, historyListeners, pageViews } = boot(null, 'GTM-M8THNDPH');
    const plain = () => window.dataLayer.filter((entry) => entry && entry.page_location && entry[0] === undefined);
    const prior = state.page;
    const sensitive = 'https://publicuniverse.net/privacy?lat=51.5&lon=-0.1&s=SECRET';
    const seen = [];
    const previous = history.pushState;
    history.pushState = function () {
        const latest = plain().at(-1);
        seen.push(latest ? latest.page_location : '');
        return previous.apply(this, arguments);
    };

    history.pushState({}, '', sensitive);
    const published = plain().at(-1).page_location;
    assert.equal(seen[0], published);
    assert.equal(decodeURIComponent(published).includes('lat=redacted'), true);
    assert.equal(decodeURIComponent(published).includes('lon=redacted'), true);
    assert.equal(decodeURIComponent(published).includes('s=redacted'), true);
    assert.equal(published.includes('51.5'), false);
    assert.equal(published.includes('SECRET'), false);
    assert.equal(plain().at(-1).page_referrer, prior);
    assert.equal(pageViews().length, 1);
    assert.equal(JSON.stringify(window.dataLayer).includes('51.5'), false);
    assert.equal(JSON.stringify(window.dataLayer).includes('SECRET'), false);

    history.replaceState({}, '', '/observe/night?lat=12.34567');
    assert.equal(plain().at(-1).page_location.includes('12.34567'), false);
    assert.equal(decodeURIComponent(plain().at(-1).page_location).includes('lat=redacted'), true);
    assert.equal(pageViews().length, 1);

    window.location.href = 'https://publicuniverse.net/catalogue?lon=1.23456&s=TOKEN';
    const pop = historyListeners.find((entry) => entry.type === 'popstate');
    assert.ok(pop);
    pop.fn();
    assert.equal(plain().at(-1).page_location.includes('1.23456'), false);
    assert.equal(plain().at(-1).page_location.includes('TOKEN'), false);
    assert.equal(decodeURIComponent(plain().at(-1).page_location).includes('lon=redacted'), true);
    assert.equal(pageViews().length, 1);

    document.dispatchEvent(new CustomEvent('livewire:navigate', { detail: { url: 'https://publicuniverse.net/api?p[lat]=51.5&p[lon]=-0.1' } }));
    assert.equal(plain().at(-1).page_location.includes('51.5'), false);
    assert.match(decodeURIComponent(plain().at(-1).page_location), /p\[lat\]=redacted/);
    assert.equal(pageViews().length, 1);

    window.publicUniverseAnalytics.choose('analytics');
    const consentedViews = pageViews().length;
    assert.equal(consentedViews, 1);
    assert.equal(pageViews()[0][2].page_location.includes('51.5'), false);
    assert.equal(pageViews()[0][2].page_location.includes('SECRET'), false);

    history.pushState({}, '', 'https://publicuniverse.net/about?lat=9.876');
    assert.equal(pageViews().length, consentedViews);
    assert.equal(plain().at(-1).page_location.includes('9.876'), false);

    state.page = 'https://publicuniverse.net/about?lat=redacted';
    document.dispatchEvent(new Event('livewire:navigated'));
    assert.equal(pageViews().length, consentedViews + 1);
    assert.equal(pageViews().at(-1)[2].page_location, state.page);
    assert.equal(pageViews().at(-1)[2].page_location.includes('9.876'), false);
    assert.equal(JSON.stringify(pageViews()).includes('51.5'), false);
    assert.equal(JSON.stringify(pageViews()).includes('SECRET'), false);
});

test('Consent Mode v2 grants analytics storage by default and still loads gtag and sends a page view', () => {
    const { scripts, commands, pageViews } = boot();
    const queued = commands();
    const consent = queued.find((entry) => entry[0] === 'consent' && entry[1] === 'default');
    const config = queued.find((entry) => entry[0] === 'config');
    const redaction = queued.find((entry) => entry[0] === 'set' && entry[1] === 'ads_data_redaction');
    assert.ok(consent);
    assert.equal(consent[2].analytics_storage, 'granted');
    assert.equal(consent[2].ad_storage, 'denied');
    assert.equal(consent[2].ad_user_data, 'denied');
    assert.equal(consent[2].ad_personalization, 'denied');
    assert.equal(queued.some((entry) => entry[0] === 'set' && entry[1] === 'url_passthrough'), false);
    assert.equal(redaction[2], true);
    assert.ok(config);
    assert.equal(queued.indexOf(consent) < queued.indexOf(config), true);
    assert.equal(scripts.length, 1);
    assert.equal(scripts[0].async, true);
    assert.equal(scripts[0]['data-cfasync'], 'false');
    assert.equal(scripts[0].src, 'https://www.googletagmanager.com/gtag/js?id=G-TEST1234');
    assert.equal(pageViews().length, 1);
    assert.equal(pageViews()[0][2].page_location, 'https://publicuniverse.net/objects/mars');
});

test('an existing accept grants storage before config and does not inject a second gtag tag', () => {
    const { scripts, commands, pageViews, window } = boot('G-TEST1234', null, '', 'analytics', true);
    const queued = commands();
    const consent = queued.find((entry) => entry[0] === 'consent' && entry[1] === 'default');
    const config = queued.find((entry) => entry[0] === 'config');
    assert.equal(consent[2].analytics_storage, 'granted');
    assert.equal(queued.some((entry) => entry[0] === 'set' && entry[1] === 'url_passthrough'), false);
    assert.equal(queued.indexOf(consent) < queued.indexOf(config), true);
    assert.equal(scripts.length, 0);
    assert.equal(pageViews().length, 1);
    assert.equal(queued.filter((entry) => entry[0] === 'consent' && entry[1] === 'update').length, 0);

    window.publicUniverseAnalytics.load();
    assert.equal(pageViews().length, 1);
    assert.equal(commands().filter((entry) => entry[0] === 'consent' && entry[1] === 'update').length, 0);
});

test('essential only keeps storage denied while gtag is already loaded', () => {
    const { scripts, commands, pageViews } = boot('G-TEST1234', null, '', 'essential');
    const queued = commands();
    const consent = queued.find((entry) => entry[0] === 'consent' && entry[1] === 'default');
    const passthrough = queued.find((entry) => entry[0] === 'set' && entry[1] === 'url_passthrough');
    assert.equal(consent[2].analytics_storage, 'denied');
    assert.equal(passthrough[2], true);
    assert.equal(scripts.length, 1);
    assert.equal(pageViews().length, 0);
    assert.equal(commands().some((entry) => entry[0] === 'consent' && entry[1] === 'update'), false);
});

test('opting in loads gtag once and reports the redacted page, then Livewire navigations', () => {
    const { state, scripts, document, window, commands, pageViews } = boot();
    document.dispatchEvent(new Event('livewire:navigated'));
    assert.equal(pageViews().length, 1);
    assert.equal(scripts.length, 1);
    assert.equal(scripts[0].async, true);
    assert.equal(scripts[0]['data-cfasync'], 'false');
    assert.equal(scripts[0].src, 'https://www.googletagmanager.com/gtag/js?id=G-TEST1234');

    window.publicUniverseAnalytics.choose('analytics');
    assert.equal(scripts.length, 1);
    const granted = commands().find((entry) => entry[0] === 'consent' && entry[1] === 'update');
    assert.equal(granted[2].analytics_storage, 'granted');
    assert.equal(granted[2].ad_storage, undefined);
    assert.equal(granted[2].ad_user_data, undefined);
    assert.equal(granted[2].ad_personalization, undefined);
    const queued = commands();
    const config = queued.find((entry) => entry[0] === 'config');
    const consentDefault = queued.find((entry) => entry[0] === 'consent' && entry[1] === 'default');
    assert.equal(config[2].send_page_view, false);
    assert.equal(config[2].page_location, state.page);
    assert.equal(queued.indexOf(consentDefault) < queued.indexOf(config), true);
    assert.equal(pageViews().length, 1);
    assert.equal(pageViews()[0][2].page_location, 'https://publicuniverse.net/objects/mars');
    assert.equal(JSON.stringify(pageViews()[0]).includes('SECRET'), false);
    assert.equal(window.publicUniverseAnalytics.read(), 'analytics');

    document.dispatchEvent(new Event('livewire:navigated'));
    assert.equal(pageViews().length, 1);

    state.page = 'https://publicuniverse.net/privacy';
    document.title = 'Privacy & cookies';
    document.dispatchEvent(new Event('livewire:navigated'));
    assert.equal(pageViews().length, 2);
    assert.equal(pageViews()[1][2].page_location, 'https://publicuniverse.net/privacy');
    assert.equal(pageViews()[1][2].page_referrer, 'https://publicuniverse.net/objects/mars');
    assert.equal(pageViews()[1][2].page_title, 'Privacy & cookies');
    assert.equal(scripts.length, 1);
});

test('essential only denies storage without reloading and clears analytics cookies', () => {
    const declined = boot();
    declined.window.publicUniverseAnalytics.choose('essential');
    assert.equal(declined.scripts.length, 1);
    assert.equal(declined.state.reloads, 0);
    assert.equal(declined.window.publicUniverseAnalytics.read(), 'essential');
    assert.equal(declined.pageViews().length, 1);
    const denial = declined.commands().filter((entry) => entry[0] === 'consent' && entry[1] === 'update').at(-1);
    assert.equal(denial[2].analytics_storage, 'denied');

    const accepted = boot();
    accepted.window.publicUniverseAnalytics.choose('analytics');
    accepted.document.cookie = '_ga=abc';
    accepted.document.cookie = '_ga_GTEST=1';
    accepted.document.cookie = '_gid=stay';
    accepted.window.publicUniverseAnalytics.choose('essential');
    assert.equal(accepted.state.reloads, 0);
    assert.equal(accepted.jar.has('_ga'), false);
    assert.equal(accepted.jar.has('_ga_GTEST'), false);
    assert.equal(accepted.jar.has('_gid'), false);
    assert.equal(accepted.window.publicUniverseAnalytics.read(), 'essential');
    assert.equal(accepted.storage.get('pu_cookie_consent'), 'essential');
    const withdrawn = accepted.commands().filter((entry) => entry[0] === 'consent' && entry[1] === 'update').at(-1);
    assert.equal(withdrawn[2].analytics_storage, 'denied');
    assert.equal(withdrawn[2].ad_personalization, 'denied');

    accepted.state.page = 'https://publicuniverse.net/privacy';
    accepted.document.dispatchEvent(new Event('livewire:navigated'));
    assert.equal(accepted.pageViews().length, 1);
    assert.equal(accepted.window.__gaStorageGranted, false);
});

test('a legacy essential cookie denies storage when localStorage has no choice', () => {
    const { commands, pageViews, window } = boot('G-TEST1234', null, '', null, false, { cookie_consent: 'essential' });
    const consent = commands().find((entry) => entry[0] === 'consent' && entry[1] === 'default');

    assert.equal(window.publicUniverseAnalytics.read(), 'essential');
    assert.equal(consent[2].analytics_storage, 'denied');
    assert.equal(pageViews().length, 0);
});

test('localStorage takes precedence over a legacy essential cookie', () => {
    const { commands, window } = boot('G-TEST1234', null, '', 'analytics', false, { cookie_consent: 'essential' });
    const consent = commands().find((entry) => entry[0] === 'consent' && entry[1] === 'default');

    assert.equal(window.publicUniverseAnalytics.read(), 'analytics');
    assert.equal(consent[2].analytics_storage, 'granted');
});

test('a planted legacy cookie cannot record an analytics opt-in', () => {
    for (const value of ['analytics', 'all']) {
        const { window } = boot('G-TEST1234', null, '', null, false, { cookie_consent: value });
        assert.equal(window.publicUniverseAnalytics.read(), null);
    }
});

test('essential only expires a legacy cookie_consent=all cookie', () => {
    const accepted = boot('G-TEST1234', null, '', null, false, { cookie_consent: 'all' });
    assert.equal(accepted.window.publicUniverseAnalytics.read(), null);
    assert.equal(accepted.jar.has('cookie_consent'), true);

    accepted.window.publicUniverseAnalytics.choose('essential');
    assert.equal(accepted.jar.has('cookie_consent'), false);
    assert.equal(accepted.storage.get('pu_cookie_consent'), 'essential');
    assert.equal(accepted.window.publicUniverseAnalytics.read(), 'essential');
});

test('a malformed cookie_consent value does not throw before consent defaults', () => {
    const { commands, window } = boot('G-TEST1234', null, '', null, false, { cookie_consent: '%' });
    const consent = commands().find((entry) => entry[0] === 'consent' && entry[1] === 'default');
    const published = window.dataLayer.find((entry) => entry && entry.page_location && entry[0] === undefined);

    assert.equal(window.publicUniverseAnalytics.read(), null);
    assert.ok(consent);
    assert.equal(consent[2].analytics_storage, 'granted');
    assert.equal(published.page_location, 'https://publicuniverse.net/objects/mars');
});

test('a tampered measurement id is not requested', () => {
    const { scripts, window } = boot('G-TEST1234<script>');
    window.publicUniverseAnalytics.load();
    assert.equal(scripts.length, 0);
});

test('Tag Manager alone records a page view without an accept click', () => {
    const { scripts, state, document, window, pageViews } = boot(null, 'GTM-M8THNDPH');
    assert.equal(scripts.length, 0);
    assert.equal(window.__gaLoaded, true);
    assert.equal(pageViews().length, 1);
    assert.equal(pageViews()[0][2].page_location, 'https://publicuniverse.net/objects/mars');

    state.page = 'https://publicuniverse.net/privacy';
    document.dispatchEvent(new Event('livewire:navigated'));
    assert.equal(pageViews().length, 2);
    assert.equal(pageViews()[1][2].page_location, state.page);
    assert.equal(pageViews()[1][2].page_referrer, 'https://publicuniverse.net/objects/mars');
});

test('Tag Manager alone grants analytics storage without requesting gtag.js', () => {
    const { scripts, state, window, commands, pageViews } = boot(null, 'GTM-M8THNDPH');
    assert.equal(window.__gaLoaded, true);
    assert.equal(pageViews().length, 1);
    window.publicUniverseAnalytics.choose('analytics');
    assert.equal(scripts.length, 0);
    const granted = commands().find((entry) => entry[0] === 'consent' && entry[1] === 'update');
    assert.equal(granted[2].analytics_storage, 'granted');
    assert.equal(granted[2].ad_storage, undefined);
    assert.equal(granted[2].ad_user_data, undefined);
    assert.equal(granted[2].ad_personalization, undefined);
    assert.equal(pageViews().length, 1);
    assert.equal(pageViews()[0][2].page_location, 'https://publicuniverse.net/objects/mars');

    window.publicUniverseAnalytics.choose('essential');
    assert.equal(state.reloads, 0);
    assert.equal(window.publicUniverseAnalytics.read(), 'essential');
    const denial = commands().filter((entry) => entry[0] === 'consent' && entry[1] === 'update').at(-1);
    assert.equal(denial[2].analytics_storage, 'denied');
});

test('GA4 and Tag Manager together load gtag.js before consent and grant storage on accept', () => {
    const { scripts, window, commands } = boot('G-TEST1234', 'GTM-M8THNDPH');
    const queued = commands();
    const consent = queued.find((entry) => entry[0] === 'consent' && entry[1] === 'default');
    const config = queued.find((entry) => entry[0] === 'config');
    assert.equal(scripts.length, 1);
    assert.equal(scripts[0]['data-cfasync'], 'false');
    assert.equal(scripts[0].src, 'https://www.googletagmanager.com/gtag/js?id=G-TEST1234');
    assert.equal(consent[2].analytics_storage, 'granted');
    assert.equal(queued.indexOf(consent) < queued.indexOf(config), true);
    window.publicUniverseAnalytics.choose('analytics');
    assert.equal(scripts.length, 1);
    const granted = commands().find((entry) => entry[0] === 'consent' && entry[1] === 'update');
    assert.equal(granted[2].analytics_storage, 'granted');
});

test('a tampered container id does not grant storage on its own', () => {
    const { scripts, window, commands } = boot(null, 'GTM-NO<script>');
    window.publicUniverseAnalytics.load();
    assert.equal(scripts.length, 0);
    assert.equal(commands().some((entry) => entry[0] === 'consent' && entry[1] === 'update'), false);
});

const SISTERS = { 'pu-sister-hosts': 'futwiz.com,pikawiz.com,wickedsick.com' };

test('a denied default sends a cookieless landing page view and does not repeat it on accept', () => {
    const page = boot('G-TEST1234', null, '', null, false, null, { 'ga-consent-default': 'denied' });
    const defaults = page.commands().find((entry) => entry[0] === 'consent' && entry[1] === 'default');

    assert.equal(defaults[2].analytics_storage, 'denied');
    assert.equal(defaults[2].ad_storage, 'denied');
    assert.equal(page.window.__gaCookieless, true);
    assert.equal(page.window.__gaStorageGranted, undefined);
    assert.ok(page.commands().some((entry) => entry[0] === 'set' && entry[1] === 'url_passthrough'));
    assert.equal(page.pageViews().length, 1);

    page.window.publicUniverseAnalytics.choose('analytics');
    const update = page.commands().filter((entry) => entry[0] === 'consent' && entry[1] === 'update').pop();
    assert.equal(update[2].analytics_storage, 'granted');
    assert.equal(page.pageViews().length, 1);
    assert.equal(page.window.__gaCookieless, false);
    assert.equal(page.window.__gaStorageGranted, true);
});

test('a denied default sends nothing after Essential only, and grants a stored accept', () => {
    const essential = boot('G-TEST1234', null, '', 'essential', false, null, { 'ga-consent-default': 'denied' });
    assert.equal(essential.pageViews().length, 0);
    assert.equal(essential.window.__gaCookieless, false);

    const accepted = boot('G-TEST1234', null, '', 'analytics', false, null, { 'ga-consent-default': 'denied' });
    const defaults = accepted.commands().find((entry) => entry[0] === 'consent' && entry[1] === 'default');
    assert.equal(defaults[2].analytics_storage, 'granted');
    assert.equal(accepted.pageViews().length, 1);

    const cookieless = boot('G-TEST1234', null, '', null, false, null, { 'ga-consent-default': 'denied' });
    cookieless.window.publicUniverseAnalytics.choose('essential');
    cookieless.click({ href: '/handouts/primary.pdf', download: 'primary.pdf' });
    assert.equal(cookieless.events('handout_download').length, 0);
});

test('the default stays granted without the meta tag or with an unknown value', () => {
    const page = boot('G-TEST1234', null, '', null, false, null, { 'ga-consent-default': 'maybe' });
    const defaults = page.commands().find((entry) => entry[0] === 'consent' && entry[1] === 'default');

    assert.equal(defaults[2].analytics_storage, 'granted');
    assert.equal(page.window.__gaCookieless, false);
});

test('handout downloads on this site send handout_download with the file name and no query', () => {
    const page = boot('G-TEST1234', null, '', null, false, null, SISTERS);

    page.click({ href: '/handouts/primary-solar-system.pdf?v=3#x', download: 'primary-solar-system.pdf' });
    page.click({ href: 'https://publicuniverse.net/higher-education/kepler-third-law/handout' });
    page.click({ href: 'https://astronomics.com/manual.pdf' });
    page.click({ href: '/objects/mars' });

    const events = page.events('handout_download');
    assert.equal(events.length, 2);
    assert.equal(events[0][2].file_name, 'primary-solar-system.pdf');
    assert.equal(events[0][2].file_extension, 'pdf');
    assert.equal(events[0][2].link_url, 'https://publicuniverse.net/handouts/primary-solar-system.pdf');
    assert.equal(events[1][2].file_name, 'handout');
    assert.equal(events[0][2].transport_type, 'beacon');
});

test('clicks to our other sites send outbound_click with the destination', () => {
    const page = boot('G-TEST1234', null, '', null, false, null, SISTERS);

    page.click({ href: 'https://www.futwiz.com/fc27/players?utm_source=pu' });
    page.click({ href: 'https://shop.pikawiz.com/' });
    page.click({ href: 'https://notfutwiz.com/' });
    page.click({ href: 'https://example.org/' });
    page.click({ href: 'mailto:hello@wickedsick.com' });

    const events = page.events('outbound_click');
    assert.equal(events.length, 2);
    assert.equal(events[0][2].destination, 'futwiz.com');
    assert.equal(events[0][2].link_domain, 'www.futwiz.com');
    assert.equal(events[0][2].link_url, 'https://www.futwiz.com/fc27/players');
    assert.equal(events[0][2].outbound, true);
    assert.equal(events[1][2].destination, 'pikawiz.com');
});

test('Livewire pu-analytics events send alert_signup and ignore unknown names', () => {
    const page = boot('G-TEST1234', null, '', null, false, null, SISTERS);

    page.emit({ name: 'alert_signup', alert_type: 'visibility', object_id: 'planet-saturn', nested: { lat: 51.5 } });
    page.emit({ name: 'purchase', value: 10 });

    const events = page.events('alert_signup');
    assert.equal(events.length, 1);
    assert.equal(events[0][2].alert_type, 'visibility');
    assert.equal(events[0][2].object_id, 'planet-saturn');
    assert.equal(events[0][2].nested, undefined);
    assert.equal(page.events('purchase').length, 0);

    page.window.publicUniverseAnalytics.choose('essential');
    page.emit({ name: 'alert_signup', alert_type: 'visibility' });
    assert.equal(page.events('alert_signup').length, 1);
});

test('a middle-click counts but a right-click context menu does not', () => {
    const page = boot('G-TEST1234', null, '', null, false, null, SISTERS);

    page.click({ href: '/handouts/primary.pdf', download: 'primary.pdf' }, 'auxclick', 2);
    page.click({ href: 'https://futwiz.com/' }, 'auxclick', 2);
    assert.equal(page.events('handout_download').length, 0);
    assert.equal(page.events('outbound_click').length, 0);

    page.click({ href: '/handouts/primary.pdf', download: 'primary.pdf' }, 'auxclick', 1);
    page.click({ href: 'https://futwiz.com/' }, 'auxclick', 1);
    assert.equal(page.events('handout_download').length, 1);
    assert.equal(page.events('outbound_click').length, 1);
});

test('newsletter_signup sends only scalar, non-email params and nothing after Essential only', () => {
    const page = boot('G-TEST1234', null, '', null, false, null, SISTERS);

    page.emit({ name: 'newsletter_signup', source: 'footer', method: 'mailchimp_double_opt_in' });
    const events = page.events('newsletter_signup');
    assert.equal(events.length, 1);
    assert.equal(events[0][2].source, 'footer');
    assert.equal(events[0][2].method, 'mailchimp_double_opt_in');
    assert.equal(events[0][2].email, undefined);
    assert.equal(events[0][2].transport_type, 'beacon');

    page.window.publicUniverseAnalytics.choose('essential');
    page.emit({ name: 'newsletter_signup', source: 'footer' });
    assert.equal(page.events('newsletter_signup').length, 1);

    const optedOut = boot('G-TEST1234', null, '', 'essential', false, null, SISTERS);
    optedOut.emit({ name: 'newsletter_signup', source: 'footer' });
    assert.equal(optedOut.events('newsletter_signup').length, 0);

    const cookieless = boot('G-TEST1234', null, '', null, false, null, { 'ga-consent-default': 'denied' });
    cookieless.emit({ name: 'newsletter_signup', source: 'footer' });
    assert.equal(cookieless.events('newsletter_signup').length, 1);
});

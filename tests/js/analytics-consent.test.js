import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const source = readFileSync(new URL('../../resources/js/analytics-consent.js', import.meta.url), 'utf8');

function boot(id = 'G-TEST1234', gtm = null, referrer = '') {
    const state = {
        id,
        gtm,
        page: 'https://publicuniverse.net/objects/mars',
        reloads: 0,
    };
    const scripts = [];
    const jar = new Map();
    const document = new EventTarget();
    document.title = 'Mars';
    document.head = { appendChild(node) { scripts.push(node); } };
    document.createElement = () => ({ async: false, src: '' });
    document.getElementById = () => null;
    document.querySelector = (selector) => {
        if (selector === 'meta[name="ga-measurement-id"]') return state.id ? { getAttribute: () => state.id } : null;
        if (selector === 'meta[name="gtm-container-id"]') return state.gtm ? { getAttribute: () => state.gtm } : null;
        if (selector === 'meta[name="ga-page-location"]') return { getAttribute: () => state.page };
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
    const location = {
        protocol: 'https:',
        hostname: 'publicuniverse.net',
        origin: 'https://publicuniverse.net',
        pathname: '/objects/mars',
        href: 'https://publicuniverse.net/objects/mars?s=SECRET#s=SECRET',
        reload() { state.reloads += 1; },
    };
    const window = { document, location };
    runInNewContext(source, { window, document, location, URL });
    const commands = () => window.dataLayer.map((entry) => Array.from(entry));
    const pageViews = () => commands().filter((entry) => entry[0] === 'event' && entry[1] === 'page_view');
    return { state, scripts, jar, document, window, commands, pageViews };
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
    assert.equal(pageViews().length, 0);

    const prior = state.page;
    state.page = 'https://publicuniverse.net/observe/night?lat=redacted&lon=redacted';
    document.dispatchEvent(new Event('livewire:navigated'));
    assert.equal(sets().at(-1)[1].page_location, state.page);
    assert.equal(sets().at(-1)[1].send_page_view, false);
    assert.equal(sets().at(-1)[1].page_referrer, prior);
    assert.equal(plain().at(-1).page_location, state.page);
    assert.equal(plain().at(-1).page_referrer, prior);
    assert.equal(pageViews().length, 0);
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
    assert.equal(pageViews().length, 0);

    document.dispatchEvent(new Event('livewire:navigated'));
    assert.equal(plain().at(-1).page_referrer, published);
    assert.equal(sets().at(-1)[1].page_referrer, published);
    assert.equal(JSON.stringify(window.dataLayer).includes('51.5'), false);

    window.publicUniverseAnalytics.choose('all');
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
    const decoded = decodeURIComponent(published);

    assert.equal(decoded.includes('lat[]=redacted'), true);
    assert.equal(decoded.includes('lon[]=redacted'), true);
    assert.equal(decoded.includes('p[lat][]=redacted'), true);
    assert.equal(decoded.includes('p[lon][]=redacted'), true);
    assert.equal(decoded.includes('s[]=redacted'), true);
    assert.equal(decoded.includes('targets=moon'), true);
    assert.equal(published.includes('51.5'), false);
    assert.equal(published.includes('9.87654'), false);
    assert.equal(published.includes('1.23456'), false);
    assert.equal(published.includes('PRIVATE_SETTINGS'), false);
    assert.equal(JSON.stringify(window.dataLayer).includes('51.5'), false);
    assert.equal(JSON.stringify(window.dataLayer).includes('PRIVATE_SETTINGS'), false);

    const nestedReferrer = 'https://publicuniverse.test/api?p[][lat]=51.5&p[][lon]=1.23456&p[0][date]=2026-10-10&p[latitude]=9&latitude=kept';
    const nested = boot(null, 'GTM-M8THNDPH', nestedReferrer);
    const nestedPublished = nested.window.dataLayer.find((entry) => entry && entry.page_referrer && entry[0] === undefined).page_referrer;
    const nestedDecoded = decodeURIComponent(nestedPublished);

    assert.equal(nestedDecoded.includes('p[][lat]=redacted'), true);
    assert.equal(nestedDecoded.includes('p[][lon]=redacted'), true);
    assert.equal(nestedDecoded.includes('p[0][date]=2026-10-10'), true);
    assert.equal(nestedDecoded.includes('p[latitude]=9'), true);
    assert.equal(nestedDecoded.includes('latitude=kept'), true);
    assert.equal(nestedPublished.includes('51.5'), false);
    assert.equal(nestedPublished.includes('1.23456'), false);
    assert.equal(JSON.stringify(nested.window.dataLayer).includes('51.5'), false);
    assert.equal(JSON.stringify(nested.window.dataLayer).includes('1.23456'), false);
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

    window.publicUniverseAnalytics.choose('all');
    assert.equal(pageViews().length, 1);
    assert.equal(pageViews()[0][2].page_location, state.page);
    assert.equal(pageViews()[0][2].page_referrer, prior);

    const next = 'https://publicuniverse.net/about';
    state.page = next;
    document.dispatchEvent(new Event('livewire:navigated'));
    assert.equal(plain().at(-1).page_referrer, 'https://publicuniverse.net/privacy');
    assert.equal(pageViews().at(-1)[2].page_referrer, 'https://publicuniverse.net/privacy');
    assert.equal(pageViews().at(-1)[2].page_location, next);
});

test('Consent Mode v2 denies analytics storage and does not load Google before consent', () => {
    const { scripts, commands } = boot();
    const consent = commands().find((entry) => entry[0] === 'consent' && entry[1] === 'default');
    assert.ok(consent);
    assert.equal(consent[2].analytics_storage, 'denied');
    assert.equal(consent[2].ad_storage, 'denied');
    assert.equal(consent[2].ad_user_data, 'denied');
    assert.equal(consent[2].ad_personalization, 'denied');
    assert.equal(scripts.length, 0);
    assert.equal(commands().some((entry) => entry[1] === 'page_view'), false);
});

test('opting in loads gtag once and reports the redacted page, then Livewire navigations', () => {
    const { state, scripts, document, window, commands, pageViews } = boot();
    document.dispatchEvent(new Event('livewire:navigated'));
    assert.equal(pageViews().length, 0);

    window.publicUniverseAnalytics.choose('all');
    assert.equal(scripts.length, 1);
    assert.equal(scripts[0].async, true);
    assert.equal(scripts[0].src, 'https://www.googletagmanager.com/gtag/js?id=G-TEST1234');
    const granted = commands().find((entry) => entry[0] === 'consent' && entry[1] === 'update');
    assert.equal(granted[2].analytics_storage, 'granted');
    assert.equal(granted[2].ad_storage, undefined);
    assert.equal(granted[2].ad_user_data, undefined);
    assert.equal(granted[2].ad_personalization, undefined);
    const config = commands().find((entry) => entry[0] === 'config');
    assert.equal(config[2].send_page_view, false);
    assert.equal(config[2].page_location, state.page);
    assert.equal(pageViews().length, 1);
    assert.equal(pageViews()[0][2].page_location, 'https://publicuniverse.net/objects/mars');
    assert.equal(JSON.stringify(pageViews()[0]).includes('SECRET'), false);

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

test('essential only never loads Google, and withdrawing consent reloads after clearing analytics cookies', () => {
    const declined = boot();
    declined.window.publicUniverseAnalytics.choose('essential');
    assert.equal(declined.scripts.length, 0);
    assert.equal(declined.state.reloads, 0);
    assert.equal(declined.window.publicUniverseAnalytics.read(), 'essential');
    assert.equal(declined.commands().some((entry) => entry[1] === 'page_view'), false);

    const accepted = boot();
    accepted.window.publicUniverseAnalytics.choose('all');
    accepted.document.cookie = '_ga=abc';
    accepted.document.cookie = '_ga_GTEST=1';
    accepted.document.cookie = '_gid=stay';
    accepted.window.publicUniverseAnalytics.choose('essential');
    assert.equal(accepted.state.reloads, 1);
    assert.equal(accepted.jar.has('_ga'), false);
    assert.equal(accepted.jar.has('_ga_GTEST'), false);
    assert.equal(accepted.jar.has('_gid'), false);
    assert.equal(accepted.jar.get('cookie_consent'), 'essential');
    const denial = accepted.commands().filter((entry) => entry[0] === 'consent' && entry[1] === 'update').at(-1);
    assert.equal(denial[2].analytics_storage, 'denied');
    assert.equal(denial[2].ad_personalization, 'denied');
});

test('a tampered measurement id is not requested', () => {
    const { scripts, window } = boot('G-TEST1234<script>');
    window.publicUniverseAnalytics.load();
    assert.equal(scripts.length, 0);
});

test('Tag Manager alone grants analytics storage without requesting gtag.js', () => {
    const { scripts, state, window, commands, pageViews } = boot(null, 'GTM-M8THNDPH');
    window.publicUniverseAnalytics.choose('all');
    assert.equal(scripts.length, 0);
    const granted = commands().find((entry) => entry[0] === 'consent' && entry[1] === 'update');
    assert.equal(granted[2].analytics_storage, 'granted');
    assert.equal(granted[2].ad_storage, undefined);
    assert.equal(granted[2].ad_user_data, undefined);
    assert.equal(granted[2].ad_personalization, undefined);
    assert.equal(pageViews().length, 1);
    assert.equal(pageViews()[0][2].page_location, 'https://publicuniverse.net/objects/mars');

    window.publicUniverseAnalytics.choose('essential');
    assert.equal(state.reloads, 1);
    const denial = commands().filter((entry) => entry[0] === 'consent' && entry[1] === 'update').at(-1);
    assert.equal(denial[2].analytics_storage, 'denied');
});

test('GA4 and Tag Manager together still load gtag.js only after consent', () => {
    const { scripts, window } = boot('G-TEST1234', 'GTM-M8THNDPH');
    assert.equal(scripts.length, 0);
    window.publicUniverseAnalytics.choose('all');
    assert.equal(scripts.length, 1);
    assert.equal(scripts[0].src, 'https://www.googletagmanager.com/gtag/js?id=G-TEST1234');
});

test('a tampered container id does not grant storage on its own', () => {
    const { scripts, window, commands } = boot(null, 'GTM-NO<script>');
    window.publicUniverseAnalytics.load();
    assert.equal(scripts.length, 0);
    assert.equal(commands().some((entry) => entry[0] === 'consent' && entry[1] === 'update'), false);
});

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const source = readFileSync(new URL('../../resources/js/analytics-consent.js', import.meta.url), 'utf8');

function boot(id = 'G-TEST1234') {
    const state = {
        id,
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
        if (selector === 'meta[name="ga-measurement-id"]') return { getAttribute: () => state.id };
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
    const location = {
        protocol: 'https:',
        hostname: 'publicuniverse.net',
        origin: 'https://publicuniverse.net',
        pathname: '/objects/mars',
        href: 'https://publicuniverse.net/objects/mars?s=SECRET#s=SECRET',
        reload() { state.reloads += 1; },
    };
    const window = { document, location };
    runInNewContext(source, { window, document, location });
    const commands = () => window.dataLayer.map((entry) => Array.from(entry));
    const pageViews = () => commands().filter((entry) => entry[0] === 'event' && entry[1] === 'page_view');
    return { state, scripts, jar, document, window, commands, pageViews };
}

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

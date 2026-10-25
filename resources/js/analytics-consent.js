// Google Analytics 4 and Google Tag Manager, UK GDPR / PECR.
//
// Consent Mode v2 defaults are denied before the Tag Manager snippet. That
// snippet is part of the document when a container id is configured; this
// file does not inject it. Before the snippet runs, and again after each
// Livewire navigation, the redacted address from
// <meta name="ga-page-location"> is written to dataLayer and gtag('set'),
// with page_referrer. The first publication uses document.referrer after the
// same sensitive query parameters are removed. Bracketed keys are folded the
// way PHP folds them, so lat[] and the server meta both become lat=redacted,
// and p[][lat] keeps its numeric index. A history change or livewire:navigate
// publishes that address provisionally and leaves the committed page where it
// is. Livewire fires navigated once on boot without changing the page, and
// that keeps the landing referrer. A later navigated event and popstate
// commit the address: the previous committed page becomes page_referrer,
// because document.referrer does not update on wire:navigate. The provisional
// publication runs synchronously inside history.pushState and replaceState,
// before any later history wrapper. Each wrapper keeps its own delegate, and
// the publication runs once on the outermost call. Container tags then use
// those values instead of the address bar and the raw referrer, including
// cookieless hits while storage is denied. send_page_view stays false. That
// does not disable GA4
// Enhanced Measurement history page views.
// Disable 'Page changes based on browser history events' under GA4 Enhanced Measurement when configuring the GA4 tag in GTM-M8THNDPH, as SPA page_view events are dispatched manually after URL redaction.
// Automatic page views stay off. When a measurement id is set, gtag.js loads
// on every page after the consent default. analytics_storage is 'denied'
// unless cookie_consent is all, and a page_view is sent immediately so
// Consent Mode records a cookieless ping. Accepting analytics updates storage
// to granted and sends page_view again. Tag Manager alone still sends that
// page_view when the visitor accepts, and updates consent so the container's
// tags may store cookies. Essential only never grants storage.
(function () {
    if (window.__puAnalyticsInit) {
        return;
    }
    window.__puAnalyticsInit = true;

    var COOKIE = 'cookie_consent'; // App\Support\Analytics::CONSENT_COOKIE
    var ONE_YEAR = 31536000;
    var lastLocation = null;
    // Committed page_location and the referrer that belonged to it. History
    // and livewire:navigate publish a provisional address first; only popstate
    // and livewire:navigated move these, so one navigation cannot advance the
    // referrer twice. Null until the first committed publication.
    var committedPageLocation = null;
    var committedPageReferrer = null;

    function gtag() {
        window.dataLayer.push(arguments);
    }

    function measurementId() {
        var meta = document.querySelector('meta[name="ga-measurement-id"]');
        var id = meta && meta.getAttribute('content');

        return id && /^G-[A-Z0-9]{4,32}$/.test(id) ? id : null;
    }

    function gtmContainerId() {
        var meta = document.querySelector('meta[name="gtm-container-id"]');
        var id = meta && meta.getAttribute('content');

        return id && /^GTM-[A-Z0-9]{4,16}$/.test(id) ? id : null;
    }

    function pageLocation() {
        // The layout publishes the address gtag may report. Current share
        // tokens live in the fragment (never on this request); leftover ?s=
        // query values are redacted. Without the tag, report the path alone.
        var meta = document.querySelector('meta[name="ga-page-location"]');
        var value = meta && meta.getAttribute('content');

        return value || (location.origin + location.pathname);
    }

    // Ordered query bag. PHP keeps insertion order, including numeric indexes,
    // and http_build_query emits that order. A plain object would sort numeric
    // keys and make the same address differ from the server meta.
    function QueryBag() {
        this.keys = [];
        this.vals = {};
        this.bag = true;
    }

    function isQueryBag(value) {
        return !!value && value.bag === true;
    }

    function bagHas(bag, key) {
        return Object.prototype.hasOwnProperty.call(bag.vals, key);
    }

    function bagSet(bag, key, value) {
        if (!bagHas(bag, key)) {
            bag.keys.push(key);
        }
        bag.vals[key] = value;
    }

    function nextIndex(bag) {
        var max = -1;
        var i;
        for (i = 0; i < bag.keys.length; i++) {
            if (/^(?:0|[1-9]\d*)$/.test(bag.keys[i])) {
                var n = Number(bag.keys[i]);
                if (n > max) {
                    max = n;
                }
            }
        }

        return String(max + 1);
    }

    function phpUrlDecode(value) {
        var text = String(value).replace(/\+/g, ' ');
        try {
            return decodeURIComponent(text);
        } catch (error) {
            return text;
        }
    }

    function phpUrlEncode(value) {
        // http_build_query uses RFC 1738: spaces are +, and brackets are escaped.
        return encodeURIComponent(String(value))
            .replace(/[!'()*~]/g, function (ch) {
                return '%' + ch.charCodeAt(0).toString(16).toUpperCase();
            })
            .replace(/%20/g, '+')
            .replace(/\[/g, '%5B')
            .replace(/\]/g, '%5D');
    }

    function phpNormalizeName(name) {
        // parse_str turns dots and spaces in the name before any bracket into
        // underscores. Names inside brackets are left as written.
        return name.replace(/[.\s]/g, '_');
    }

    function phpAssign(root, key, value) {
        var bracket = key.indexOf('[');
        if (bracket <= 0) {
            bagSet(root, phpNormalizeName(key), value);
            return;
        }
        var name = phpNormalizeName(key.slice(0, bracket));
        var rest = key.slice(bracket);
        var segments = [];
        var pos = 0;
        // parse_str keeps each complete [...] that follows immediately, then
        // ignores the rest. p[lat]extra and lat[0]extra are p[lat] and lat[0],
        // and p[lat]extra[lon] does not become a second index.
        while (pos < rest.length && rest.charAt(pos) === '[') {
            var close = rest.indexOf(']', pos + 1);
            if (close === -1) {
                break;
            }
            segments.push(rest.slice(pos + 1, close));
            pos = close + 1;
        }
        if (segments.length === 0) {
            // An unclosed bracket is not an array access. PHP turns each `[` into `_`.
            bagSet(root, phpNormalizeName(key.replace(/\[/g, '_')), value);
            return;
        }
        if (!bagHas(root, name) || !isQueryBag(root.vals[name])) {
            bagSet(root, name, new QueryBag());
        }
        var cursor = root.vals[name];
        var i;
        for (i = 0; i < segments.length; i++) {
            var segment = segments[i];
            var last = i === segments.length - 1;
            if (segment === '') {
                segment = nextIndex(cursor);
            }
            if (last) {
                bagSet(cursor, segment, value);
            } else if (!bagHas(cursor, segment) || !isQueryBag(cursor.vals[segment])) {
                bagSet(cursor, segment, new QueryBag());
                cursor = cursor.vals[segment];
            } else {
                cursor = cursor.vals[segment];
            }
        }
    }

    function phpParseQuery(search) {
        var root = new QueryBag();
        if (!search) {
            return root;
        }
        var pairs = search.split('&');
        var i;
        for (i = 0; i < pairs.length; i++) {
            if (pairs[i] === '') {
                continue;
            }
            var eq = pairs[i].indexOf('=');
            var rawKey = eq === -1 ? pairs[i] : pairs[i].slice(0, eq);
            var rawValue = eq === -1 ? '' : pairs[i].slice(eq + 1);
            phpAssign(root, phpUrlDecode(rawKey), phpUrlDecode(rawValue));
        }

        return root;
    }

    function redactTryBag(bag) {
        // Coordinates inside the /api try-it bag, including p[lon][] and p[][lat].
        // A lat or lon key is replaced whole, which collapses p[lat][] to p[lat].
        // Nested bags keep their indexes, so p[][lat] stays p[0][lat].
        var i;
        for (i = 0; i < bag.keys.length; i++) {
            var key = bag.keys[i];
            var value = bag.vals[key];
            if (key === 'lat' || key === 'lon') {
                bag.vals[key] = 'redacted';
            } else if (isQueryBag(value)) {
                bag.vals[key] = redactTryBag(value);
            }
        }

        return bag;
    }

    function redactQuery(query) {
        // lat[] and s[token] arrive as arrays under lat and s. Replacing the
        // whole value drops every nested entry, matching Analytics::pageLocation().
        var i;
        for (i = 0; i < query.keys.length; i++) {
            var key = query.keys[i];
            if (key === 's' || key === 'lat' || key === 'lon') {
                query.vals[key] = 'redacted';
            } else if (key === 'p' && isQueryBag(query.vals[key])) {
                query.vals[key] = redactTryBag(query.vals[key]);
            }
        }

        return query;
    }

    function appendBuiltQuery(parts, prefix, value) {
        if (isQueryBag(value)) {
            var i;
            for (i = 0; i < value.keys.length; i++) {
                var key = value.keys[i];
                var next = prefix === '' ? key : prefix + '[' + key + ']';
                appendBuiltQuery(parts, next, value.vals[key]);
            }
            return;
        }
        parts.push(phpUrlEncode(prefix) + '=' + phpUrlEncode(value));
    }

    function buildQuery(query) {
        var parts = [];
        appendBuiltQuery(parts, '', query);

        return parts.join('&');
    }

    function sanitizeAnalyticsUrl(value) {
        // Same-origin navigations keep the full query string in
        // document.referrer. GA4 copies that into page_referrer on its own,
        // so the parameters page_location already redacts are removed here.
        // The query is parsed and encoded like PHP, so a provisional history
        // URL and the server meta for that navigation are the same string.
        // An empty or unparseable referrer is reported as an empty string.
        if (typeof value !== 'string' || value === '') {
            return '';
        }
        var url;
        try {
            url = new URL(value);
        } catch (error) {
            return '';
        }
        if (url.protocol !== 'http:' && url.protocol !== 'https:') {
            return '';
        }
        var search = url.search.charAt(0) === '?' ? url.search.slice(1) : url.search;
        var built = buildQuery(redactQuery(phpParseQuery(search)));
        // Request::url() trims every trailing slash, so the homepage is
        // https://example.test and /objects/mars/ is /objects/mars. Leaving
        // the slash in would make popstate and the server meta two addresses.
        var base = (url.origin + url.pathname).replace(/\/+$/, '');

        return base + (built === '' ? '' : '?' + built) + (url.hash || '');
    }

    function referrerFor(page) {
        // A changed address reports the previous committed page. Provisional
        // publications do not count. The same address, including Livewire's
        // boot event, keeps the referrer already chosen for this page (the
        // sanitized document.referrer at first).
        if (committedPageLocation !== null && committedPageLocation !== page) {
            return committedPageLocation;
        }

        return committedPageReferrer === null ? '' : committedPageReferrer;
    }

    function read() {
        var match = document.cookie.match(new RegExp('(?:^|; )' + COOKIE + '=([^;]*)'));

        return match ? decodeURIComponent(match[1]) : null;
    }

    function write(value) {
        document.cookie = COOKIE + '=' + encodeURIComponent(value) + '; Max-Age=' + ONE_YEAR + '; Path=/; SameSite=Lax' +
            (location.protocol === 'https:' ? '; Secure' : '');
    }

    function isAnalyticsCookie(name) {
        return name === '_ga' || name === '_gid' || name === '_gat' || name.indexOf('_ga_') === 0 || name.indexOf('_gat_') === 0;
    }

    function clearAnalyticsCookies() {
        var names = document.cookie.split(';').map(function (part) {
            return part.split('=')[0].trim();
        }).filter(isAnalyticsCookie);
        var host = location.hostname;
        var domains = [''];

        if (host) {
            domains.push(host);
            var parts = host.split('.');
            if (parts.length >= 2) {
                domains.push('.' + parts.slice(-2).join('.'));
            }
        }

        names.forEach(function (name) {
            domains.forEach(function (domain) {
                document.cookie = name + '=; Max-Age=0; Path=/' + (domain ? '; Domain=' + domain : '') + '; SameSite=Lax';
            });
        });
    }

    function closeBanner() {
        if (typeof window.dispatchEvent !== 'function') {
            return;
        }
        window.dispatchEvent(new CustomEvent('cookie-consent-chosen'));
    }

    function urlText(value) {
        if (typeof value === 'string') {
            return value;
        }
        if (value && typeof value.href === 'string') {
            return value.href;
        }

        return '';
    }

    function redactedHistoryUrl(value) {
        // History changes expose the address bar before Livewire swaps in the
        // server-rendered meta. Redact the URL being committed, and drop the
        // fragment, which can carry a settings token.
        var text = urlText(value);
        if (text === '') {
            return pageLocation();
        }
        var absolute;
        try {
            absolute = new URL(text, location.href);
        } catch (error) {
            return pageLocation();
        }
        absolute.hash = '';
        var sanitized = sanitizeAnalyticsUrl(absolute.toString());

        return sanitized || pageLocation();
    }

    function publishPageLocation() {
        // Commits the address. Container-managed tags read the address bar and
        // document.referrer unless both are already in the dataLayer when they
        // initialise, and again when the page changes without a reload. The
        // manual page_view below is the only page view. send_page_view: false
        // does not stop GA4 Enhanced Measurement from sending its own hit on
        // pushState.
        var override = arguments[0];
        var page = typeof override === 'string' && override !== '' ? override : pageLocation();

        return commitPageNavigation(page);
    }

    function publishProvisionalPageLocation(page) {
        // History and livewire:navigate run before the server meta exists.
        // Publish the redacted destination without moving the committed page,
        // so the later navigated event still reports the origin as referrer.
        return writePageFields(page, false);
    }

    function commitPageNavigation(page) {
        return writePageFields(page, true);
    }

    function writePageFields(page, commit) {
        if (!page) {
            return '';
        }
        if (committedPageReferrer === null) {
            committedPageReferrer = sanitizeAnalyticsUrl(document.referrer);
        }
        var referrer = referrerFor(page);
        window.dataLayer.push({
            page_location: page,
            page_referrer: referrer,
        });
        window.gtag('set', {
            page_location: page,
            page_referrer: referrer,
            send_page_view: false,
        });
        if (commit && committedPageLocation !== null && committedPageLocation !== page) {
            committedPageReferrer = committedPageLocation;
        }
        if (commit) {
            committedPageLocation = page;
        }

        return page;
    }

    function bindHistoryMethod(method) {
        var nav = window.history;
        if (!nav || typeof nav[method] !== 'function') {
            return;
        }
        var native = nav[method];
        var depth = 0;

        function wrap(delegate) {
            // Each assigned wrapper closes over its own delegate. A later
            // script that captures and replaces pushState stays in the chain
            // instead of being dropped for the native method. Publication
            // happens only on the outermost call.
            return function () {
                var args = arguments;
                var self = this;
                if (depth === 0) {
                    var target = args.length >= 3 ? args[2] : null;
                    publishProvisionalPageLocation(redactedHistoryUrl(target));
                }
                depth += 1;
                try {
                    return delegate.apply(self, args);
                } finally {
                    depth -= 1;
                }
            };
        }

        var current = wrap(native);

        try {
            Object.defineProperty(nav, method, {
                configurable: true,
                enumerable: true,
                get: function () {
                    return current;
                },
                set: function (next) {
                    if (typeof next === 'function' && next !== current) {
                        current = wrap(next);
                    }
                },
            });
        } catch (error) {
            nav[method] = current;
        }
    }

    function installHistoryPublisher() {
        bindHistoryMethod('pushState');
        bindHistoryMethod('replaceState');
        if (typeof window.addEventListener === 'function') {
            window.addEventListener('popstate', function () {
                publishPageLocation(redactedHistoryUrl(location.href));
            }, true);
        }
        // livewire:navigate is dispatched before pushState and before
        // livewire:navigating. Capture runs before bubble listeners.
        document.addEventListener('livewire:navigate', function (event) {
            var detail = event && event.detail;
            var url = detail && detail.url;
            if (url && typeof url.href === 'string') {
                url = url.href;
            }
            if (typeof url === 'string' && url !== '') {
                publishProvisionalPageLocation(redactedHistoryUrl(url));
            }
        }, true);
    }

    function sendPageView() {
        if (!window.__gaLoaded || typeof window.gtag !== 'function') {
            return;
        }
        var page = pageLocation();
        if (!page || page === lastLocation) {
            return;
        }
        var referrer = referrerFor(page);
        lastLocation = page;
        window.gtag('event', 'page_view', {
            page_title: document.title,
            page_location: page,
            page_referrer: referrer,
        });
    }

    function analyticsStorageGranted() {
        return read() === 'all';
    }

    function configureMeasurement() {
        var id = measurementId();
        if (!id || window.__gtagConfigured) {
            return;
        }
        window.__gtagConfigured = true;
        // The layout renders this tag when it can, ahead of Rocket Loader.
        // Tests and a missing tag fall back to the same attributes.
        if (!window.__puGtagScriptTag) {
            var script = document.createElement('script');
            script.async = true;
            script.setAttribute('data-cfasync', 'false');
            script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
            document.head.appendChild(script);
        }
        window.gtag('js', new Date());
        window.gtag('config', id, {
            anonymize_ip: true,
            page_location: pageLocation(),
            send_page_view: false,
        });
    }

    function load() {
        if (window.__gaLoaded && window.__gaStorageGranted) {
            return;
        }
        var id = measurementId();
        var gtm = gtmContainerId();
        if (!id && !gtm) {
            return;
        }
        if (analyticsStorageGranted()) {
            window.gtag('consent', 'update', {
                analytics_storage: 'granted',
            });
            if (window.__gaStorageGranted !== true) {
                window.__gaStorageGranted = true;
                // The landing page_view was cookieless. Send it again now that
                // storage is granted. Later navigations already carry the grant.
                lastLocation = null;
            }
        }
        window.__gaLoaded = true;
        if (id) {
            configureMeasurement();
        }
        sendPageView();
    }

    function deny() {
        var hadGrant = window.__gaStorageGranted === true;
        clearAnalyticsCookies();
        window.__gaStorageGranted = false;
        if (!window.__gaLoaded || typeof window.gtag !== 'function') {
            return;
        }
        window.gtag('consent', 'update', {
            ad_storage: 'denied',
            ad_user_data: 'denied',
            ad_personalization: 'denied',
            analytics_storage: 'denied',
        });
        // A cookieless tag is already in denied mode. Reload only after a
        // grant, so stored analytics cookies are dropped with the page.
        if (hadGrant) {
            window.location.reload();
        }
    }

    function choose(value) {
        if (value !== 'all' && value !== 'essential') {
            return;
        }
        write(value);
        closeBanner();
        if (value === 'all') {
            load();
            return;
        }
        deny();
    }

    window.dataLayer = window.dataLayer || [];
    window.gtag = window.gtag || gtag;
    // Denied until cookie_consent=all, including when the visitor has not
    // chosen yet. This runs before gtag('config') so the first hit is a
    // cookieless Consent Mode ping.
    var granted = analyticsStorageGranted();
    window.gtag('consent', 'default', {
        ad_storage: 'denied',
        ad_user_data: 'denied',
        ad_personalization: 'denied',
        analytics_storage: granted ? 'granted' : 'denied',
        wait_for_update: 500,
    });
    window.gtag('set', 'ads_data_redaction', true);
    if (!granted) {
        window.gtag('set', 'url_passthrough', true);
    } else {
        window.__gaStorageGranted = true;
    }
    installHistoryPublisher();
    publishPageLocation();
    if (measurementId()) {
        window.__gaLoaded = true;
        configureMeasurement();
        sendPageView();
    }

    window.publicUniverseAnalytics = {
        choose: choose,
        load: load,
        pageLocation: pageLocation,
        read: read,
    };

    document.addEventListener('livewire:navigated', function () {
        publishPageLocation();
        sendPageView();
    }, true);
})();

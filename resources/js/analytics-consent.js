// Google Analytics 4 and Google Tag Manager, UK GDPR / PECR.
//
// Consent Mode v2 defaults are denied before the Tag Manager snippet. That
// snippet is part of the document when a container id is configured; this
// file does not inject it. Before the snippet runs, and again after each
// Livewire navigation, the redacted address from
// <meta name="ga-page-location"> is written to dataLayer and gtag('set'),
// with page_referrer. The first publication uses document.referrer after the
// same sensitive query parameters are removed, including bracketed forms such
// as lat[], p[lon][], and p[][lat]. Livewire fires navigated once on boot without
// changing the page, and that keeps the landing referrer. A later navigated
// event whose address changed uses the previous redacted page_location,
// because document.referrer does not update on wire:navigate. The same
// publication runs synchronously inside history.pushState and replaceState,
// and on popstate, before any later history wrapper or livewire:navigating /
// livewire:navigated listener. Container tags then use those values instead
// of the address bar and the raw referrer, including cookieless hits while
// storage is denied. send_page_view stays false. That does not disable GA4
// Enhanced Measurement history page views.
// Disable 'Page changes based on browser history events' under GA4 Enhanced Measurement when configuring the GA4 tag in GTM-M8THNDPH, as SPA page_view events are dispatched manually after URL redaction.
// Automatic page views stay off;
// a page_view event is sent only after cookie_consent=all. gtag.js is
// injected only after that choice, and only when a GA4 measurement id is
// set. Accepting analytics with Tag Manager alone updates consent so the
// container's tags may run. Essential only never grants storage.
(function () {
    if (window.__puAnalyticsInit) {
        return;
    }
    window.__puAnalyticsInit = true;

    var COOKIE = 'cookie_consent'; // App\Support\Analytics::CONSENT_COOKIE
    var ONE_YEAR = 31536000;
    var lastLocation = null;
    // Last redacted page_location already given to tags, and the referrer that
    // belonged to it. Null until the first publication.
    var publishedLocation = null;
    var publishedReferrer = null;

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

    function isSensitiveAnalyticsParam(key) {
        // PHP parses lat[], s[], and p[lon][] as nested arrays and redacts the
        // whole value. URLSearchParams keeps the brackets in the key, so match
        // the name and any bracketed descendant. p[][lat] and p[][lon] are the
        // same coordinates one level further down.
        if (/^(?:s|lat|lon)(?:\[.*\])?$/i.test(key)) {
            return true;
        }

        return /^p\[/i.test(key) && /\[(?:lat|lon)\]/i.test(key);
    }

    function sanitizeAnalyticsUrl(value) {
        // Same-origin navigations keep the full query string in
        // document.referrer. GA4 copies that into page_referrer on its own,
        // so the parameters page_location already redacts are removed here.
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
        var keys = [];
        url.searchParams.forEach(function (paramValue, key) {
            keys.push(key);
        });
        keys.forEach(function (key) {
            if (isSensitiveAnalyticsParam(key)) {
                url.searchParams.set(key, 'redacted');
            }
        });

        return url.toString();
    }

    function referrerFor(page) {
        // A changed address reports the previous redacted page. The same
        // address, including Livewire's boot event, keeps the referrer already
        // chosen for this page (the sanitized document.referrer at first).
        if (publishedLocation !== null && publishedLocation !== page) {
            return publishedLocation;
        }

        return publishedReferrer === null ? '' : publishedReferrer;
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
        // Container-managed tags read the address bar and document.referrer
        // unless both are already in the dataLayer when they initialise, and
        // again when the page changes without a reload. The manual page_view
        // below is the only page view. send_page_view: false does not stop
        // GA4 Enhanced Measurement from sending its own hit on pushState.
        var override = arguments[0];
        var page = typeof override === 'string' && override !== '' ? override : pageLocation();
        if (!page) {
            return '';
        }
        if (publishedReferrer === null) {
            publishedReferrer = sanitizeAnalyticsUrl(document.referrer);
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
        if (publishedLocation !== null && publishedLocation !== page) {
            publishedReferrer = publishedLocation;
        }
        publishedLocation = page;

        return page;
    }

    function bindHistoryMethod(method) {
        var nav = window.history;
        if (!nav || typeof nav[method] !== 'function') {
            return;
        }
        var native = nav[method];
        var inner = native;
        var applying = false;

        function outer() {
            // A wrapper installed later (Tag Manager) calls back into us.
            // The re-entry must hit the browser method, not publish again.
            if (applying) {
                return native.apply(this, arguments);
            }
            applying = true;
            try {
                var target = arguments.length >= 3 ? arguments[2] : null;
                // Publish before the inner wrapper, so a history listener that
                // sends a hit sees the redacted page_location already set.
                publishPageLocation(redactedHistoryUrl(target));
                return inner.apply(this, arguments);
            } finally {
                applying = false;
            }
        }

        try {
            Object.defineProperty(nav, method, {
                configurable: true,
                enumerable: true,
                get: function () {
                    return outer;
                },
                set: function (next) {
                    if (typeof next === 'function' && next !== outer) {
                        inner = next;
                    }
                },
            });
        } catch (error) {
            nav[method] = outer;
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
                publishPageLocation(redactedHistoryUrl(url));
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

    function load() {
        if (window.__gaLoaded) {
            return;
        }
        var id = measurementId();
        var gtm = gtmContainerId();
        if (!id && !gtm) {
            return;
        }
        window.__gaLoaded = true;
        window.gtag('consent', 'update', {
            analytics_storage: 'granted',
        });
        if (!id) {
            sendPageView();
            return;
        }
        var script = document.createElement('script');
        script.async = true;
        script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
        document.head.appendChild(script);
        window.gtag('js', new Date());
        window.gtag('config', id, {
            anonymize_ip: true,
            page_location: pageLocation(),
            send_page_view: false,
        });
        sendPageView();
    }

    function deny() {
        clearAnalyticsCookies();
        if (!window.__gaLoaded || typeof window.gtag !== 'function') {
            return;
        }
        window.gtag('consent', 'update', {
            ad_storage: 'denied',
            ad_user_data: 'denied',
            ad_personalization: 'denied',
            analytics_storage: 'denied',
        });
        window.location.reload();
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
    window.gtag('consent', 'default', {
        ad_storage: 'denied',
        ad_user_data: 'denied',
        ad_personalization: 'denied',
        analytics_storage: 'denied',
        wait_for_update: 500,
    });
    window.gtag('set', 'ads_data_redaction', true);
    window.gtag('set', 'url_passthrough', false);
    installHistoryPublisher();
    publishPageLocation();

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

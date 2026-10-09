// Google Analytics 4, UK GDPR / PECR.
//
// Consent Mode v2 defaults are denied before any Google script exists.
// googletagmanager.com is injected only after cookie_consent=all. Essential
// only never loads it. Livewire's wire:navigate does not reload the page, so
// a later page_view is sent from livewire:navigated using the redacted
// address in <meta name="ga-page-location">, not location.href.
(function () {
    if (window.__puAnalyticsInit) {
        return;
    }
    window.__puAnalyticsInit = true;

    var COOKIE = 'cookie_consent';
    var ONE_YEAR = 31536000;
    var lastLocation = null;

    function gtag() {
        window.dataLayer.push(arguments);
    }

    function measurementId() {
        var meta = document.querySelector('meta[name="ga-measurement-id"]');
        var id = meta && meta.getAttribute('content');

        return id && /^G-[A-Z0-9]{4,32}$/.test(id) ? id : null;
    }

    function pageLocation() {
        // The layout publishes the address gtag may report. Current share
        // tokens live in the fragment (never on this request); leftover ?s=
        // query values are redacted. Without the tag, report the path alone.
        var meta = document.querySelector('meta[name="ga-page-location"]');
        var value = meta && meta.getAttribute('content');

        return value || (location.origin + location.pathname);
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

    function sendPageView() {
        if (!window.__gaLoaded || typeof window.gtag !== 'function') {
            return;
        }
        var page = pageLocation();
        if (!page || page === lastLocation) {
            return;
        }
        lastLocation = page;
        window.gtag('event', 'page_view', {
            page_title: document.title,
            page_location: page,
        });
    }

    function load() {
        if (window.__gaLoaded) {
            return;
        }
        var id = measurementId();
        if (!id) {
            return;
        }
        window.__gaLoaded = true;
        window.gtag('consent', 'update', {
            analytics_storage: 'granted',
        });
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

    window.publicUniverseAnalytics = {
        choose: choose,
        load: load,
        pageLocation: pageLocation,
        read: read,
    };

    document.addEventListener('livewire:navigated', sendPageView);
})();

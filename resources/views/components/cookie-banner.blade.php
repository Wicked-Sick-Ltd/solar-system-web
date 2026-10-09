{{--
    Cookie consent. Essential-only is the default; nothing non-essential runs
    until the visitor explicitly accepts. The choice lives in a first-party
    cookie (`cookie_consent` = essential | all) for a year.

    Google Consent Mode v2 defaults (analytics_storage denied) are set in
    <x-analytics /> when a measurement id is configured. gtag.js is loaded only
    after "Accept analytics"; Livewire wire:navigate fires page_view thereafter.

    Rendered only when analytics is configured: without it the site sets
    essential cookies only, which need no consent banner.
--}}
@if (config('site.analytics.ga_measurement_id'))
<div id="cookie-banner" x-data="cookieConsent()" x-init="init()" x-show="open" x-cloak
     class="fixed inset-x-0 bottom-0 z-40 border-t p-4 sm:p-5"
     style="background-color: var(--bg-elevated); border-color: var(--border); box-shadow: 0 -8px 32px rgba(0,0,0,.35);"
     role="region" aria-label="{{ __('Cookie consent') }}">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm leading-relaxed" style="color: var(--text); max-width: 60ch;">
            {{ __('We use an essential cookie to remember this choice. With your permission we also use Google Analytics to see which pages are useful.') }}
            <a class="link-quiet underline" href="{{ route('privacy') }}">{{ __('Privacy & cookies') }}</a>
        </p>
        <div class="flex shrink-0 gap-2">
            <button type="button" class="rounded-lg border px-4 py-2 text-sm font-medium" @click="choose('essential')"
                    style="border-color: var(--border); color: var(--text);">{{ __('Essential only') }}</button>
            <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium" @click="choose('all')"
                    style="background-color: var(--accent); color: #07090f;">{{ __('Accept analytics') }}</button>
        </div>
    </div>
</div>

<script>
    function cookieConsent() {
        var NAME = 'cookie_consent';
        var REDACTED_QUERY_PARAMS = ['s'];

        function read() {
            var m = document.cookie.match(new RegExp('(?:^|; )' + NAME + '=([^;]*)'));
            return m ? decodeURIComponent(m[1]) : null;
        }
        function write(value) {
            document.cookie = NAME + '=' + encodeURIComponent(value) + '; Max-Age=31536000; Path=/; SameSite=Lax' +
                (location.protocol === 'https:' ? '; Secure' : '');
        }
        function clientPageLocation() {
            // Mirror App\Support\Analytics: redact share tokens, never send the fragment.
            try {
                var u = new URL(location.href);
                u.hash = '';
                REDACTED_QUERY_PARAMS.forEach(function (key) {
                    if (u.searchParams.has(key)) {
                        u.searchParams.set(key, 'redacted');
                    }
                });
                return u.origin + u.pathname + u.search;
            } catch (e) {
                return location.origin + location.pathname;
            }
        }
        function pageLocation(preferMeta) {
            // First hit: prefer the server-rendered redacted address. After
            // wire:navigate the meta can be stale, so recompute from the bar.
            if (preferMeta !== false) {
                var meta = document.querySelector('meta[name="ga-page-location"]');
                var value = meta && meta.getAttribute('content');
                if (value) return value;
            }
            return clientPageLocation();
        }
        function measurementId() {
            var meta = document.querySelector('meta[name="ga-measurement-id"]');
            return meta ? meta.getAttribute('content') : null;
        }
        function grantAnalyticsConsent() {
            if (typeof window.gtag !== 'function') return;
            window.gtag('consent', 'update', { analytics_storage: 'granted' });
        }
        function pagePath() {
            try {
                var u = new URL(location.href);
                REDACTED_QUERY_PARAMS.forEach(function (key) {
                    if (u.searchParams.has(key)) {
                        u.searchParams.set(key, 'redacted');
                    }
                });
                return u.pathname + u.search;
            } catch (e) {
                return location.pathname;
            }
        }
        function trackPageView() {
            var id = measurementId();
            if (!id || !window.__gaLoaded || typeof window.gtag !== 'function') return;
            window.gtag('event', 'page_view', {
                send_to: id,
                page_location: pageLocation(false),
                page_path: pagePath()
            });
        }
        function bindNavigateTracking() {
            if (window.__gaNavigateBound) return;
            window.__gaNavigateBound = true;
            document.addEventListener('livewire:navigated', trackPageView);
        }
        function loadAnalytics() {
            var id = measurementId();
            if (!id || window.__gaLoaded) return;
            window.__gaLoaded = true;
            grantAnalyticsConsent();
            var s = document.createElement('script');
            s.async = true;
            s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
            document.head.appendChild(s);
            window.dataLayer = window.dataLayer || [];
            if (typeof window.gtag !== 'function') {
                function gtag() { window.dataLayer.push(arguments); }
                window.gtag = gtag;
            }
            window.gtag('js', new Date());
            window.gtag('config', id, {
                anonymize_ip: true,
                page_location: pageLocation(true)
            });
            bindNavigateTracking();
        }
        return {
            open: false,
            init: function () {
                var choice = read();
                if (choice === 'all') { loadAnalytics(); return; }
                if (choice === 'essential') return;
                this.open = true;
            },
            choose: function (value) {
                write(value);
                this.open = false;
                if (value === 'all') loadAnalytics();
            }
        };
    }
</script>
@endif

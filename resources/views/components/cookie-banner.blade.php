{{--
    Cookie consent. Analytics storage is granted unless the visitor has chosen
    essential cookies only. The choice lives in localStorage (`pu_cookie_consent`
    = analytics | essential). gtag.js still loads immediately.

    Rendered when a valid GA4 measurement id, a Google Tag Manager container,
    or both are configured, and CookieYes is not supplying the banner. Without
    either Google tag, the site sets essential cookies only, which need no
    consent banner. Loading and Consent Mode live in
    resources/js/analytics-consent.js.
--}}
@if (\App\Support\Analytics::enabled() && blank(config('services.cookieyes.script_id')))
<div id="cookie-banner" data-nosnippet x-data="cookieConsent()" x-init="init()" x-show="open" x-cloak
     class="fixed inset-x-0 bottom-0 z-40 border-t p-4 sm:p-5"
     style="background-color: var(--bg-elevated); border-color: var(--border); box-shadow: 0 -8px 32px rgba(0,0,0,.35);"
     role="region" aria-label="{{ __('Cookie consent') }}">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm leading-relaxed" style="color: var(--text); max-width: 60ch;">
            {{ __('Analytics helps us make the site better. We use anonymous Google Analytics (G-4H1XSCFT9J) to see which planets, missions, and tools people explore most. There is no ad tracking or cross-site profiling. You can keep analytics on, or choose essential cookies only, at any time.') }}
            <a class="link-quiet underline" href="{{ route('privacy') }}">{{ __('Privacy & cookies') }}</a>
        </p>
        <div class="flex shrink-0 gap-2">
            <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium" @click="choose('analytics')"
                    style="background-color: var(--accent-fill); color: var(--on-accent);">{{ __('Accept analytics') }}</button>
            <button type="button" class="rounded-lg border px-4 py-2 text-sm font-medium" @click="choose('essential')"
                    style="border-color: var(--border); color: var(--text);">{{ __('Essential only') }}</button>
        </div>
    </div>
</div>

<script data-navigate-once>
    function cookieConsent() {
        return {
            open: false,
            init: function () {
                var self = this;
                window.addEventListener('cookie-consent-chosen', function () {
                    self.open = false;
                });
                var analytics = window.publicUniverseAnalytics;
                if (!analytics) {
                    return;
                }
                var choice = analytics.read();
                if (choice === 'analytics') {
                    analytics.load();
                    return;
                }
                if (choice === 'essential') {
                    return;
                }
                this.open = true;
            },
            choose: function (value) {
                if (window.publicUniverseAnalytics) {
                    window.publicUniverseAnalytics.choose(value);
                }
                this.open = false;
            }
        };
    }
</script>
@endif

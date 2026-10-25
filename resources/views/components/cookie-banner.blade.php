{{--
    Cookie consent. Analytics storage stays denied until the visitor accepts.
    gtag.js still loads immediately and sends cookieless Consent Mode pings.
    The choice lives in a first-party cookie (`cookie_consent` = essential | all)
    for a year.

    Rendered when a valid GA4 measurement id, a Google Tag Manager container,
    or both are configured. Without either, the site sets essential cookies
    only, which need no consent banner. Loading and Consent Mode live in
    resources/js/analytics-consent.js.
--}}
@if (\App\Support\Analytics::enabled())
<div id="cookie-banner" data-nosnippet x-data="cookieConsent()" x-init="init()" x-show="open" x-cloak
     class="fixed inset-x-0 bottom-0 z-40 border-t p-4 sm:p-5"
     style="background-color: var(--bg-elevated); border-color: var(--border); box-shadow: 0 -8px 32px rgba(0,0,0,.35);"
     role="region" aria-label="{{ __('Cookie consent') }}">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm leading-relaxed" style="color: var(--text); max-width: 60ch;">
            {{ __('We use an essential cookie to remember this choice. Google Analytics 4 loads in cookieless Consent Mode so we can see which pages are useful before you choose. Analytics storage stays off until you accept.') }}
            <a class="link-quiet underline" href="{{ route('privacy') }}">{{ __('Privacy & cookies') }}</a>
        </p>
        <div class="flex shrink-0 gap-2">
            <button type="button" class="rounded-lg border px-4 py-2 text-sm font-medium" @click="choose('essential')"
                    style="border-color: var(--border); color: var(--text);">{{ __('Essential only') }}</button>
            <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium" @click="choose('all')"
                    style="background-color: var(--accent-fill); color: var(--on-accent);">{{ __('Accept analytics') }}</button>
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
                if (choice === 'all') {
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

<div class="mx-auto" style="max-width: var(--container-prose);">
    <x-page-header :title="__('Privacy & cookies')" :eyebrow="config('site.name')"
                   :lead="__('The short version: we collect as little as we can, nothing non-essential runs without your say-so, and you can ask us to delete anything we hold.')" />

    <div class="space-y-6 text-base leading-relaxed" style="color: var(--text);">
        <p class="text-sm" style="color: var(--muted);">{{ __('Last updated') }} {{ \App\Support\Format::date($updated) }}</p>

        <h2 class="pt-2 font-serif text-2xl font-medium">{{ __('Who we are') }}</h2>
        <p>{{ __(':site is run by :operator, a company registered in England and Wales. For anything in this policy, email :email.', ['site' => config('site.name'), 'operator' => $operator, 'email' => $email]) }}</p>

        <h2 class="pt-2 font-serif text-2xl font-medium">{{ __('What we collect, and why') }}</h2>
        <ul class="list-disc space-y-2 pl-5">
            <li><strong>{{ __('Server logs.') }}</strong> {{ __('Our hosting provider records the IP address, requested page, browser type and time of each request, as every web server does. We use these only to keep the site running and to investigate abuse. They are kept for up to 30 days.') }}</li>
            <li><strong>{{ __('Account and alert data.') }}</strong> {{ __('If you create an account, we store your name, email address, password hash and any visibility alerts you save (object and location) so we can notify you when the condition matches. Remove alerts from your alerts page, and contact us to delete your account data.') }}</li>
            <li><strong>{{ __('Optional observing backup.') }}</strong> {{ __('Only when you explicitly upload a preview, your equipment, sites, lists and journal are copied to your account. This can include site coordinates and private notes. The copy is encrypted in our database; the application can decrypt it for you. You can replace or delete it from Private observing backup. A revision marker remains after deletion to prevent older devices silently restoring the deleted copy. Signing in or out does not upload, download or erase browser guest records. Account previews are not persisted in browser storage.') }} <a class="underline" href="{{ route('observing.sync.page') }}">{{ __('Private observing backup') }}</a></li>
            <li><strong>{{ __('Analytics (only with your consent).') }}</strong>
                {{ __('If you accept analytics cookies we load Google Analytics 4. It anonymises IP addresses and tells us which pages people find useful and roughly where in the world they are. We use Google Consent Mode v2: analytics storage (analytics_storage) stays denied until you choose "Accept analytics", and the advertising signals ad_storage, ad_user_data and ad_personalization stay denied, because we do not use analytics for advertising. Until you opt in, and if you choose "Essential only", Google\'s script is not loaded at all. After you opt in, moving between pages is recorded as a page view. The address we send has sensitive parts removed, so a settings link you open never sends the observing location inside it to Google. We do not link analytics to an account.') }}
                @unless ($analyticsEnabled)
                    <em style="color: var(--muted);">{{ __('(Analytics is not currently switched on.)') }}</em>
                @endunless
            </li>
            <li><strong>{{ __('Newsletter.') }}</strong> {{ __('If you sign up, your email address goes straight to Mailchimp, who send you a confirmation link (double opt-in). Nothing is stored on our servers. You can unsubscribe from the link in any email, and we will delete your address on request.') }}</li>
            <li><strong>{{ __('Your location (astronomy tools).') }}</strong> {{ __('Some tools can use your approximate location to tailor sky data. For sky and night calculations, the rounded location is sent to our API without being saved as an account location. Alerts and private backups that you explicitly save can retain locations as described above. Weather checks for the observer panel and explicitly requested night forecasts are made server-side to Open-Meteo from our infrastructure, using the same location rounded to two decimals (about a kilometre), so your IP address is not sent to Open-Meteo. Forecasts are cached for 30 minutes per rounded grid cell and are not tied to a person. A settings link you copy to another device keeps those values in the fragment of the URL (after #), which browsers do not send to our server, to our logs, or as a Referer. Entering a what3words address is optional — the other ways of giving a location do not contact what3words. The weather lookup described above still uses Open-Meteo. Pasting a what3words address into the sky panel sends the three words to what3words to convert them; that lookup is not stored, cached or logged. Locating an observing site on Your observatory is separate and also optional. Our server sends those three words to what3words, returns the coordinates and nearest place for you to confirm, and caches a successful result — the address, the coordinates rounded to about a kilometre, and the nearest place — so the same lookup is not repeated. The cache is not tied to an account. A saved site can show an approximate what3words address for its rounded coordinates when that lookup is already cached or otherwise inexpensive.') }}</li>
        </ul>

        <section id="feedback" class="scroll-mt-24 space-y-3" aria-labelledby="feedback-heading">
            <h2 id="feedback-heading" class="pt-2 font-serif text-2xl font-medium">{{ __('Feedback and contact') }}</h2>
            <p>{{ __('The feedback form sends your chosen topic, message and optional reply email to our private inbox at hello@publicuniverse.net through our email delivery provider. Submissions are not published or used for newsletter signups. We do not automatically attach your observing location, equipment, journal or account details.') }}</p>
            <p>{{ __('We use these messages to respond to enquiries and improve the site. They remain in our mailbox while needed to handle the enquiry and related follow-up. Contact hello@publicuniverse.net to ask us to remove your message. If delivery fails or validation finds an error, your form fields are temporarily kept in your session so you can correct and resend them; they are not stored as feedback records in our database.') }}</p>
            <p>{{ __('The form uses an essential session cookie, CSRF protection and a short-lived request limit keyed to your connection to reduce spam. Normal web-server access logs still apply. Please avoid including passwords, exact observing locations or other sensitive details in your message.') }}</p>
        </section>

        <h2 class="pt-2 font-serif text-2xl font-medium">{{ __('Cookies and local storage') }}</h2>
        <div class="surface overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-left" style="border-color: var(--border); color: var(--muted);">
                        <th class="px-4 py-3 font-medium">{{ __('Name') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Purpose') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Lifetime') }}</th>
                    </tr>
                </thead>
                <tbody style="color: var(--text);">
                    <tr class="border-b" style="border-color: var(--border);"><td class="px-4 py-3 font-mono text-xs">cookie_consent</td><td class="px-4 py-3">{{ __('Remembers your cookie choice (essential).') }}</td><td class="px-4 py-3">{{ __('1 year') }}</td></tr>
                    <tr class="border-b" style="border-color: var(--border);"><td class="px-4 py-3 font-mono text-xs">{{ config('session.cookie') }}</td><td class="px-4 py-3">{{ __('Keeps interactive pages working between requests (essential).') }}</td><td class="px-4 py-3">{{ __(':minutes minutes after the last request', ['minutes' => config('session.lifetime')]) }}</td></tr>
                    <tr class="border-b" style="border-color: var(--border);"><td class="px-4 py-3 font-mono text-xs">XSRF-TOKEN</td><td class="px-4 py-3">{{ __('Protects forms against cross-site request forgery (essential).') }}</td><td class="px-4 py-3">{{ __(':minutes minutes after the last request', ['minutes' => config('session.lifetime')]) }}</td></tr>
                    <tr class="border-b" style="border-color: var(--border);"><td class="px-4 py-3 font-mono text-xs">theme</td><td class="px-4 py-3">{{ __('Local storage, not a cookie: your light/dark choice. Never leaves your browser.') }}</td><td class="px-4 py-3">{{ __('Until cleared') }}</td></tr>
                    <tr class="border-b" style="border-color: var(--border);"><td class="px-4 py-3 font-mono text-xs">observer_location</td><td class="px-4 py-3">{{ __('Local storage: your observing location, rounded to about a kilometre, so you don\'t re-enter it on every object page. Sent to our API only for the sky calculation; never stored there.') }}</td><td class="px-4 py-3">{{ __('Until cleared') }}</td></tr>
                    <tr class="border-b" style="border-color: var(--border);"><td class="px-4 py-3 font-mono text-xs">public_universe_observing_v1</td><td class="px-4 py-3">{{ __('Local storage: named equipment and observing sites. Coordinates are rounded to two decimal places. Manage, export or delete this workspace in Your observatory. It is not uploaded on sign-in or included in settings links. Using a site copies its coordinates to the independent observing location setting.') }}</td><td class="px-4 py-3">{{ __('Until cleared') }}</td></tr>
                    <tr class="border-b" style="border-color: var(--border);"><td class="px-4 py-3 font-mono text-xs">public_universe_journal_v1</td><td class="px-4 py-3">{{ __('Local storage: observing lists, actual observation times, notes and optional equipment/site snapshots. These remain in this browser unless you explicitly export or upload a private backup; signing in does not upload them. Manage, export or remove them in your observing journal. Exported notes can contain private information even when site fields are omitted.') }}</td><td class="px-4 py-3">{{ __('Until removed or browser storage is cleared') }}</td></tr>
                    <tr class="border-b" style="border-color: var(--border);"><td class="px-4 py-3 font-mono text-xs">preferences</td><td class="px-4 py-3">{{ __('Local storage: display preferences such as 12- or 24-hour times. Never leaves your browser.') }}</td><td class="px-4 py-3">{{ __('Until cleared') }}</td></tr>
                    <tr><td class="px-4 py-3 font-mono text-xs">_ga, _ga_*</td><td class="px-4 py-3">{{ __('Google Analytics 4, set only after you accept analytics.') }}</td><td class="px-4 py-3">{{ __('Up to 2 years') }}</td></tr>
                </tbody>
            </table>
        </div>
        <p>{{ __('You can change your mind at any time. Choosing "Essential only" stops analytics and removes Google Analytics cookies from this browser. If analytics was already running, the page reloads so Google\'s script is not kept. Clearing the cookie_consent cookie makes the banner ask again on your next visit.') }}
           {{ __('Browser settings and a link to manage your private observing workspace are listed on') }} <a class="link-quiet underline" href="{{ route('settings') }}">{{ __('Your settings') }}</a>. {{ __('Account and alert records are separate from browser storage and are used only for sign-in and notifications.') }}</p>
        @if ($analyticsEnabled)
            <div x-data class="flex flex-wrap gap-2">
                <button type="button" class="inline-flex min-h-11 items-center rounded-lg border px-4 py-2 text-sm font-medium" style="border-color: var(--border); color: var(--text);" @click="window.publicUniverseAnalytics && window.publicUniverseAnalytics.choose('essential')">{{ __('Essential only') }}</button>
                <button type="button" class="inline-flex min-h-11 items-center rounded-lg px-4 py-2 text-sm font-medium" style="background-color: var(--accent-fill); color: var(--on-accent);" @click="window.publicUniverseAnalytics && window.publicUniverseAnalytics.choose('all')">{{ __('Accept analytics') }}</button>
            </div>
        @endif

        <h2 class="pt-2 font-serif text-2xl font-medium">{{ __('Third parties') }}</h2>
        <ul class="list-disc space-y-2 pl-5">
            <li><strong>Google Analytics 4</strong> — {{ __('usage statistics, only after you opt in. Consent Mode keeps analytics storage denied until then.') }} <a class="link-quiet underline" href="https://policies.google.com/privacy" rel="noopener" target="_blank">{{ __('Google privacy policy') }}</a></li>
            <li><strong>Open-Meteo</strong> — {{ __('hourly cloud, visibility, humidity and wind forecasts for observer outlooks and optional night forecasts.') }} <a class="link-quiet underline" href="https://open-meteo.com/en/docs" rel="noopener" target="_blank">{{ __('Open-Meteo docs') }}</a></li>
            <li><strong>what3words</strong> — {{ __('converting a what3words address you enter into coordinates, and an approximate address for a saved site’s rounded coordinates.') }} <a class="link-quiet underline" href="https://what3words.com/privacy" rel="noopener" target="_blank">{{ __('what3words privacy policy') }}</a></li>
            <li><strong>Mailchimp</strong> (Intuit) — {{ __('newsletter delivery.') }} <a class="link-quiet underline" href="https://www.intuit.com/privacy/statement/" rel="noopener" target="_blank">{{ __('Mailchimp privacy statement') }}</a></li>
            <li><strong>NASA / JPL, IAU Minor Planet Center</strong> — {{ __('the astronomical data itself. No personal data is exchanged.') }}</li>
        </ul>

        <h2 class="pt-2 font-serif text-2xl font-medium">{{ __('Your rights') }}</h2>
        <p>{{ __('Under UK GDPR and the Data Protection Act 2018 you can ask what we hold about you, have it corrected or deleted, object to processing, or withdraw consent. Email :email and we will respond within one month. If you are unhappy with how we handle it, you can complain to the Information Commissioner\'s Office (ico.org.uk).', ['email' => $email]) }}</p>

        <h2 class="pt-2 font-serif text-2xl font-medium">{{ __('Changes') }}</h2>
        <p>{{ __('If this policy changes in a way that matters, we will update the date above and, where we can, say so on the site.') }}</p>
    </div>
</div>

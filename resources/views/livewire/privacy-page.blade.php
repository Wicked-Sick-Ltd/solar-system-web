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
            <li><strong>{{ __('Analytics (only with your consent).') }}</strong>
                {{ __('If you accept analytics cookies we load Google Analytics 4 with IP anonymisation, which tells us which pages people find useful and roughly where in the world they are. We do not use it for advertising and we do not link it to any account. If you choose "essential only", nothing from Google is loaded at all.') }}
                @unless ($analyticsEnabled)
                    <em style="color: var(--muted);">{{ __('(Analytics is not currently switched on.)') }}</em>
                @endunless
            </li>
            <li><strong>{{ __('Newsletter.') }}</strong> {{ __('If you sign up, your email address goes straight to Mailchimp, who send you a confirmation link (double opt-in). Nothing is stored on our servers. You can unsubscribe from the link in any email, and we will delete your address on request.') }}</li>
            <li><strong>{{ __('Your location (astronomy tools).') }}</strong> {{ __('Some tools can use your approximate location to tailor sky data. If you allow it, the location stays in your browser and is sent to our API only for that calculation; it is not stored or logged with anything that identifies you. Entering a what3words address is optional — the other ways of giving a location involve no third party. If you do use one, the three words are sent to what3words to convert them to coordinates; we do not store, cache or log the address or the result.') }}</li>
        </ul>

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
                    <tr><td class="px-4 py-3 font-mono text-xs">_ga, _ga_*</td><td class="px-4 py-3">{{ __('Google Analytics, set only after you accept analytics.') }}</td><td class="px-4 py-3">{{ __('Up to 2 years') }}</td></tr>
                </tbody>
            </table>
        </div>
        <p>{{ __('You can change your mind at any time by clearing the cookie_consent cookie in your browser; the banner will ask again on your next visit.') }}</p>

        <h2 class="pt-2 font-serif text-2xl font-medium">{{ __('Third parties') }}</h2>
        <ul class="list-disc space-y-2 pl-5">
            <li><strong>Google Analytics</strong> — {{ __('usage statistics, only with consent.') }} <a class="link-quiet underline" href="https://policies.google.com/privacy" rel="noopener" target="_blank">{{ __('Google privacy policy') }}</a></li>
            <li><strong>what3words</strong> — {{ __('converting a what3words address you paste into coordinates.') }} <a class="link-quiet underline" href="https://what3words.com/privacy" rel="noopener" target="_blank">{{ __('what3words privacy policy') }}</a></li>
            <li><strong>Mailchimp</strong> (Intuit) — {{ __('newsletter delivery.') }} <a class="link-quiet underline" href="https://www.intuit.com/privacy/statement/" rel="noopener" target="_blank">{{ __('Mailchimp privacy statement') }}</a></li>
            <li><strong>NASA / JPL, IAU Minor Planet Center</strong> — {{ __('the astronomical data itself. No personal data is exchanged.') }}</li>
        </ul>

        <h2 class="pt-2 font-serif text-2xl font-medium">{{ __('Your rights') }}</h2>
        <p>{{ __('Under UK GDPR and the Data Protection Act 2018 you can ask what we hold about you, have it corrected or deleted, object to processing, or withdraw consent. Email :email and we will respond within one month. If you are unhappy with how we handle it, you can complain to the Information Commissioner\'s Office (ico.org.uk).', ['email' => $email]) }}</p>

        <h2 class="pt-2 font-serif text-2xl font-medium">{{ __('Changes') }}</h2>
        <p>{{ __('If this policy changes in a way that matters, we will update the date above and, where we can, say so on the site.') }}</p>
    </div>
</div>

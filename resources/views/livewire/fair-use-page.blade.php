<div class="mx-auto" style="max-width: var(--container-prose);">
    <x-page-header :title="__('Fair use')" :eyebrow="config('site.name')"
        :lead="__('This site and its catalogue are free for everyone. Please take what you need for reading, teaching and research, and use an API key when a program does the asking.')" />

    <div class="space-y-6 text-base leading-relaxed">
        <h2 class="font-serif text-2xl font-medium">{{ __('Free for everyone') }}</h2>
        <p>{{ __('You can browse Public Universe, follow links, and read the pages without an account. Generous anonymous limits cover ordinary visits and light API use. The website itself is not a bulk download path.') }}</p>

        <h2 class="pt-2 font-serif text-2xl font-medium">{{ __('Anonymous API limits') }}</h2>
        <p>{{ __('Calls with no key are limited per IP address: 60 requests per minute and 1,000 per day. That budget is for people and small scripts. It is not a feed for a crawler that walks the whole catalogue.') }}</p>

        <h2 class="pt-2 font-serif text-2xl font-medium">{{ __('Bulk and automated use needs a key') }}</h2>
        <p>{{ __('Programs that download many records, mirror the catalogue, or call the API on a schedule must send a key in the X-PU-Api-Key request header. Do not put the key in a query string. A free key has a higher daily limit. An institution key is for universities, schools, museums and similar organisations, and defaults to 100,000 calls per day for that key. That ceiling is a configuration value and may change.') }}</p>
        <p>{{ __('A missing key, a blank header, an unknown key and a revoked key are all treated as anonymous traffic. They use the per-IP anonymous limit, and bad keys are also rate-limited per IP so guesses cannot run unchecked. The API does not answer those calls with a special “invalid key” error. When a limit is exceeded the response is HTTP 429 with a Retry-After header and a link back to the access form.') }}</p>
        <p>{{ __('Cloudflare may skip its own edge limit when a request merely carries X-PU-Api-Key. The application still checks the key. Sending a header is not the same as holding a valid key.') }}</p>
        <p><a class="underline" href="{{ route('api-access') }}">{{ __('Request a key') }}</a>
            · <a class="underline" href="{{ route('api') }}">{{ __('API reference') }}</a></p>

        <h2 class="pt-2 font-serif text-2xl font-medium">{{ __('Search and AI crawlers are welcome') }}</h2>
        <p>{{ __('Verified search engines and recognised AI crawlers may read the public pages. robots.txt does not block them, and it does not block the sitemap or itself. Please use the sitemap instead of guessing URLs, and prefer the API or the database download when you need the catalogue in bulk rather than the HTML pages.') }}</p>
        <p>{{ __('Interactive MCP clients can keep using the public MCP endpoint without a key. That endpoint is for conversations and small lookups, not for scraping the catalogue.') }}</p>
    </div>
</div>

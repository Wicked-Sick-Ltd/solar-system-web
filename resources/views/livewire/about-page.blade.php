<div class="mx-auto" style="max-width: var(--container-prose);">
    <x-page-header :title="__('About this site')" :eyebrow="config('site.name')" />

    <div class="space-y-6 text-base leading-relaxed" style="color: var(--text);">
        <p>
            {{ config('site.name') }} {{ __('is a clean, public reference for the solar system: a place to look up the planets, their moons, the dwarf planets, and the great swarm of asteroids, comets and icy worlds beyond Neptune. It is built for the curious — students, hobbyists, educators and sci-fi worldbuilders alike.') }}
        </p>
        <p class="rounded-lg border-l-2 px-4 py-2" style="border-color: var(--accent); color: var(--muted);">
            {{ __('This is an astronomy reference, not an astrology site. There are no horoscopes, houses or star signs here — just the physical bodies of the solar system and the measured facts about them.') }}
        </p>

        <h2 class="pt-2 font-serif text-2xl font-medium">{{ __('Where the data comes from') }}</h2>
        <p>
            {{ __('Everything is drawn from open, authoritative sources — chiefly NASA / JPL and the IAU Minor Planet Center — and refreshed nightly. NASA data is generally in the public domain; the Minor Planet Center data is freely usable. You are welcome to use it too.') }}
        </p>

        @if (count($sources))
            <ul class="surface divide-y text-sm" style="border-color: var(--border);">
                @foreach ($sources as $source)
                    <li class="flex items-center justify-between gap-4 px-4 py-3" style="border-color: var(--border);">
                        @if ($source->sourceUrl)
                            <a class="link-quiet underline" href="{{ $source->sourceUrl }}" rel="noopener" target="_blank">{{ $source->sourceName }}</a>
                        @else
                            <span style="color: var(--text);">{{ $source->sourceName }}</span>
                        @endif
                        @if ($source->count)
                            <span class="tabular-nums" style="color: var(--muted);">{{ \App\Support\Format::count($source->count) }} {{ __('records') }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        <h2 class="pt-2 font-serif text-2xl font-medium">{{ __('Use the API yourself') }}</h2>
        <p>
            {{ __('This whole site is just a front end over a free, read-only REST API. You can query the same data directly — see the') }}
            <a class="underline" style="color: var(--link);" href="{{ route('api') }}">{{ __('developer page') }}</a>
            {{ __('for a worked example, or browse the') }}
            <a class="underline" style="color: var(--link);" href="{{ \App\Support\Links::apiDocs() }}" rel="noopener" target="_blank">{{ __('interactive API documentation') }}</a>.
        </p>

        <h2 class="pt-2 font-serif text-2xl font-medium">{{ __('Download the whole database') }}</h2>
        <p>
            {{ __('Everything behind this site — every body in the JPL Small-Body Database with every published field, discoveries, close approaches, satellites and the NASA fact sheets — is rebuilt nightly and published as one compressed SQLite file with a manifest, checksum and licence.') }}
            <a class="underline" style="color: var(--link);" href="{{ config('site.download_url') }}" rel="noopener" target="_blank">{{ __('Get the latest manifest') }}</a>.
        </p>

        <h2 class="pt-2 font-serif text-2xl font-medium">{{ __('Credits & source code') }}</h2>
        <p>
            {{ __('The catalogue and API are open source.') }}
            <a class="underline" style="color: var(--link);" href="{{ config('site.backend_repo') }}" rel="noopener" target="_blank">{{ __('Browse the backend repository on GitHub') }}</a>.
        </p>

        <h2 class="pt-2 font-serif text-2xl font-medium">{{ __('Spotted an error?') }}</h2>
        <p>
            {{ __('Astronomical data is always being refined. If something looks wrong, please let us know:') }}
            <a class="underline" style="color: var(--link);" href="mailto:{{ config('site.contact_email') }}?subject={{ rawurlencode('Solar — data correction') }}">{{ config('site.contact_email') }}</a>.
        </p>
    </div>
</div>

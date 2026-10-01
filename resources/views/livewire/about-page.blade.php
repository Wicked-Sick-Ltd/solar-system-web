<div class="mx-auto" style="max-width: var(--container-prose);">
    <x-page-header :title="__('About this site')" :eyebrow="config('site.name')" />

    <div class="space-y-6 text-base leading-relaxed" style="color: var(--text);">
        <p>
            {{ config('site.name') }} {{ __('is a free astronomy reference for everyone, from children exploring their first planets to scientists working with catalogue data. Discover planets, moons, asteroids and comets in our solar system, exoplanets around other stars, and interactive views of known planetary systems in the galaxy. Browsing and learning need no account.') }}
        </p>
        <p class="rounded-lg border-l-2 px-4 py-2" style="border-color: var(--accent); color: var(--muted);">
            {{ __('This is an astronomy reference: physical worlds, measured properties and scientific discoveries. The galaxy explorer plots known exoplanet hosts against a schematic galactic disk; it is not a complete map of the stars.') }}
        </p>

        <h2 class="pt-2 font-serif text-2xl font-medium">{{ __('Where the data comes from') }}</h2>
        <p>
            {{ __('The catalogue brings together data from NASA and JPL, the NASA Exoplanet Archive, the IAU Minor Planet Center and other astronomical organisations. Source coverage and refresh times vary. Follow the source references and check the original data, attribution requirements and reuse terms when using a measurement in your own work. The code licence does not replace the terms of individual datasets.') }}
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
            {{ __('The published catalogue is available as a compressed SQLite database for offline exploration and research. The latest manifest records the available build and its checksum; check its timestamp, accompanying licence and source metadata before use. A nightly publishing schedule does not mean every upstream measurement was updated that day.') }}
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
            <a class="underline" style="color: var(--link);" href="mailto:{{ config('site.contact_email') }}?subject={{ rawurlencode(config('site.name').' — data correction') }}">{{ config('site.contact_email') }}</a>.
        </p>
    </div>
</div>

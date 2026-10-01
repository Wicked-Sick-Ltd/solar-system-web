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

        <section class="surface space-y-4 p-4" aria-labelledby="catalogue-version-heading">
            <h3 id="catalogue-version-heading" class="text-lg font-semibold">{{ __('Catalogue versions and checksums') }}</h3>
            @if ($catalogueIdentity->known())
                <p>{{ __('The API reports this catalogue identity. Website caches check for changes about once a minute; a separate page response is not an atomically pinned snapshot.') }}</p>
                <dl class="space-y-2 text-sm">
                    <div><dt class="font-semibold">{{ __('API logical catalogue ID') }}</dt><dd class="break-all font-mono">{{ $catalogueIdentity->catalogueId }}</dd></div>
                    <div><dt class="font-semibold">{{ __('API build ID') }}</dt><dd class="break-all font-mono">{{ $catalogueIdentity->buildIdentifier }}</dd></div>
                    <div><dt class="font-semibold">{{ __('Recorded build finish (UTC offset included)') }}</dt><dd>{{ $catalogueIdentity->builtAt }}</dd></div>
                </dl>
            @else
                <p>{{ __('The current API catalogue identity is unknown or unavailable. Older backends may not record it; a date or object count cannot establish a snapshot ID.') }}</p>
            @endif
            @if ($downloadManifest)
                <p class="font-semibold">{{ __('API-reported downloadable file') }}: <span class="break-all font-mono">{{ $downloadManifest->artifact }}</span></p>
                <dl class="space-y-2 text-sm">
                    <div><dt class="font-semibold">{{ __('Compressed file SHA-256') }}</dt><dd class="break-all font-mono">{{ $downloadManifest->compressedSha256 }}</dd></div>
                    @if ($downloadManifest->sqliteSha256)
                        <div><dt class="font-semibold">{{ __('Uncompressed SQLite SHA-256') }}</dt><dd class="break-all font-mono">{{ $downloadManifest->sqliteSha256 }}</dd></div>
                    @endif
                    @if ($downloadManifest->identity->known())
                        <div><dt class="font-semibold">{{ __('Download logical catalogue ID') }}</dt><dd class="break-all font-mono">{{ $downloadManifest->identity->catalogueId }}</dd></div>
                        <div><dt class="font-semibold">{{ __('Download build ID') }}</dt><dd class="break-all font-mono">{{ $downloadManifest->identity->buildIdentifier }}</dd></div>
                    @endif
                </dl>
                @if ($catalogueIdentity->known() && $downloadManifest->identity->known())
                    @if ($catalogueIdentity->buildIdentifier === $downloadManifest->identity->buildIdentifier)
                        <p>{{ __('The latest observed API and download metadata report the same build ID. Recheck the manifest when downloading; it can change independently.') }}</p>
                    @elseif ($catalogueIdentity->catalogueId === $downloadManifest->identity->catalogueId)
                        <p>{{ __('The API and download report the same logical data ID but different build provenance. They are not the same build.') }}</p>
                    @else
                        <p>{{ __('The API and downloadable catalogue currently report different data IDs. The downloadable file may be ahead of or behind the API; do not treat them as the same snapshot.') }}</p>
                    @endif
                @else
                    <p>{{ __('A shared snapshot cannot be established because at least one catalogue identity is unknown.') }}</p>
                @endif
            @else
                <p>{{ __('Download version metadata is unavailable here. Use the manifest link and inspect its recorded checksums before using a downloaded file.') }}</p>
            @endif
            <p class="text-sm" style="color: var(--muted);">{{ __('Logical data IDs, build IDs and file checksums serve different purposes. Keep the downloaded file and its manifest together, including source metadata and licences. Check the actual manifest you download against these values: configured download links can differ from the API-reported source. IDs do not guarantee that historical files remain hosted, and absent upstream source versions remain unknown.') }}</p>
        </section>

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

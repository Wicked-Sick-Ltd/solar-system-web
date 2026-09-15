<footer class="border-t" style="border-color: var(--border);">
    <div class="mx-auto grid w-full max-w-6xl gap-8 px-4 py-12 sm:px-6 sm:grid-cols-2 lg:grid-cols-4 lg:px-8">
        <div class="sm:col-span-2 lg:col-span-1">
            <div class="flex items-center gap-2.5">
                <span aria-hidden="true" class="inline-block h-3 w-3 rounded-full"
                      style="background: radial-gradient(circle at 30% 30%, var(--color-amber-soft), var(--accent) 60%, #8a6a2a);"></span>
                <span class="wordmark text-lg" style="color: var(--text);">{{ config('site.name') }}</span>
            </div>
            <p class="mt-3 max-w-xs text-sm leading-relaxed" style="color: var(--muted);">
                {{ config('site.tagline') }}. {{ __('Built on open data from NASA/JPL and the IAU Minor Planet Center.') }}
            </p>
        </div>

        <nav aria-label="{{ __('Browse') }}">
            <h2 class="text-xs font-semibold uppercase tracking-wider" style="color: var(--muted);">{{ __('Browse') }}</h2>
            <ul class="mt-3 space-y-2 text-sm">
                <li><a class="link-quiet" href="{{ route('planets.index') }}">{{ __('Planets') }}</a></li>
                <li><a class="link-quiet" href="{{ route('dwarf-planets') }}">{{ __('Dwarf planets') }}</a></li>
                <li><a class="link-quiet" href="{{ route('asteroids') }}">{{ __('Asteroids') }}</a></li>
                <li><a class="link-quiet" href="{{ route('comets') }}">{{ __('Comets') }}</a></li>
                <li><a class="link-quiet" href="{{ route('tnos') }}">{{ __('Trans-Neptunian objects') }}</a></li>
                <li><a class="link-quiet" href="{{ route('objects.index') }}">{{ __('All objects') }}</a></li>
            </ul>
        </nav>

        <nav aria-label="{{ __('This site') }}">
            <h2 class="text-xs font-semibold uppercase tracking-wider" style="color: var(--muted);">{{ __('This site') }}</h2>
            <ul class="mt-3 space-y-2 text-sm">
                <li><a class="link-quiet" href="{{ route('orrery') }}">{{ __('Orrery') }}</a></li>
                <li><a class="link-quiet" href="{{ route('about') }}">{{ __('About & data sources') }}</a></li>
                <li><a class="link-quiet" href="{{ route('api') }}">{{ __('Use the API') }}</a></li>
                <li><a class="link-quiet" href="{{ route('random') }}">{{ __('Random object') }}</a></li>
            </ul>
        </nav>

        <nav aria-label="{{ __('Open source') }}">
            <h2 class="text-xs font-semibold uppercase tracking-wider" style="color: var(--muted);">{{ __('Open data') }}</h2>
            <ul class="mt-3 space-y-2 text-sm">
                <li><a class="link-quiet" href="{{ config('site.backend_repo') }}" rel="noopener" target="_blank">{{ __('Backend repository') }}</a></li>
                <li><a class="link-quiet" href="{{ \App\Support\Links::apiDocs() }}" rel="noopener" target="_blank">{{ __('API documentation') }}</a></li>
                <li><a class="link-quiet" href="{{ config('site.download_url') }}" rel="noopener" target="_blank">{{ __('Download the whole database') }}</a></li>
            </ul>
        </nav>
    </div>

    <div class="border-t" style="border-color: var(--border);">
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-2 px-4 py-6 text-xs sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8"
             style="color: var(--muted);">
            <p>
                {{ __('Data in the public domain / freely usable. This is an astronomy reference — not astrology.') }}
            </p>
            <p>&copy; {{ now()->year }} {{ config('site.name') }}.</p>
        </div>
    </div>
</footer>

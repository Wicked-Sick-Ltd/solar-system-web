<x-layouts.app>
    <x-page-header :title="__('Measured exoplanet systems')" :eyebrow="__('Host directory')"
        :lead="__('Find measured planetary systems by name or distance. Explore their planets, or locate them on the galaxy map.')" />

    <form action="{{ route('systems.index') }}" method="get" role="search" class="surface mb-6 grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4" aria-label="{{ __('Filter measured host systems') }}">
        <div class="min-w-0">
            <label for="systems-q" class="mb-2 block text-sm">{{ __('Host name contains') }}</label>
            <input id="systems-q" name="q" type="search" maxlength="200" value="{{ $values['q'] }}" placeholder="TRAPPIST-1" class="w-full min-w-0 rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border); background: var(--bg);">
        </div>
        <div class="min-w-0">
            <label for="systems-radius" class="mb-2 block text-sm">{{ __('Distance from the Sun') }}</label>
            <x-form-select id="systems-radius" name="radius" :selected="$values['radius']" :options="['all' => __('All returned distances'), '25' => __('Within 25 parsecs'), '100' => __('Within 100 parsecs'), '1000' => __('Within 1,000 parsecs')]" class="min-w-0 focus-visible:ring-2 focus-visible:ring-[var(--accent)]" />
        </div>
        <div class="min-w-0">
            <label for="systems-order" class="mb-2 block text-sm">{{ __('Sort by') }}</label>
            <x-form-select id="systems-order" name="order" :selected="$values['order']" :options="['distance' => __('Nearest first'), 'name' => __('Name')]" class="min-w-0 focus-visible:ring-2 focus-visible:ring-[var(--accent)]" />
        </div>
        <div class="flex flex-wrap items-end gap-4">
            <button type="submit" class="rounded-lg border px-4 py-2 text-sm font-medium" style="border-color: var(--border);">{{ __('Apply filters') }}</button>
            <a href="{{ route('systems.index') }}" class="rounded py-2 text-sm underline">{{ __('Reset') }}</a>
        </div>
    </form>

    <div class="mb-8 space-y-3 text-sm" style="color: var(--muted);">
        <p>{{ __('This directory covers measured exoplanet hosts in the available map sample. Hosts without usable positions are omitted.') }}</p>
        <details class="surface p-4">
            <summary class="cursor-pointer font-medium">{{ __('About this sample and its measurements') }}</summary>
            <div class="mt-3 space-y-3">
        <p>{{ __('The known sample is incomplete and unevenly observed. Hosts without a usable measured position are not listed here. Name and distance filters apply only to the sample returned by the map, not to all stars or all exoplanet hosts.') }}</p>
        <p>{{ __('Distances and available uncertainties are in parsecs; light-years are also shown. A missing uncertainty is unknown, not zero. Recorded planet counts describe the catalogue, not a complete census.') }}</p>
            <p>{{ __('Distance filters use the reported value, not its uncertainty range. Catalogue updates can change results between pages.') }}</p>
            <p>{{ __('Source:') }} <a class="underline" href="https://exoplanetarchive.ipac.caltech.edu/docs/PSCompPars.html">{{ __('NASA Exoplanet Archive, Planetary Systems Composite Parameters (PSCompPars)') }}</a></p>
            </div>
        </details>
        @if ($map)
            <p>{{ __(':count hosts returned by the map.', ['count' => \App\Support\Format::count(count($map->hosts))]) }}</p>
            @if ($map->unmappedHosts === null)
                <p>{{ __('The number of catalogue hosts without a usable measured position is unknown in this response.') }}</p>
            @else
                <p>{{ __(':unmapped catalogue hosts lack a usable measured position.', ['unmapped' => \App\Support\Format::count($map->unmappedHosts)]) }}</p>
            @endif
            @if ($map->truncated === true)
                <p role="status" class="surface p-4">{{ __('The map response is truncated. Additional measured hosts may exist beyond this returned sample, so a search here may miss them.') }}</p>
            @elseif ($map->truncated === null)
                <p role="status" class="surface p-4">{{ __('This response does not say whether the map sample was limited. Additional measured hosts may be omitted.') }}</p>
            @endif
        @endif
        <p><a href="{{ route('galaxy') }}" class="underline">{{ __('Open galaxy explorer') }}</a> · <a href="{{ route('exoplanets.index') }}" class="underline">{{ __('Search the exoplanet catalogue') }}</a></p>
    </div>

    @if ($errors !== [])
        <div role="alert" class="surface space-y-3 p-5">
            @foreach ($errors as $error)<p>{{ $error }}</p>@endforeach
            <p>{{ __('Correct the filters above and apply them to start from the first page.') }}</p>
            @if ($filters)
                <a href="{{ route('systems.index', $filters->query(1)) }}" class="inline-block underline">{{ __('First page with these filters') }}</a>
            @endif
        </div>
    @elseif ($apiDown)
        <x-api-down :section="__('The measured host directory')" />
    @elseif ($selection && $filters)
        @if ($selection->total === 0)
            <x-empty-state :title="__('No measured hosts match these filters in the returned sample')">
                {{ __('Try another name, increase the distance or reset the filters. Unmapped or omitted hosts may still appear in the exoplanet catalogue.') }}
            </x-empty-state>
        @else
            <p class="mb-4 text-sm" style="color: var(--muted);">{{ trans_choice(':count matching host in this returned sample · page :page of :pages|:count matching hosts in this returned sample · page :page of :pages', $selection->total, ['count' => \App\Support\Format::count($selection->total), 'page' => $filters->page, 'pages' => $selection->pages]) }}</p>
            <ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-label="{{ __('Measured host systems') }}">
                @foreach ($selection->hosts as $host)
                    <li class="surface min-w-0 space-y-4 p-5">
                        <h2 class="break-words text-lg font-semibold"><a href="{{ route('systems.show', $host->id) }}" class="rounded underline decoration-transparent underline-offset-4 hover:decoration-current focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--accent)]">{{ $host->name }}</a></h2>
                        <dl class="space-y-2 text-sm">
                            <div><dt style="color: var(--muted);">{{ __('Distance from the Sun') }}</dt><dd>{{ \App\Support\Format::scientific($host->distancePc, 4) }} pc <span style="color: var(--muted);">({{ \App\Support\Format::lightYears($host->distancePc) }})</span></dd></div>
                            <div><dt style="color: var(--muted);">{{ __('Distance uncertainty') }}</dt><dd class="break-words">
                                {{ __('Upper:') }} {{ $host->distancePlusPc === null ? __('unknown') : '+'.\App\Support\Format::scientific(abs($host->distancePlusPc), 4).' pc' }};
                                {{ __('lower:') }} {{ $host->distanceMinusPc === null ? __('unknown') : '−'.\App\Support\Format::scientific(abs($host->distanceMinusPc), 4).' pc' }}
                            </dd></div>
                            <div><dt style="color: var(--muted);">{{ __('Recorded planets') }}</dt><dd>{{ \App\Support\Format::count($host->planetCount) }}</dd></div>
                        </dl>
                        <a href="{{ route('galaxy', ['host' => $host->id]) }}" class="inline-block max-w-full break-words rounded text-sm underline">{{ __('Locate :name on map', ['name' => $host->name]) }}</a>
                    </li>
                @endforeach
            </ul>
            <nav class="mt-8 flex flex-wrap items-center justify-between gap-4 text-sm" aria-label="{{ __('Host directory pages') }}">
                @if ($filters->page > 1)
                    <a href="{{ route('systems.index', $filters->query($filters->page - 1)) }}" rel="prev" class="rounded-lg border px-4 py-2" style="border-color: var(--border);">← {{ __('Previous') }}</a>
                @else
                    <span></span>
                @endif
                <span>{{ __('Page :page of :pages', ['page' => $filters->page, 'pages' => $selection->pages]) }}</span>
                @if ($filters->page < $selection->pages)
                    <a href="{{ route('systems.index', $filters->query($filters->page + 1)) }}" rel="next" class="rounded-lg border px-4 py-2" style="border-color: var(--border);">{{ __('Next') }} →</a>
                @endif
            </nav>
        @endif
    @endif
</x-layouts.app>

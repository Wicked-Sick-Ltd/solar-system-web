<div>
    <x-page-header :title="__('Exoplanets')" :eyebrow="__('Beyond the Sun')"
        :lead="__('Confirmed planets from the NASA Exoplanet Archive. Explore a system, or locate its host on the galaxy map.')" />
    <a href="{{ route('galaxy') }}" class="mb-6 inline-block underline">{{ __('Open galaxy explorer') }} →</a>
    <form action="{{ route('exoplanets.index') }}" method="get" wire:submit="applyFilters" aria-label="{{ __('Exoplanet filters') }}" class="surface mb-8 grid gap-4 p-5 sm:grid-cols-2 xl:grid-cols-4">
        <div><label for="exo-search" class="mb-2 block text-sm">{{ __('Planet or host name') }}</label>
            <input id="exo-search" name="q" type="search" value="{{ $displayFilters['q'] }}" maxlength="200" wire:model.live.debounce.400ms="q" class="w-full rounded border p-2" style="border-color: var(--border); background: var(--bg)" placeholder="TRAPPIST-1"></div>
        <div><label for="exo-method" class="mb-2 block text-sm">{{ __('Discovery method') }}</label>
            <x-form-select id="exo-method" name="method" wire:model.live="method" :selected="$displayFilters['method']" class="focus-visible:ring-2 focus-visible:ring-[var(--accent)]" :options="['' => __('Any method'), 'Transit' => __('Transit'), 'Radial Velocity' => __('Radial velocity'), 'Imaging' => __('Imaging'), 'Microlensing' => __('Microlensing'), 'Transit Timing Variations' => __('Transit timing variations'), 'Astrometry' => __('Astrometry'), 'Eclipse Timing Variations' => __('Eclipse timing variations'), 'Pulsar Timing' => __('Pulsar timing'), 'Pulsation Timing Variations' => __('Pulsation timing variations'), 'Orbital Brightness Modulation' => __('Orbital brightness modulation'), 'Disk Kinematics' => __('Disk kinematics')] + ($displayFilters['method'] !== '' ? [$displayFilters['method'] => $displayFilters['method']] : [])" /></div>
        <div><label for="exo-distance" class="mb-2 block text-sm">{{ __('Distance from the Sun') }}</label>
            <x-form-select id="exo-distance" name="distance" wire:model.live="distance" :selected="$displayFilters['distance']" class="focus-visible:ring-2 focus-visible:ring-[var(--accent)]" :options="['' => __('Any distance'), '10' => __('Within 33 light-years'), '25' => __('Within 82 light-years'), '100' => __('Within 326 light-years'), '1000' => __('Within 3,262 light-years')]" /></div>
        <div class="flex flex-wrap items-end gap-4">
            <button type="submit" class="rounded border p-2 text-sm" style="border-color: var(--border)">{{ __('Apply filters') }}</button>
            <a href="{{ route('exoplanets.index') }}" wire:click.prevent="clearFilters" class="rounded py-2 text-sm underline">{{ __('Clear filters') }}</a>
        </div>
    </form>
    <p wire:loading role="status" class="mb-4 text-sm" style="color: var(--accent);">{{ __('Updating exoplanets…') }}</p>
    @if($filterErrors !== [])
        <div role="alert" class="surface space-y-2 p-5">
            @foreach($filterErrors as $error)<p>{{ $error }}</p>@endforeach
            <a href="{{ route('exoplanets.index') }}" wire:click.prevent="clearFilters" class="underline">{{ __('Reset filters and page') }}</a>
        </div>
    @elseif($apiDown)
        <x-api-down :section="__('The exoplanet catalogue')" />
    @elseif($results->isEmpty())
        <x-empty-state :title="$page > 1 ? __('No exoplanets at this page') : __('No exoplanets match those filters')">
            @if($page > 1)
                {{ __('This page is beyond the current results. The catalogue may have changed since the link was saved.') }}
                <a href="{{ route('exoplanets.index', array_replace($exportQuery, ['page' => 1])) }}" wire:click.prevent="applyFilters" class="mt-3 block underline">{{ __('First page with these filters') }}</a>
            @else
                {{ __('Try another name or clear the filters. Planets without a known distance are excluded when a distance filter is selected.') }}
            @endif
        </x-empty-state>
    @else
        <x-pagination :results="$results" :page="$page" :native-links="true"
            :previous-url="$page > 1 ? route('exoplanets.index', array_replace($exportQuery, ['page' => $page - 1])) : null"
            :next-url="$page < 4000 ? route('exoplanets.index', array_replace($exportQuery, ['page' => $page + 1])) : null">
            @foreach($results->items as $planet)<x-exoplanet-card :planet="$planet" wire:key="{{ $planet->id }}" />@endforeach
        </x-pagination>
        @if($results->hasMore && $page >= 4000)
            <p class="mt-4 text-sm" role="status">{{ __('The browsing page limit has been reached. Narrow the filters to explore more of this catalogue.') }}</p>
        @endif
    @endif
    @if(! $apiDown && $filterErrors === [])
        <section class="surface mt-8 space-y-3 p-5" aria-labelledby="export-heading">
            <h2 id="export-heading" class="font-semibold">{{ __('Download this filtered page') }}</h2>
            <p class="text-sm" style="color: var(--muted)">{{ __('Up to 24 planets with original measurements, uncertainties, limits and references. JSON preserves missing fields and null values; CSV includes the original source data as JSON alongside measurement columns. The catalogue can change between viewing and downloading; exports do not have an immutable snapshot ID.') }}</p>
            <div class="flex flex-wrap gap-5">
                <a href="{{ route('exoplanets.export', ['format' => 'csv'] + $exportQuery) }}" class="rounded underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--accent)]">{{ __('Download page as CSV') }}</a>
                <a href="{{ route('exoplanets.export', ['format' => 'json'] + $exportQuery) }}" class="rounded underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--accent)]">{{ __('Download page as JSON') }}</a>
            </div>
        </section>
    @endif
    <p class="mt-8 text-sm" style="color: var(--muted)">{{ __('Data: NASA Exoplanet Archive, Planetary Systems Composite Parameters (PSCompPars). This is a catalogue of discoveries, not a complete census of planets.') }}</p>
</div>

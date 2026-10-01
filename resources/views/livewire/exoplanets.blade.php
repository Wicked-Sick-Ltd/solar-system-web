<div>
    <x-page-header :title="__('Exoplanets')" :eyebrow="__('Beyond the Sun')"
        :lead="__('Confirmed planets from the NASA Exoplanet Archive. Explore a system, or locate its host on the galaxy map.')" />
    <a href="{{ route('galaxy') }}" class="mb-6 inline-block underline">{{ __('Open galaxy explorer') }} →</a>
    <div class="surface mb-8 grid gap-4 p-5 md:grid-cols-4">
        <div><label for="exo-search" class="mb-2 block text-sm">{{ __('Planet or host name') }}</label>
            <input id="exo-search" type="search" value="{{ $displayFilters['q'] }}" maxlength="200" wire:model.live.debounce.400ms="q" class="w-full rounded border p-2" style="border-color: var(--border); background: var(--bg)" placeholder="TRAPPIST-1"></div>
        <div><label for="exo-method" class="mb-2 block text-sm">{{ __('Discovery method') }}</label>
            <x-form-select id="exo-method" wire:model.live="method" :selected="$displayFilters['method']" class="focus-visible:ring-2 focus-visible:ring-[var(--accent)]" :options="['' => __('Any method'), 'Transit' => __('Transit'), 'Radial Velocity' => __('Radial velocity'), 'Imaging' => __('Imaging'), 'Microlensing' => __('Microlensing'), 'Transit Timing Variations' => __('Transit timing variations'), 'Astrometry' => __('Astrometry'), 'Eclipse Timing Variations' => __('Eclipse timing variations'), 'Pulsar Timing' => __('Pulsar timing'), 'Pulsation Timing Variations' => __('Pulsation timing variations'), 'Orbital Brightness Modulation' => __('Orbital brightness modulation'), 'Disk Kinematics' => __('Disk kinematics') ]" /></div>
        <div><label for="exo-distance" class="mb-2 block text-sm">{{ __('Distance from the Sun') }}</label>
            <x-form-select id="exo-distance" wire:model.live="distance" :selected="$displayFilters['distance']" class="focus-visible:ring-2 focus-visible:ring-[var(--accent)]" :options="['' => __('Any distance'), '10' => __('Within 33 light-years'), '25' => __('Within 82 light-years'), '100' => __('Within 326 light-years'), '1000' => __('Within 3,262 light-years')]" /></div>
        <button type="button" wire:click="clearFilters" class="self-end rounded border p-2" style="border-color: var(--border)">{{ __('Clear filters') }}</button>
    </div>
    @if($filterErrors !== [])
        <div role="alert" class="surface space-y-2 p-5">
            @foreach($filterErrors as $error)<p>{{ $error }}</p>@endforeach
            <button type="button" wire:click="clearFilters" class="underline">{{ __('Reset filters and page') }}</button>
        </div>
    @elseif($apiDown)
        <x-api-down :section="__('The exoplanet catalogue')" />
    @elseif($results->isEmpty())
        <x-empty-state :title="__('No exoplanets match those filters')">{{ __('Try another name or clear the filters. Planets without a known distance are excluded when a distance filter is selected.') }}</x-empty-state>
    @else
        <x-pagination :results="$results" :page="$page">
            @foreach($results->items as $planet)<x-exoplanet-card :planet="$planet" wire:key="{{ $planet->id }}" />@endforeach
        </x-pagination>
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

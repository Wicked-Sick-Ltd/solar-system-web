<div>
    @if($apiDown)<x-api-down :section="__('This planetary system')" />
    @elseif($host)
        <x-page-header :title="$host->name" :eyebrow="__('Planetary system')" :lead="\App\Support\Format::lightYears($host->distancePc)" />
        <p class="mb-5">{{ __(':count catalogued planets', ['count' => count($host->planets)]) }}</p>
        @if($host->mapped)
            <a class="mb-6 inline-block underline" href="{{ route('galaxy', ['host' => $host->id]) }}">{{ __('Locate this system in the galaxy explorer') }} →</a>
            @if($host->distancePlusPc !== null && $host->distanceMinusPc !== null)
                <p class="mb-6 text-sm" style="color: var(--muted)">{{ __('Distance uncertainty') }}: +{{ \App\Support\Format::unit(abs($host->distancePlusPc) * 3.261563777, 'light-years', 4) }} / −{{ \App\Support\Format::unit(abs($host->distanceMinusPc) * 3.261563777, 'light-years', 4) }}</p>
            @endif
        @else
            <p class="surface mb-6 p-4">{{ __('This system has no usable 3D position in the archive snapshot. Its location is not guessed.') }}</p>
        @endif
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($host->planets as $planet)<x-exoplanet-card :planet="$planet" />@endforeach
        </div>
        <p class="mt-8 text-sm" style="color: var(--muted)">{{ __('A system marker represents the host location. Stellar multiples and planetary orbits are not resolved at this scale.') }}</p>
    @endif
</div>

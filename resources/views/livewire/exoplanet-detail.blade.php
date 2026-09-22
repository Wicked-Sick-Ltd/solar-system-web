<div>
    @if($apiDown)<x-api-down :section="__('This exoplanet')" />
    @elseif($planet)
        <x-page-header :title="$planet->name" :eyebrow="__('Exoplanet')" :lead="\App\Support\Format::lightYears($planet->distancePc)" />
        <div class="mb-8 flex flex-wrap gap-5">
            <a class="underline" href="{{ route('systems.show', $planet->hostId) }}">{{ __('Explore :name', ['name' => $planet->hostName]) }} →</a>
            <a class="underline" href="{{ route('galaxy', ['host' => $planet->hostId]) }}">{{ __('Locate system') }} →</a>
            <a class="underline" href="{{ route('exoplanets.index') }}">{{ __('All exoplanets') }}</a>
        </div>
        @if($planet->controversial)<p class="surface mb-6 p-4">{{ __('The archive flags this planet’s confirmation as questioned in the literature.') }}</p>@endif
        <dl class="surface divide-y p-5" style="border-color: var(--border)">
            <x-prop-row :label="__('Discovery')" :value="($planet->discoveryMethod ?? __('Unknown')).' · '.($planet->discoveryYear ?? __('Unknown year'))" />
            @foreach(['pl_rade' => [__('Radius'), __('Earth radii')], 'pl_bmasse' => [__('Mass'), __('Earth masses')], 'pl_orbper' => [__('Orbital period'), __('days')], 'pl_orbsmax' => [__('Semi-major axis'), 'AU'], 'pl_eqt' => [__('Equilibrium temperature'), 'K']] as $field => [$label, $unit])
                <x-prop-row :label="$label" :value="\App\Support\Format::exoplanetMeasurement($planet->measurements, $field, $unit)" />
            @endforeach
            <x-prop-row :label="__('Mass provenance')" :value="$planet->massProvenance ?? __('Unknown')" />
        </dl>
        <div class="mt-8 max-w-3xl space-y-3 text-sm" style="color: var(--muted)">
            <p>{{ __('Measurements may come from different studies and need not form one self-consistent model. “Msini” is a minimum mass; a mass inferred from radius is an estimate. Equilibrium temperature is a model estimate, not a measured surface temperature or evidence of habitability.') }}</p>
            <p><a class="underline" href="{{ $planet->archiveUrl() }}" target="_blank" rel="noopener noreferrer">{{ __('NASA archive record and references') }} ↗</a></p>
            <p>{{ __('Snapshot retrieved: :date', ['date' => $planet->retrievedAt ?? __('Unknown')]) }}</p>
        </div>
    @endif
</div>

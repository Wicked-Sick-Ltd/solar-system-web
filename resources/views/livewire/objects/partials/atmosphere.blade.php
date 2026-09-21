@php
    use App\Support\Format;
@endphp

<section class="mt-10" aria-labelledby="atm-heading">
    <h2 id="atm-heading" class="mb-3 font-serif text-2xl font-medium">{{ __('Atmosphere') }}</h2>
    <div class="surface grid gap-6 p-6 lg:grid-cols-2">
        <dl>
            <x-prop-row :label="__('Surface pressure')" :value="$atmosphere->surfacePressureBar !== null ? Format::unit($atmosphere->surfacePressureBar, 'bar', $atmosphere->surfacePressureBar < 0.01 ? 6 : 2) : null" :hint="$atmosphere->pressureNote" />
            <x-prop-row :label="__('Temperature')" :value="Format::unit($atmosphere->temperatureK, 'K', 0)" :hint="$atmosphere->temperatureNote" />
            <x-prop-row :label="__('Density')" :value="Format::unit($atmosphere->densityKgM3, 'kg/m³', 3)" />
            <x-prop-row :label="__('Scale height')" :value="Format::km($atmosphere->scaleHeightKm, 1)" />
            <x-prop-row :label="__('Mean molecular weight')" :value="Format::number($atmosphere->meanMolecularWeight, 2)" />
            <x-prop-row :label="__('Winds')" :value="$atmosphere->windNote" />
        </dl>
        @if (count($atmosphere->composition))
            <div>
                <h3 class="mb-2 text-xs uppercase tracking-wide" style="color: var(--muted);">{{ __('Composition by volume') }}</h3>
                <ul class="space-y-1.5 text-sm">
                    @foreach (array_slice($atmosphere->composition, 0, 8) as $component)
                        <li class="flex items-baseline justify-between gap-4">
                            <span style="color: var(--text);">{{ $component['species'] }}</span>
                            <span class="tabular-nums" style="color: var(--muted);">{{ Format::number($component['fraction'], $component['unit'] === '%' ? 2 : 0) }} {{ $component['unit'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
    <p class="mt-2 text-xs" style="color: var(--color-faint);">{{ __('Source: NASA Planetary Fact Sheet.') }}</p>
</section>

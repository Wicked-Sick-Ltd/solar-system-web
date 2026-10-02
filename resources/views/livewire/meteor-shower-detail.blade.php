@php use App\Support\Format; @endphp
<div>
    <a class="mb-5 inline-block underline" href="{{ route('meteor-showers.index') }}">← {{ __('Meteor showers') }}</a>
    @if($apiDown)
        <x-page-header :title="__('Meteor shower')" />
        <x-api-down :section="__('This meteor shower')" />
    @elseif(!$shower)
        <x-page-header :title="__('No shower record available')" />
        <x-empty-state :title="__('The catalogue returned no record for this code or name')">
            {{ __('The shower may be missing, or this backend may not yet support meteor showers. Try the catalogue list or return later.') }}
        </x-empty-state>
    @else
        <x-page-header :title="$shower->name" :eyebrow="__('IAU :number · :code', ['number' => $shower->iauNo, 'code' => $shower->code])"
            :lead="__('Measurements from the IAU Meteor Data Center. Each parameter set describes an observation campaign; values and proposed parent bodies may differ between sets.')" />
        <p class="mb-6 text-sm leading-relaxed" style="color: var(--muted);">{{ __('An activity peak is expressed as solar longitude, not a guaranteed calendar date or observing forecast. Radiant coordinates and orbital elements are reported as supplied by the source. Missing values are not reported; these data do not predict an hourly meteor rate.') }}</p>
        @if($shower->parameterSets === [])
            <x-empty-state :title="__('No parameter sets supplied')">{{ __('This record contains no campaign measurements in the current backend response.') }}</x-empty-state>
        @else
            <p class="mb-6 text-sm">{{ __(':count parameter sets returned for this shower. List-page filters do not hide sets here.', ['count' => count($shower->parameterSets)]) }}</p>
            <div class="space-y-6">
                @foreach($shower->parameterSets as $set)
                    <section class="surface p-5 sm:p-7" aria-labelledby="meteor-set-{{ $loop->index }}">
                        <h2 id="meteor-set-{{ $loop->index }}" class="mb-4 font-serif text-2xl">{{ __('Parameter set :number', ['number' => $set->adNo]) }}</h2>
                        <dl class="grid gap-x-8 lg:grid-cols-2">
                            <x-prop-row :label="__('MDC status code')" :value="$set->statusCode ?? __('Not reported')" />
                            <x-prop-row :label="__('MDC status')" :value="$set->statusLabel ?? __('Not reported')" />
                            <x-prop-row :label="__('Activity')" :value="$set->activity ?? __('Not reported')" />
                            <x-prop-row :label="__('Shower group')" :value="$set->showerGroup ?? __('Not reported')" />
                            @foreach([
                                'solar_longitude_deg' => [__('Peak solar longitude'), '°'],
                                'ra_deg' => [__('Radiant right ascension'), '°'],
                                'dec_deg' => [__('Radiant declination'), '°'],
                                'dra_deg_per_day' => [__('Right ascension drift'), '°/day'],
                                'ddec_deg_per_day' => [__('Declination drift'), '°/day'],
                                'vg_km_s' => [__('Geocentric speed'), 'km/s'],
                                'a_au' => [__('Semimajor axis'), 'AU'],
                                'q_au' => [__('Perihelion distance'), 'AU'],
                                'e' => [__('Eccentricity'), ''],
                                'peri_deg' => [__('Argument of perihelion'), '°'],
                                'node_deg' => [__('Ascending node'), '°'],
                                'incl_deg' => [__('Inclination'), '°'],
                            ] as $field => [$label, $unit])
                                <x-prop-row :label="$label" :value="Format::unit($set->measurements[$field], $unit, 6) ?? __('Not reported')" />
                            @endforeach
                            <x-prop-row :label="__('Observed members')" :value="$set->members === null ? __('Not reported') : Format::count($set->members)" />
                            <x-prop-row :label="__('Technique')" :value="$set->technique ?? __('Not reported')" />
                            <x-prop-row :label="__('Submitted on')" :value="$set->submittedOn ?? __('Not reported')" />
                            <x-prop-row :label="__('Reported parent body')">
                                @if($set->parentObjectId)
                                    <a class="underline" href="{{ route('objects.show', $set->parentObjectId) }}">{{ $set->parentBody ?? $set->parentObjectId }}</a>
                                @else
                                    {{ $set->parentBody ?? __('Not reported') }}
                                @endif
                            </x-prop-row>
                        </dl>
                        <div class="mt-5 space-y-2 break-words text-sm" style="color: var(--muted);">
                            <p><span class="font-semibold">{{ __('Reference:') }}</span> {{ \App\Support\SourceReference::plainText($set->reference) ?? __('Not reported') }}</p>
                            <p><span class="font-semibold">{{ __('Source:') }}</span> {{ $set->source ?? __('IAU Meteor Data Center') }}</p>
                        </div>
                    </section>
                @endforeach
            </div>
        @endif
    @endif
</div>

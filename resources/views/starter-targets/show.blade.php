<x-layouts.app>
    <p class="mb-4"><a href="{{ route('observing-targets.index') }}" class="inline-block min-h-11 content-center underline">← {{ __('Observing starter catalogues') }}</a></p>
    @if ($apiDown)
        <x-page-header :title="__('Observing target unavailable')" />
        <x-api-down :section="__('This starter catalogue record')" />
    @elseif (! $target)
        <x-page-header :title="__('Record not found in this starter sample')" />
        <p>{{ __('This identifier is not present in the available bounded sample. Browse or search the starter catalogues for exact source identifiers.') }}</p>
    @else
        <x-page-header :title="$target->name" :eyebrow="__('Catalogue record')" :lead="implode(' · ', $target->aliases)" />
        <x-save-observing-target catalogue="starter" :id="$target->id" :label="$target->name" />
        @include('starter-targets.context')
        <dl class="surface mb-6 grid gap-4 p-5 sm:grid-cols-2">
            <div><dt>{{ __('Stable source identifier') }}</dt><dd class="break-all font-mono">{{ $target->id }}</dd></div>
            <div><dt>{{ __('Source object type') }}</dt><dd>{{ $target->data['object_type'] }}</dd></div>
            <div><dt>{{ __('Catalogue right ascension') }}</dt><dd>{{ $target->measurement('ra_deg', 'degrees') }}</dd></div>
            <div><dt>{{ __('Catalogue declination') }}</dt><dd>{{ $target->measurement('dec_deg', 'degrees') }}</dd></div>
            <div><dt>{{ __('Coordinate equinox') }}</dt><dd>{{ $target->data['coordinate_equinox'] ?? __('Not independently specified') }}</dd></div>
            <div><dt>{{ __('Coordinate epoch') }}</dt><dd>{{ $target->data['coordinate_epoch'] ?? __('Not separately specified; no motion correction applied') }}</dd></div>
            <div><dt>{{ __('Recorded magnitude') }}</dt><dd>{{ $target->measurement('magnitude') }} · {{ $target->data['magnitude_band'] }}<br>{{ __('Uncertainty flag') }}: {{ $target->data['magnitude_flag'] ?? __('None reported') }}; {{ __('source code') }}: {{ $target->data['magnitude_code'] ?? __('None reported') }}</dd></div>
            <div><dt>{{ __('Catalogue major / minor axes') }}</dt><dd>{{ $target->measurement('major_axis_arcmin', 'arcmin') }} / {{ $target->measurement('minor_axis_arcmin', 'arcmin') }}</dd></div>
            @if (in_array('double_star', $target->families, true))
                <div><dt>{{ __('Components') }}</dt><dd>{{ $target->data['components'] }}</dd></div>
                <div><dt>{{ __('Historical component separation') }}</dt><dd>{{ $target->measurement('separation_arcsec', 'arcsec') }}</dd></div>
                <div><dt>{{ __('Separation measurement epoch') }}</dt><dd>{{ $target->data['separation_epoch'] ?? __('Not reported') }}</dd></div>
                <div><dt>{{ __('Companion position angle') }}</dt><dd>{{ $target->measurement('position_angle_deg', 'degrees') }}</dd></div>
            @endif
        </dl>
        @include('starter-targets.source', ['source' => $target->provenance])
        <details class="surface mt-6 p-5"><summary class="cursor-pointer py-2 font-semibold">{{ __('Original source fields and flags') }}</summary>
            <p class="my-3 text-sm">{{ __('Original strings preserve missing values, photometric flags and per-field source codes. Blank means no value was supplied. No proper motion or companion position is calculated here.') }}</p>
            <dl class="space-y-3 text-sm">@foreach ($target->data['source_data'] as $key => $value)<div class="grid gap-1 sm:grid-cols-[10rem_1fr]"><dt class="font-medium">{{ $key }}</dt><dd class="min-w-0 break-words">{{ $value === '' ? __('Not supplied') : $value }}</dd></div>@endforeach</dl>
        </details>
    @endif
</x-layouts.app>

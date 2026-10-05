<x-layouts.app>
    <div class="mx-auto max-w-5xl">
        <x-page-header :title="__('Find targets for a night')" :eyebrow="__('Start with your site and equipment')" :lead="__('Request a small, explained shortlist without knowing catalogue identifiers. This searches a bounded starter sample, the Moon and seven planets; it is not an all-sky survey.')" />
        <p class="mb-4">{{ __('Equipment choices express preferences, not promises of visibility. Darkness, atmosphere, sky brightness, aperture, observer experience and unresolved model boundaries can change what you actually see.') }}</p>
        <p class="mb-6">{{ __('Coordinates are rounded to two decimal places and sent only when you submit. This form does not save or activate a location, upload equipment profiles, or request weather.') }} <a class="underline" href="{{ route('observe.night') }}">{{ __('Already know your targets? Use the manual night planner.') }}</a></p>
        @if($problem)
            <div role="alert" class="surface mb-6 space-y-3 p-5"><p>{{ $problem }}</p>
                @if($validation)<ul class="list-disc pl-5">@foreach($validation as $messages)@foreach($messages as $message)<li>{{ $message }}</li>@endforeach @endforeach</ul>@endif
            </div>
        @endif
        <form data-shortlist-form method="POST" action="{{ route('observe.shortlist.calculate') }}" class="surface space-y-5 p-5 print:hidden">
            @csrf
            <fieldset class="space-y-3 rounded border p-4" @isset($validation['equipment_mode']) aria-describedby="shortlist-error-equipment" @endisset>
                <legend>{{ __('How would you like to observe?') }}</legend>
                <div class="flex flex-wrap gap-3">
                    @foreach(['naked_eye' => 'Naked eye', 'binocular' => 'Binoculars', 'telescope' => 'Telescope'] as $mode => $label)
                        <label class="inline-flex min-h-11 items-center gap-2 rounded border px-3"><input type="radio" name="equipment_mode" value="{{ $mode }}" @checked($input['equipment_mode'] === $mode) required> {{ __($label) }}</label>
                    @endforeach
                </div>
                <p>{{ __('Naked eye favours the Moon and bright stars; binoculars favour known angular field fits and deep-sky families; telescopes favour Solar System targets and double stars. These are editorial ordering preferences, not detection or resolving-power limits. A double-star label does not mean its components can be resolved.') }}</p>
                @isset($validation['equipment_mode'])<p id="shortlist-error-equipment">{{ implode(' ', $validation['equipment_mode']) }}</p>@endisset
            </fieldset>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block">{{ __('Prefer') }}
                    <select name="preference" @isset($validation['preference']) aria-invalid="true" aria-describedby="shortlist-error-preference" @endisset class="mt-2 w-full rounded border p-3" style="background: var(--bg-elevated)">
                        @foreach(['balanced' => 'Balanced families', 'wide_field' => 'Known angular field fit first', 'stars' => 'Stars first', 'deep_sky' => 'Deep-sky objects first', 'solar_system' => 'Moon and planets first'] as $value => $label)<option value="{{ $value }}" @selected($input['preference'] === $value)>{{ __($label) }}</option>@endforeach
                    </select>
                </label>
                <label class="block">{{ __('Maximum shortlisted targets') }}<select name="shortlist_limit" class="mt-2 w-full rounded border p-3" style="background: var(--bg-elevated)" @isset($validation['shortlist_limit']) aria-invalid="true" aria-describedby="shortlist-error-limit" @endisset>@foreach(range(1, 8) as $limit)<option value="{{ $limit }}" @selected((string)$input['shortlist_limit'] === (string)$limit)>{{ $limit }}</option>@endforeach</select></label>
                @isset($validation['shortlist_limit'])<p id="shortlist-error-limit">{{ implode(' ', $validation['shortlist_limit']) }}</p>@endisset
            </div>
            @isset($validation['preference'])<p id="shortlist-error-preference">{{ implode(' ', $validation['preference']) }}</p>@endisset
            <fieldset class="space-y-3 rounded border p-4">
                <legend>{{ __('Optional constraints you supply') }}</legend>
                <label class="block" for="shortlist-field">{{ __('True field of view, degrees (optional)') }}</label>
                <input id="shortlist-field" name="true_field_deg" type="number" step="any" min="0.01" max="180" value="{{ $input['true_field_deg'] }}" class="w-full rounded border p-3" style="background: var(--bg-elevated)" aria-describedby="shortlist-field-help {{ isset($validation['true_field_deg']) ? 'shortlist-error-field' : '' }}" aria-invalid="{{ isset($validation['true_field_deg']) ? 'true' : 'false' }}">
                <p id="shortlist-field-help">{{ __('Use a measured or calculated true field if you know it. Known catalogue major-axis extents can be compared with this angle; fitting does not guarantee visibility. Leave blank for an unknown field. Double-star separation is not treated as an object diameter.') }}</p>
                @isset($validation['true_field_deg'])<p id="shortlist-error-field">{{ implode(' ', $validation['true_field_deg']) }}</p>@endisset
                <label class="block" for="shortlist-magnitude">{{ __('Maximum catalogue V magnitude (optional)') }}</label>
                <input id="shortlist-magnitude" name="max_catalogue_v_magnitude" type="number" step="any" min="-30" max="30" value="{{ $input['max_catalogue_v_magnitude'] }}" class="w-full rounded border p-3" style="background: var(--bg-elevated)" aria-describedby="shortlist-magnitude-help {{ isset($validation['max_catalogue_v_magnitude']) ? 'shortlist-error-magnitude' : '' }}" aria-invalid="{{ isset($validation['max_catalogue_v_magnitude']) ? 'true' : 'false' }}">
                <p id="shortlist-magnitude-help">{{ __('This is your explicit catalogue filter, not a limiting magnitude inferred from equipment. Larger magnitudes are fainter. Supplying it excludes unknown or non-V magnitudes, including Moon and planet candidates whose brightness is not supplied. Integrated deep-sky magnitude is not surface brightness or point-source detectability.') }}</p>
                @isset($validation['max_catalogue_v_magnitude'])<p id="shortlist-error-magnitude">{{ implode(' ', $validation['max_catalogue_v_magnitude']) }}</p>@endisset
            </fieldset>
            <p>{{ __('A night runs from local noon to the following noon. Optional UTC observing hours avoid ambiguous clock-change times.') }}</p>
            @include('observing.partials-night-conditions')
            <p>{{ __('Moon separation applies to other targets only while the Moon’s geometric centre is above 0°, independent of terrain. Every target must remain at least 30° from the Sun; this gives no solar-observing instructions.') }}</p>
            <button type="submit" class="min-h-11 rounded border px-5 py-3 font-semibold">{{ __('Find my shortlist') }}</button>
            <p class="text-sm">{{ __('This performs one bounded calculation and can take up to 40 seconds. Nothing is requested until you submit. No account is required.') }}</p>
        </form>
        @if($result)
            @php
                $screen = $result['discovery'];
                $plan = $result['plan'];
                $zone = new \DateTimeZone($result['request']['timezone']);
                $local = static fn (string $utc): string => (new \DateTimeImmutable($utc))->setTimezone($zone)->format('j M H:i:s P');
                $targetsById = $plan ? array_column($plan['targets'], null, 'id') : [];
                $start = $plan ? strtotime($plan['night']['start_utc']) : 0;
                $duration = $plan ? strtotime($plan['night']['end_utc']) - $start : 1;
            @endphp
            <section class="mt-8 space-y-4" aria-labelledby="shortlist-results">
                <h2 id="shortlist-results" class="text-2xl">{{ __('Your explained shortlist') }}</h2>
                <p>{{ $result['request']['date'] }} · {{ $result['request']['timezone'] }} · {{ $local($result['request']['window_start_utc']) }} → {{ $local($result['request']['window_end_utc']) }}</p>
                <p class="print:hidden">{{ __('Rounded location:') }} {{ $result['request']['lat'] }}°, {{ $result['request']['lon'] }}°.</p>
                <p>{{ __('Screened :catalogue supported catalogue records plus :solar Solar System targets at :samples instants, no more than 20 minutes apart. :unsupported catalogue records have unsupported astrometry and were excluded.', ['catalogue' => $screen['catalogue_records'], 'solar' => $screen['solar_system_targets'], 'samples' => $screen['sample_count'], 'unsupported' => $screen['unsupported_catalogue_records']]) }}</p>
                <p>{{ __(':coarse candidates matched at sampled instants; the first :refined were refined. :omitted of that selection had no refined window and are not suggested. There is no automatic backfill beyond that bounded selection.', ['coarse' => $screen['coarse_matching_candidates'], 'refined' => $screen['refined_candidates'], 'omitted' => $screen['selected_without_refined_window']]) }}</p>
                @if($screen['options']['max_catalogue_v_magnitude'] !== null)<p>{{ __('Your V-magnitude filter excluded :faint fainter records and :unknown records without a known V magnitude.', ['faint' => $screen['brightness_excluded'], 'unknown' => $screen['unknown_or_non_v_brightness_excluded']]) }}</p>@endif
                @if($result['candidates'] === [])<p role="status" class="surface p-5">{{ __('No confirmed shortlist from this bounded screening. Short or grazing windows can fall between sampled instants; this does not establish that the night has no observable targets. Review the limits or use the manual planner for a particular target.') }}</p>@endif
                @if($result['candidates'])
                    <div class="space-y-2 print:hidden"><a data-shortlist-manual-link class="inline-block min-h-11 rounded border px-4 py-3 underline" href="{{ route('observe.night', ['targets' => array_column($result['candidates'], 'id')]) }}">{{ __('Prepare these targets in the manual planner') }}</a>
                    <p>{{ __('This transfers target identifiers only. Enter or copy your site and date again to calculate a session with equipment comparisons and exports. Those exports describe that new calculation, not this shortlist’s screening or ranking.') }}</p></div>
                @endif
                <p>{{ __('Ranking uses your family/field preference, equipment-family preference, then more matching sampled instants and stable identifier order. These counts are not uninterrupted duration, a visibility score or a guarantee.') }}</p>
                @if($plan)<p>{{ __('Moon illuminated:') }} {{ number_format($plan['moon']['illumination_fraction'] * 100, 1) }}% {{ __('at') }} {{ $local($plan['moon']['reference_utc']) }}. {{ $plan['constraints']['horizon_mask'] === null ? __('Terrain remains unknown.') : __('Your supplied horizon profile was applied.') }}</p>@endif
            </section>
            @foreach($result['candidates'] as $candidate)
                @include('observing.partials-night-target', ['target' => $targetsById[$candidate['id']]])
            @endforeach
            <section class="surface mt-6 space-y-3 p-5" aria-labelledby="shortlist-limits">
                <h2 id="shortlist-limits" class="text-xl">{{ __('Scope, sources and limits') }}</h2>
                <p>{{ $screen['scope'] }}</p>
                <ul class="list-disc pl-5">@foreach($screen['limitations'] as $limit)<li>{{ $limit }}</li>@endforeach</ul>
                <p>{{ $result['method']['provider'] }} · {{ $result['method']['ephemeris'] }} · {{ $result['method']['frame'] }} · {{ $result['method']['refraction'] }}</p>
                <p>{{ $result['method']['accuracy_note'] }}</p><p>{{ $result['method']['window_note'] }}</p>
                @include('observing.partials-night-method', ['method' => $result['method'], 'hasSessionExport' => false])
                <details class="rounded border p-3"><summary class="cursor-pointer py-2">{{ __('Screening algorithm and source snapshots') }}</summary>
                    <p class="break-all">{{ __('Screening source SHA-256:') }} {{ $screen['calculation']['source_sha256'] }}</p>
                    @foreach($screen['source_snapshots'] as $source)<div class="my-3 space-y-2"><p><a class="underline" href="{{ $source['source_url'] }}">{{ $source['source'] }}</a> · <a class="underline" href="{{ $source['license_url'] }}">{{ $source['license'] }}</a></p><p>{{ $source['attribution'] }}</p><p class="break-all">{{ __('Snapshot SHA-256:') }} {{ $source['snapshot_sha256'] }}</p></div>@endforeach
                </details>
                <p>{{ __('Weather was not requested. Check a current local forecast and appropriate observing guidance before going outside.') }}</p>
            </section>
        @endif
    </div>
    @vite('resources/js/observing/shortlist.js')
</x-layouts.app>

<x-layouts.app>
    <div class="mx-auto max-w-5xl" @if($sessionSummary) data-night-session @endif>
        <x-page-header :title="__('Plan a night')" :eyebrow="__('Moon, planets and catalogue targets')" :lead="__('Find geometric observing windows for your location. Clouds, terrain, brightness and equipment can still prevent a useful view.')" />
        @php
            $sections = [['id' => 'night-plan-heading', 'label' => __('Location, time and targets')]];
            if ($plan) {
                $sections = array_merge($sections, [
                    ['id' => 'night-summary', 'label' => __('Your night')],
                    ['id' => 'night-session-heading', 'label' => __('Save and print')],
                    ['id' => 'equipment-suggestions-heading', 'label' => __('Equipment')],
                    ['id' => 'night-weather-heading', 'label' => __('Weather')],
                    ['id' => 'target-'.$plan['targets'][0]['id'], 'label' => __('Target windows')],
                    ['id' => 'night-method', 'label' => __('Calculation and limits')],
                ]);
            }
        @endphp
        <x-section-navigation :sections="$sections" />
        <p class="mb-6">{{ __('A night runs from local noon to the following noon. Times include their UTC offset so clock changes are unambiguous. Coordinates are rounded to two decimal places and sent for this calculation; this form does not save them.') }}</p>
        <p class="mb-6"><a class="underline" href="{{ route('observe.shortlist') }}">{{ __('Not sure what to choose? Find an explained shortlist for your site and equipment.') }}</a></p>
        @if ($problem)
            <div role="alert" class="surface mb-6 p-5">
                <p>{{ $problem }}</p>
                @if ($validation)<ul class="mt-3 list-disc pl-5">@foreach ($validation as $messages)@foreach ($messages as $message)<li>{{ $message }}</li>@endforeach @endforeach</ul>@endif
            </div>
        @endif
        <form data-night-form method="POST" action="{{ route('observe.night.calculate') }}" class="surface space-y-5 p-5 print:hidden">
            <h2 id="night-plan-heading" tabindex="-1" class="text-2xl">{{ __('Location, time and targets') }}</h2>
            @csrf
            @include('observing.partials-night-conditions')
            <fieldset @if(collect($validation)->keys()->contains(fn ($key) => str_starts_with($key, 'targets'))) aria-describedby="night-error-targets" @endif>
                <legend>{{ __('Targets') }}</legend>
                <div class="mt-2 flex flex-wrap gap-3">@foreach (\App\Services\Observing\NightRequest::TARGETS as $body)<label class="inline-flex min-h-11 items-center gap-2 rounded border px-3"><input type="checkbox" name="targets[]" value="{{ $body }}" @checked(in_array($body, $input['targets'], true))> {{ ucfirst($body) }}</label>@endforeach</div>
                <label class="mt-4 block" for="night-catalogue-targets">{{ __('Catalogue identifiers (optional)') }}</label>
                <input id="night-catalogue-targets" name="catalogue_targets" value="{{ $input['catalogue_targets'] }}" maxlength="400" placeholder="bsc5p:hr2491, openngc:NGC0224" class="mt-2 w-full rounded border p-3" style="background: var(--bg-elevated)" @if(collect($validation)->keys()->contains(fn ($key) => str_starts_with($key, 'targets'))) aria-invalid="true" aria-describedby="night-error-targets" @endif>
                <p class="mt-2 text-sm">{{ __('Choose at most eight targets in total. Use exact, case-sensitive identifiers from the starter catalogue, separated by spaces or commas. Only records with supported coordinate metadata can be calculated; the catalogue is a bounded sample.') }}</p>
                @if(collect($validation)->keys()->contains(fn ($key) => str_starts_with($key, 'targets')))<p id="night-error-targets" class="mt-2 text-sm">{{ __('Choose one to eight distinct supported targets.') }}</p>@endif
            </fieldset>
            <p class="text-sm">{{ __('Moon separation applies to other targets only while the Moon’s geometric centre is above 0°, independent of your terrain profile. Every target must remain at least 30° from the Sun. This is a night-planning tool and provides no solar-observing instructions.') }}</p>
            <button class="min-h-11 rounded border px-5 py-3 font-semibold" type="submit">{{ __('Calculate this night') }}</button>
            <p class="text-sm">{{ __('A full calculation may take up to 40 seconds. No account is required.') }}</p>
        </form>
        @if ($plan)
            @php
                $zone = new \DateTimeZone($plan['observer']['timezone']);
                $local = static fn (string $utc): string => (new \DateTimeImmutable($utc))->setTimezone($zone)->format('j M H:i:s P');
                $start = strtotime($plan['night']['start_utc']);
                $duration = strtotime($plan['night']['end_utc']) - $start;
            @endphp
            <section class="mt-8 space-y-4" aria-labelledby="night-summary">
                <h2 id="night-summary" tabindex="-1" class="text-2xl">{{ __('Your night') }} · {{ $plan['night']['date'] }}</h2>
                <p>{{ $local($plan['night']['start_utc']) }} → {{ $local($plan['night']['end_utc']) }} · {{ $plan['observer']['timezone'] }} · {{ $plan['night']['duration_hours'] }} {{ __('hours') }}</p>
                <p>{{ __('Selected observing interval:') }} {{ $local($plan['constraints']['window_start_utc']) }} → {{ $local($plan['constraints']['window_end_utc']) }}. {{ $plan['constraints']['horizon_mask'] === null ? __('Terrain is unknown; only the baseline altitude was applied.') : __('Your supplied horizon profile was applied with the baseline altitude.') }}</p>
                <p><span data-night-private-location class="print:hidden">{{ __('Location:') }} {{ $plan['observer']['lat'] }}°, {{ $plan['observer']['lon'] }}° · </span>{{ __('Moon illuminated:') }} {{ number_format($plan['moon']['illumination_fraction'] * 100, 1) }}% {{ __('at') }} {{ $local($plan['moon']['reference_utc']) }}.</p>
                <p data-night-private-location class="print:hidden">{{ __('Terrain profile (azimuth°, minimum altitude°):') }} @if($plan['constraints']['horizon_mask'] === null) {{ __('Unknown') }} @else @foreach($plan['constraints']['horizon_mask'] as $point) {{ $point['azimuth_deg'] }}°, {{ $point['min_altitude_deg'] }}°{{ $loop->last ? '.' : '; ' }} @endforeach @endif</p>
                <p>{{ __('Darkness intervals:') }} @forelse ($plan['darkness']['intervals'] as $window){{ $local($window['start_utc']) }} – {{ $local($window['end_utc']) }}{{ $loop->last ? '.' : '; ' }} @empty {{ $plan['darkness']['status'] === 'unresolved_grazing' ? __('No confirmed interval; the boundary remains unresolved.') : __('None at the chosen threshold.') }} @endforelse</p>
                @if ($plan['darkness']['status'] === 'unresolved_grazing')<p>{{ __('Darkness is close to a grazing crossing; its boundary is unresolved in this model.') }}</p>@endif
            </section>
            @include('observing.partials-night-session')
            <div class="print:hidden">@include('observing.equipment-suggestions')</div>
            @include('observing.partials-night-weather-request')
            @foreach ($plan['targets'] as $target)
                @include('observing.partials-night-target')
            @endforeach
            <section class="surface mt-6 space-y-3 p-5" aria-labelledby="night-method">
                <h2 id="night-method" tabindex="-1" class="text-xl">{{ __('Calculation and limits') }}</h2>
                <p>{{ $plan['method']['provider'] }} · {{ $plan['method']['ephemeris'] }} · {{ $plan['method']['frame'] }} · {{ $plan['method']['refraction'] }}</p>
                <p>{{ $plan['method']['accuracy_note'] }}</p><p>{{ $plan['method']['window_note'] }}</p>
                <p>{{ __('Earth-orientation data:') }} {{ $plan['method']['iers']['status'] }}. {{ __('Numerical crossing tolerance:') }} {{ $plan['method']['root_tolerance_seconds'] }} s.</p>
                @include('observing.partials-night-method', ['method' => $plan['method']])
                <p>{{ __('Weather is not included in this calculation. Check a current local forecast before observing.') }}</p>
            </section>
        @endif
    </div>
    @vite('resources/js/observing/night.js')
</x-layouts.app>

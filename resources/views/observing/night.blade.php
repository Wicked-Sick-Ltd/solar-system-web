<x-layouts.app>
    <div class="mx-auto max-w-5xl">
        <x-page-header :title="__('Plan a night')" :eyebrow="__('Moon and planets')" :lead="__('Find geometric observing windows for your location. Clouds, terrain, brightness and equipment can still prevent a useful view.')" />
        <p class="mb-6">{{ __('A night runs from local noon to the following noon. Times include their UTC offset so clock changes are unambiguous. Coordinates are rounded to two decimal places and sent for this calculation; this form does not save them.') }}</p>
        @if ($problem)
            <div role="alert" class="surface mb-6 p-5">
                <p>{{ $problem }}</p>
                @if ($validation)<ul class="mt-3 list-disc pl-5">@foreach ($validation as $messages)@foreach ($messages as $message)<li>{{ $message }}</li>@endforeach @endforeach</ul>@endif
            </div>
        @endif
        <form method="POST" action="{{ route('observe.night.calculate') }}" class="surface space-y-5 p-5 print:hidden">
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach (['date' => ['Night starting', 'date', null, null], 'timezone' => ['IANA time zone (for example Europe/London)', 'text', null, null], 'lat' => ['Latitude, degrees north', 'number', -90, 90], 'lon' => ['Longitude, degrees east', 'number', -180, 180], 'min_altitude_deg' => ['Minimum altitude, degrees', 'number', 0, 85], 'min_moon_separation_deg' => ['Minimum Moon separation, degrees', 'number', 0, 180]] as $field => [$label, $type, $min, $max])
                    <div>
                        <label class="block text-sm" for="night-{{ $field }}">{{ __($label) }}</label>
                        <input class="mt-2 w-full rounded border p-3" style="background: var(--bg-elevated); border-color: var(--border);" id="night-{{ $field }}" name="{{ $field }}" type="{{ $type }}" value="{{ $input[$field] }}" required @if($type === 'number') step="0.01" min="{{ $min }}" max="{{ $max }}" @endif @if($field === 'timezone') maxlength="100" @endif aria-invalid="{{ isset($validation[$field]) ? 'true' : 'false' }}" @if(isset($validation[$field])) aria-describedby="night-error-{{ $field }}" @endif>
                        @if(isset($validation[$field]))<p id="night-error-{{ $field }}" class="mt-2 text-sm">{{ implode(' ', $validation[$field]) }}</p>@endif
                    </div>
                @endforeach
                <div>
                    <label class="block text-sm" for="night-darkness">{{ __('Darkness threshold') }}</label>
                    <select id="night-darkness" name="sun_altitude_deg" @if(isset($validation['sun_altitude_deg'])) aria-invalid="true" aria-describedby="night-error-darkness" @endif class="mt-2 w-full rounded border p-3" style="background: var(--bg-elevated); border-color: var(--border);">
                        @foreach ([-6 => 'Civil twilight (Sun below −6°)', -12 => 'Nautical twilight (Sun below −12°)', -18 => 'Astronomical darkness (Sun below −18°)'] as $angle => $label)<option value="{{ $angle }}" @selected($input['sun_altitude_deg'] == $angle)>{{ __($label) }}</option>@endforeach
                    </select>
                    @if(isset($validation['sun_altitude_deg']))<p id="night-error-darkness" class="mt-2 text-sm">{{ implode(' ', $validation['sun_altitude_deg']) }}</p>@endif
                </div>
            </div>
            <fieldset @if(collect($validation)->keys()->contains(fn ($key) => str_starts_with($key, 'targets'))) aria-describedby="night-error-targets" @endif>
                <legend>{{ __('Targets') }}</legend>
                <div class="mt-2 flex flex-wrap gap-3">@foreach (\App\Services\Observing\NightRequest::TARGETS as $body)<label class="inline-flex min-h-11 items-center gap-2 rounded border px-3"><input type="checkbox" name="targets[]" value="{{ $body }}" @checked(in_array($body, $input['targets'], true))> {{ ucfirst($body) }}</label>@endforeach</div>
                @if(collect($validation)->keys()->contains(fn ($key) => str_starts_with($key, 'targets')))<p id="night-error-targets" class="mt-2 text-sm">{{ __('Choose one or more distinct supported targets.') }}</p>@endif
            </fieldset>
            <p class="text-sm">{{ __('Moon separation applies to other targets only while the Moon is above the horizon. Every target must remain at least 30° from the Sun. This is a night-planning tool and provides no solar-observing instructions.') }}</p>
            <button class="min-h-11 rounded border px-5 py-3 font-semibold" type="submit">{{ __('Calculate this night') }}</button>
            <p class="text-sm">{{ __('A full calculation may take up to 30 seconds. No account is required.') }}</p>
        </form>
        @if ($plan)
            @php
                $zone = new \DateTimeZone($plan['observer']['timezone']);
                $local = static fn (string $utc): string => (new \DateTimeImmutable($utc))->setTimezone($zone)->format('j M H:i P');
                $start = strtotime($plan['night']['start_utc']);
                $duration = strtotime($plan['night']['end_utc']) - $start;
            @endphp
            <section class="mt-8 space-y-4" aria-labelledby="night-summary">
                <h2 id="night-summary" class="text-2xl">{{ __('Your night') }} · {{ $plan['night']['date'] }}</h2>
                <p>{{ $local($plan['night']['start_utc']) }} → {{ $local($plan['night']['end_utc']) }} · {{ $plan['observer']['timezone'] }} · {{ $plan['night']['duration_hours'] }} {{ __('hours') }}</p>
                <p>{{ __('Location:') }} {{ $plan['observer']['lat'] }}°, {{ $plan['observer']['lon'] }}° · {{ __('Moon illuminated:') }} {{ number_format($plan['moon']['illumination_fraction'] * 100, 1) }}% {{ __('at') }} {{ $local($plan['moon']['reference_utc']) }}.</p>
                <p>{{ __('Darkness intervals:') }} @forelse ($plan['darkness']['intervals'] as $window){{ $local($window['start_utc']) }} – {{ $local($window['end_utc']) }}{{ $loop->last ? '.' : '; ' }} @empty {{ $plan['darkness']['status'] === 'unresolved_grazing' ? __('No confirmed interval; the boundary remains unresolved.') : __('None at the chosen threshold.') }} @endforelse</p>
                @if ($plan['darkness']['status'] === 'unresolved_grazing')<p>{{ __('Darkness is close to a grazing crossing; its boundary is unresolved in this model.') }}</p>@endif
            </section>
            @foreach ($plan['targets'] as $target)
                <article class="surface mt-6 space-y-4 p-5" aria-labelledby="target-{{ $target['id'] }}">
                    <h2 id="target-{{ $target['id'] }}" class="text-2xl">{{ $target['name'] }}</h2>
                    <x-save-observing-target catalogue="solar" :target-id="$target['id'] === 'moon' ? 'moon-luna' : 'planet-'.$target['id']" :target-label="$target['name']" />
                    @if ($target['status'] === 'unresolved_grazing')<p>{{ __('A constraint nearly touches its threshold. These provisional windows need independent checking.') }}</p>@endif
                    <p>{{ __('Windows satisfying altitude, darkness, Sun separation and your Moon constraint:') }}</p>
                    <ul class="list-disc pl-5">@forelse ($target['windows'] as $window)<li><time datetime="{{ $window['start_utc'] }}">{{ $local($window['start_utc']) }}</time> – <time datetime="{{ $window['end_utc'] }}">{{ $local($window['end_utc']) }}</time></li>@empty<li>{{ $target['status'] === 'unresolved_grazing' ? __('No confirmed window; a constraint boundary remains unresolved.') : __('No matching window in this local night. This does not mean the target never rises.') }}</li>@endforelse</ul>
                    @php
                        $points = implode(' ', array_map(static fn (array $s): string => number_format((strtotime($s['time_utc']) - $start) / $duration * 580 + 40, 2, '.', '').','.number_format(100 - $s['altitude_deg'], 2, '.', ''), $target['samples']));
                    @endphp
                    <svg viewBox="0 0 640 220" class="w-full" role="img" aria-labelledby="chart-{{ $target['id'] }}">
                        <title id="chart-{{ $target['id'] }}">{{ __('Altitude through this night; full sample table follows.') }}</title>
                        @foreach ([90, 45, 0, -45, -90] as $altitude)
                            <line x1="40" y1="{{ 100 - $altitude }}" x2="620" y2="{{ 100 - $altitude }}" stroke="currentColor" opacity="{{ $altitude === 0 ? '0.5' : '0.15' }}" />
                            <text x="32" y="{{ 104 - $altitude }}" text-anchor="end" font-size="10" fill="currentColor">{{ $altitude }}°</text>
                        @endforeach
                        <polyline points="{{ $points }}" fill="none" stroke="currentColor" stroke-width="2" />
                        <text x="40" y="214" font-size="11" fill="currentColor">{{ __('Local noon') }}</text>
                        <text x="620" y="214" text-anchor="end" font-size="11" fill="currentColor">{{ __('Next noon') }}</text>
                    </svg>
                    <details>
                        <summary class="cursor-pointer py-3">{{ __('Altitude and direction sample table') }}</summary>
                        <div class="overflow-x-auto"><table class="w-full text-left text-sm tabular-nums">
                            <caption class="py-3 text-left">{{ __('Five-minute geometric samples; azimuth runs from north (0°) through east (90°). Windows use refined boundaries between samples.') }}</caption>
                            <thead><tr>@foreach (['Local time (UTC offset)', 'Altitude °', 'Azimuth °', 'Sun separation °', 'Moon separation °', 'Moon altitude °'] as $heading)<th scope="col" class="p-2">{{ __($heading) }}</th>@endforeach</tr></thead>
                            <tbody>@foreach ($target['samples'] as $sample)<tr><th scope="row" class="p-2 font-normal"><time datetime="{{ $sample['time_utc'] }}">{{ $local($sample['time_utc']) }}</time></th>@foreach (['altitude_deg', 'azimuth_deg', 'sun_separation_deg', 'moon_separation_deg', 'moon_altitude_deg'] as $field)<td class="p-2">{{ number_format($sample[$field], 1) }}</td>@endforeach</tr>@endforeach</tbody>
                        </table></div>
                    </details>
                </article>
            @endforeach
            <section class="surface mt-6 space-y-3 p-5" aria-labelledby="night-method">
                <h2 id="night-method" class="text-xl">{{ __('Calculation and limits') }}</h2>
                <p>{{ $plan['method']['provider'] }} · {{ $plan['method']['ephemeris'] }} · {{ $plan['method']['frame'] }} · {{ $plan['method']['refraction'] }}</p>
                <p>{{ $plan['method']['accuracy_note'] }}</p><p>{{ $plan['method']['window_note'] }}</p>
                <p>{{ __('Earth-orientation data:') }} {{ $plan['method']['iers']['status'] }}. {{ __('Numerical crossing tolerance:') }} {{ $plan['method']['root_tolerance_seconds'] }} s.</p>
                <p>{{ __('Weather is not included in this calculation. Check a current local forecast before observing.') }}</p>
            </section>
        @endif
    </div>
</x-layouts.app>

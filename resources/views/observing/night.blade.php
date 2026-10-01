<x-layouts.app>
    <div class="mx-auto max-w-5xl" @if($sessionSummary) data-night-session @endif>
        <x-page-header :title="__('Plan a night')" :eyebrow="__('Moon, planets and catalogue targets')" :lead="__('Find geometric observing windows for your location. Clouds, terrain, brightness and equipment can still prevent a useful view.')" />
        <p class="mb-6">{{ __('A night runs from local noon to the following noon. Times include their UTC offset so clock changes are unambiguous. Coordinates are rounded to two decimal places and sent for this calculation; this form does not save them.') }}</p>
        @if ($problem)
            <div role="alert" class="surface mb-6 p-5">
                <p>{{ $problem }}</p>
                @if ($validation)<ul class="mt-3 list-disc pl-5">@foreach ($validation as $messages)@foreach ($messages as $message)<li>{{ $message }}</li>@endforeach @endforeach</ul>@endif
            </div>
        @endif
        <form data-night-form method="POST" action="{{ route('observe.night.calculate') }}" class="surface space-y-5 p-5 print:hidden">
            @csrf
            <fieldset data-night-sites hidden class="space-y-3 rounded border p-4">
                <legend>{{ __('Use a site saved in this browser') }}</legend>
                <label class="block">{{ __('Saved site') }}<select data-night-site-choice class="mt-2 w-full rounded border p-3" style="background: var(--bg-elevated)"></select></label>
                <button data-night-site-apply type="button" class="min-h-11 rounded border px-4">{{ __('Copy this site into the form') }}</button>
                <p>{{ __('This copies its rounded coordinates, timezone, minimum altitude and any horizon profile. Review them before calculating. It does not activate the site or save form changes.') }}</p>
                <p data-night-site-status role="status"></p>
            </fieldset>
            <p data-night-site-error role="alert"></p>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach (['date' => ['Night starting', 'date', null, null], 'timezone' => ['IANA time zone (for example Europe/London)', 'text', null, null], 'lat' => ['Latitude, degrees north', 'number', -90, 90], 'lon' => ['Longitude, degrees east', 'number', -180, 180], 'min_altitude_deg' => ['Minimum altitude, degrees', 'number', 0, 90], 'min_moon_separation_deg' => ['Minimum Moon separation, degrees', 'number', 0, 180]] as $field => [$label, $type, $min, $max])
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
            <fieldset class="space-y-3 rounded border p-4">
                <legend>{{ __('Observing hours (optional)') }}</legend>
                <p>{{ __('Leave both times blank to consider the whole local night. Otherwise enter UTC start and end times within that night; UTC avoids ambiguous clock-change hours.') }}</p>
                @foreach (['window_start_utc' => 'Start in UTC', 'window_end_utc' => 'End in UTC'] as $field => $label)
                    <label class="block" for="night-{{ $field }}">{{ __($label) }}</label>
                    <input id="night-{{ $field }}" name="{{ $field }}" value="{{ $input[$field] }}" maxlength="20" placeholder="2026-10-01T20:00:00Z" class="w-full rounded border p-3" style="background: var(--bg-elevated)" aria-invalid="{{ isset($validation[$field]) ? 'true' : 'false' }}" @if(isset($validation[$field])) aria-describedby="night-error-{{ $field }}" @endif>
                    @if(isset($validation[$field]))<p id="night-error-{{ $field }}">{{ implode(' ', $validation[$field]) }}</p>@endif
                @endforeach
            </fieldset>
            <div class="space-y-3">
                <label class="block" for="night-horizon">{{ __('Horizon profile (optional)') }}</label>
                <p>{{ __('Enter 2–72 lines, each with an azimuth and minimum altitude in degrees, separated by a space. North is 0°, east 90°, south 180°, west 270°. Heights interpolate between directions and across north; the larger of this profile and your minimum altitude applies. Blank means terrain is unknown.') }}</p>
                <textarea id="night-horizon" name="horizon" rows="4" maxlength="5000" placeholder="0 10&#10;90 25&#10;180 5&#10;270 15" class="w-full rounded border p-3" style="background: var(--bg-elevated)" aria-invalid="{{ isset($validation['horizon']) ? 'true' : 'false' }}" @if(isset($validation['horizon'])) aria-describedby="night-error-horizon" @endif>{{ $input['horizon'] }}</textarea>
                @if(isset($validation['horizon']))<p id="night-error-horizon">{{ implode(' ', $validation['horizon']) }}</p>@endif
            </div>
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
                <h2 id="night-summary" class="text-2xl">{{ __('Your night') }} · {{ $plan['night']['date'] }}</h2>
                <p>{{ $local($plan['night']['start_utc']) }} → {{ $local($plan['night']['end_utc']) }} · {{ $plan['observer']['timezone'] }} · {{ $plan['night']['duration_hours'] }} {{ __('hours') }}</p>
                <p>{{ __('Selected observing interval:') }} {{ $local($plan['constraints']['window_start_utc']) }} → {{ $local($plan['constraints']['window_end_utc']) }}. {{ $plan['constraints']['horizon_mask'] === null ? __('Terrain is unknown; only the baseline altitude was applied.') : __('Your supplied horizon profile was applied with the baseline altitude.') }}</p>
                <p><span data-night-private-location class="print:hidden">{{ __('Location:') }} {{ $plan['observer']['lat'] }}°, {{ $plan['observer']['lon'] }}° · </span>{{ __('Moon illuminated:') }} {{ number_format($plan['moon']['illumination_fraction'] * 100, 1) }}% {{ __('at') }} {{ $local($plan['moon']['reference_utc']) }}.</p>
                <p data-night-private-location class="print:hidden">{{ __('Terrain profile (azimuth°, minimum altitude°):') }} @if($plan['constraints']['horizon_mask'] === null) {{ __('Unknown') }} @else @foreach($plan['constraints']['horizon_mask'] as $point) {{ $point['azimuth_deg'] }}°, {{ $point['min_altitude_deg'] }}°{{ $loop->last ? '.' : '; ' }} @endforeach @endif</p>
                <p>{{ __('Darkness intervals:') }} @forelse ($plan['darkness']['intervals'] as $window){{ $local($window['start_utc']) }} – {{ $local($window['end_utc']) }}{{ $loop->last ? '.' : '; ' }} @empty {{ $plan['darkness']['status'] === 'unresolved_grazing' ? __('No confirmed interval; the boundary remains unresolved.') : __('None at the chosen threshold.') }} @endforelse</p>
                @if ($plan['darkness']['status'] === 'unresolved_grazing')<p>{{ __('Darkness is close to a grazing crossing; its boundary is unresolved in this model.') }}</p>@endif
            </section>
            @include('observing.partials-night-session')
            @foreach ($plan['targets'] as $target)
                <article class="surface mt-6 space-y-4 p-5" aria-labelledby="target-{{ $target['id'] }}">
                    <h2 id="target-{{ $target['id'] }}" class="text-2xl">{{ $target['name'] }}</h2>
                    @php
                        $journalTarget = \App\Services\Observing\NightTargets::journalIdentity($target['id']);
                    @endphp
                    <div class="print:hidden"><x-save-observing-target :catalogue="$journalTarget['catalogue']" :target-id="$journalTarget['id']" :target-label="$target['name']" /></div>
                    @if(isset($target['catalogue'])) @include('observing.partials-night-source', ['source' => $target['catalogue']]) @endif
                    @if ($target['status'] === 'unresolved_grazing')<p>{{ __('A constraint nearly touches its threshold. These provisional windows need independent checking.') }}</p>@endif
                    <p>{{ __('Windows satisfying altitude, darkness, Sun separation and your Moon constraint:') }}</p>
                    <ul class="list-disc pl-5">@forelse ($target['windows'] as $window)<li><time datetime="{{ $window['start_utc'] }}">{{ $local($window['start_utc']) }}</time> – <time datetime="{{ $window['end_utc'] }}">{{ $local($window['end_utc']) }}</time></li>@empty<li>{{ $target['status'] === 'unresolved_grazing' ? __('No confirmed window; a constraint boundary remains unresolved.') : __('No matching window in the selected observing interval. This does not mean the target never rises.') }}</li>@endforelse</ul>
                    @php
                        $points = implode(' ', array_map(static fn (array $s): string => number_format((strtotime($s['time_utc']) - $start) / $duration * 540 + 80, 2, '.', '').','.number_format(100 - $s['altitude_deg'], 2, '.', ''), $target['samples']));
                        $limits = implode(' ', array_map(static fn (array $s): string => number_format((strtotime($s['time_utc']) - $start) / $duration * 540 + 80, 2, '.', '').','.number_format(100 - $s['required_min_altitude_deg'], 2, '.', ''), $target['samples']));
                        $windowX = (strtotime($plan['constraints']['window_start_utc']) - $start) / $duration * 540 + 80;
                        $windowWidth = (strtotime($plan['constraints']['window_end_utc']) - strtotime($plan['constraints']['window_start_utc'])) / $duration * 540;
                    @endphp
                    <svg viewBox="0 0 640 220" class="w-full" role="img" aria-labelledby="chart-{{ $target['id'] }}">
                        <title id="chart-{{ $target['id'] }}">{{ __('Altitude through this night; full sample table follows.') }}</title>
                        <rect x="{{ $windowX }}" y="10" width="{{ $windowWidth }}" height="180" fill="currentColor" opacity="0.06" />
                        @foreach ([90, 45, 0, -45, -90] as $altitude)
                            <line x1="80" y1="{{ 100 - $altitude }}" x2="620" y2="{{ 100 - $altitude }}" stroke="currentColor" opacity="{{ $altitude === 0 ? '0.5' : '0.15' }}" />
                            <text x="70" y="{{ 100 - $altitude }}" dominant-baseline="middle" text-anchor="end" class="text-[24px] sm:text-[10px]" fill="currentColor">{{ $altitude }}°</text>
                        @endforeach
                        <polyline points="{{ $points }}" fill="none" stroke="currentColor" stroke-width="2" />
                        <polyline points="{{ $limits }}" fill="none" stroke="currentColor" stroke-width="1" stroke-dasharray="4 3" />
                        <text x="80" y="214" class="text-[22px] sm:text-[11px]" fill="currentColor">{{ __('Local noon') }}</text>
                        <text x="620" y="214" text-anchor="end" class="text-[22px] sm:text-[11px]" fill="currentColor">{{ __('Next noon') }}</text>
                    </svg>
                    <p class="text-sm">{{ __('Solid line: target altitude. Dashed line: required minimum altitude. Shading: selected observing interval. Darkness and angular-separation constraints also determine the listed windows.') }}</p>
                    <details>
                        <summary class="cursor-pointer py-3">{{ __('Altitude and direction sample table') }}</summary>
                        <div class="overflow-x-auto" tabindex="0" role="region" aria-label="{{ __('Scrollable sample table for :target', ['target' => $target['name']]) }}"><table class="w-full text-left text-sm tabular-nums">
                            <caption class="py-3 text-left">{{ __('Geometric samples; azimuth runs from north (0°) through east (90°). Windows use refined boundaries between samples.') }} {{ $plan['method']['sample_minutes'] }} {{ __('minute sampling interval.') }}</caption>
                            <thead><tr>@foreach (['Local time (UTC offset)', 'Altitude °', 'Azimuth °', 'Required altitude °', 'Terrain altitude °', 'Sun separation °', 'Moon separation °', 'Moon altitude °'] as $heading)<th scope="col" class="p-2">{{ __($heading) }}</th>@endforeach</tr></thead>
                            <tbody>@foreach ($target['samples'] as $sample)<tr><th scope="row" class="p-2 font-normal"><time datetime="{{ $sample['time_utc'] }}">{{ $local($sample['time_utc']) }}</time></th>@foreach (['altitude_deg', 'azimuth_deg', 'required_min_altitude_deg', 'horizon_altitude_deg', 'sun_separation_deg', 'moon_separation_deg', 'moon_altitude_deg'] as $field)<td class="p-2">{{ $sample[$field] === null ? __('Unknown') : number_format($sample[$field], 1) }}</td>@endforeach</tr>@endforeach</tbody>
                        </table></div>
                    </details>
                </article>
            @endforeach
            <section class="surface mt-6 space-y-3 p-5" aria-labelledby="night-method">
                <h2 id="night-method" class="text-xl">{{ __('Calculation and limits') }}</h2>
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

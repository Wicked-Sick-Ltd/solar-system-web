                <article class="surface mt-6 space-y-4 p-5" aria-labelledby="target-{{ $target['id'] }}">
                    <h2 id="target-{{ $target['id'] }}" tabindex="-1" class="text-2xl">{{ $target['name'] }}</h2>
                    @isset($candidate) @include('observing.partials-shortlist-reasons') @endisset
                    @php
                        $journalTarget = \App\Services\Observing\NightTargets::journalIdentity($target['id']);
                    @endphp
                    <div class="print:hidden"><x-save-observing-target :catalogue="$journalTarget['catalogue']" :target-id="$journalTarget['id']" :target-label="$target['name']" /></div>
                    @if(isset($target['catalogue'])) @include('observing.partials-night-source', ['source' => $target['catalogue']]) @endif
                    @if ($target['status'] === 'unresolved_grazing')<p>{{ __('A constraint nearly touches its threshold. These provisional windows need independent checking.') }}</p>@endif
                    @if(isset($target['constraint_coverage'])) @include('observing.partials-constraint-coverage', ['coverage' => $target['constraint_coverage']]) @endif
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

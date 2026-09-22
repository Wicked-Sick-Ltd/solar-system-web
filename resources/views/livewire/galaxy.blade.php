<div>
    <x-page-header :title="__('Galaxy explorer')" :eyebrow="__('Our stellar neighbourhood')"
        :lead="__('Locate known exoplanet systems around the Sun. Select a host to explore its planets.')" />
    @if($apiDown)
        <x-api-down :section="__('The galaxy explorer')" />
    @elseif($map && count($map->hosts))
        <div data-galaxy-root data-data-url="{{ route('galaxy.data') }}" wire:ignore>
            <div class="surface mb-4 flex flex-wrap items-end gap-4 p-4">
                <div><label for="galaxy-view" class="mb-2 block text-sm">{{ __('View') }}</label>
                    <select id="galaxy-view" data-view class="rounded border p-2" style="background: var(--bg); border-color: var(--border)">
                        <option value="nearby">{{ __('Nearby systems') }}</option><option value="galaxy">{{ __('Milky Way overview') }}</option>
                    </select></div>
                <div><label for="galaxy-radius" class="mb-2 block text-sm">{{ __('Distance from the Sun') }}</label>
                    <select id="galaxy-radius" data-radius class="rounded border p-2" style="background: var(--bg); border-color: var(--border)">
                        <option value="25">{{ __('Within 82 light-years') }}</option><option value="100">{{ __('Within 326 light-years') }}</option>
                        <option value="1000">{{ __('Within 3,262 light-years') }}</option><option value="all">{{ __('All measured systems') }}</option>
                    </select></div>
                <button type="button" data-reset class="rounded border px-4 py-2" style="border-color: var(--border)">{{ __('Reset camera') }}</button>
                <a class="ml-auto py-2 underline" href="{{ route('exoplanets.index') }}">{{ __('Browse exoplanets') }} →</a>
            </div>
            <div class="grid gap-4 lg:grid-cols-[1fr_18rem]">
                <div data-viewport class="relative overflow-hidden rounded-xl border" style="height: clamp(340px, 58vh, 650px); background: #070c18; border-color: var(--border)">
                    <span data-sun-label class="pointer-events-none absolute z-10 text-sm text-amber-200">{{ __('Sun') }}</span>
                    <span data-centre-label class="pointer-events-none absolute z-10 text-sm text-slate-300" hidden>{{ __('Galactic centre') }}</span>
                    <p data-map-status role="status" class="absolute bottom-3 left-3 z-10 max-w-[90%] rounded bg-slate-950/90 px-3 py-2 text-sm text-slate-200">{{ __('Loading 3D view…') }}</p>
                </div>
                <aside class="surface space-y-5 p-5">
                    <div><label for="galaxy-system" class="mb-2 block text-sm">{{ __('Choose a system') }}</label>
                        <select id="galaxy-system" data-system class="w-full rounded border p-2" style="background: var(--bg); border-color: var(--border)">
                            <option value="">{{ __('Select a host') }}</option>
                            @foreach($map->hosts as $host)<option value="{{ $host['id'] }}">{{ $host['name'] }}</option>@endforeach
                        </select></div>
                    <div data-selection aria-live="polite"><p>{{ __('Each blue point is a host system. The amber point is our Sun.') }}</p></div>
                    <p class="text-sm" style="color: var(--muted)">{{ __('Drag to rotate, scroll or pinch to zoom. Select a point or choose a system above. Use arrow keys on the map to pan; + and − zoom.') }}</p>
                    <p class="text-sm" style="color: var(--muted)">{{ __('Distances are to scale; marker sizes are enlarged for visibility. The galaxy outline is schematic.') }}</p>
                </aside>
            </div>
        </div>
        @vite('resources/js/galaxy.js')
        <div class="mt-5 space-y-2 text-sm" style="color: var(--muted)">
            <p>{{ __('The map shows known detections, not the distribution of all planets. Systems without usable positions are omitted: :count.', ['count' => $map->unmappedHosts]) }}</p>
            <p>{{ __('Positions use NASA catalogue astrometry, without stellar-motion propagation. Nearby axes follow Galactic coordinates. The overview places the Sun 8,122 parsecs from the Galactic centre and 20.8 parsecs above its plane.') }}</p>
            @if($map->truncated)<p>{{ __('The map response reached its display limit; browse the catalogue for additional systems.') }}</p>@endif
            <p><a class="underline" href="https://exoplanetarchive.ipac.caltech.edu/docs/PSCompPars.html">{{ __('Data: NASA Exoplanet Archive / PSCompPars') }}</a></p>
        </div>
        <details class="surface mt-6 p-5">
            <summary class="cursor-pointer">{{ __('Nearest systems — accessible list') }}</summary>
            <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                @foreach(array_slice($map->hosts, 0, 20) as $host)
                    <li><a class="underline" href="{{ route('systems.show', $host['id']) }}">{{ $host['name'] }}</a> · {{ \App\Support\Format::lightYears($host['distance_pc']) }}</li>
                @endforeach
            </ul>
        </details>
    @else
        <x-empty-state :title="__('No measured systems available')">{{ __('The catalogue has no usable 3D positions in this snapshot.') }}</x-empty-state>
    @endif
</div>

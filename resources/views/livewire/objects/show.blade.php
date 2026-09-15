@php
    use App\Support\Format;
    use App\Support\ObjectType;
    use App\Support\PlutoDistance;
@endphp
<div>
    @if ($apiDown)
        <x-page-header :title="__('Object detail')" />
        <x-api-down />
    @else
        @php
            $colour = $object->visual?->safeColourHex();
            $hasOrbital = $object->orbital?->hasAny();
            $hasPhysical = $object->physical?->hasAny();
            $hasVisual = $object->visual?->hasAny();
        @endphp

        {{-- Breadcrumb --}}
        <nav class="mb-6 flex flex-wrap items-center gap-1.5 text-sm" style="color: var(--muted);" aria-label="{{ __('Breadcrumb') }}">
            <a class="link-quiet" href="{{ route('home') }}">{{ __('Home') }}</a>
            <span aria-hidden="true">/</span>
            <a class="link-quiet" href="{{ route('objects.index') }}">{{ __('Objects') }}</a>
            <span aria-hidden="true">/</span>
            <span style="color: var(--text);">{{ $object->name }}</span>
        </nav>

        {{-- Title block --}}
        <header class="flex flex-col gap-6 sm:flex-row sm:items-start">
            <div aria-hidden="true" class="shrink-0">
                <span class="block h-20 w-20 rounded-full sm:h-24 sm:w-24"
                      style="background: radial-gradient(circle at 32% 30%, color-mix(in srgb, {{ $colour ?? 'var(--accent)' }} 92%, white), {{ $colour ?? 'var(--accent)' }} 62%, #05070d);
                             box-shadow: 0 0 36px color-mix(in srgb, {{ $colour ?? 'var(--accent)' }} 30%, transparent);"></span>
            </div>
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    @if ($object->typeLabel())
                        <x-badge tone="amber">{{ $object->typeLabel() }}</x-badge>
                    @endif
                    @if ($object->isNeo())
                        <x-badge tone="blue">{{ __('Near-Earth') }}</x-badge>
                    @endif
                    @if ($object->isPha())
                        <x-badge tone="danger">{{ __('Potentially hazardous') }}</x-badge>
                    @endif
                </div>
                <h1 class="mt-2 text-4xl font-medium">{{ $object->name }}</h1>
                @if ($object->designation && $object->designation !== $object->name)
                    <p class="mt-1 text-base" style="color: var(--color-faint);">{{ $object->designation }}</p>
                @endif
                <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-1 text-sm" style="color: var(--muted);">
                    @if ($parent)
                        <span>{{ __('Orbits') }}
                            <a class="font-medium" style="color: var(--link);" href="{{ route('objects.show', $parent->id) }}">{{ $parent->name }}</a>
                        </span>
                    @endif
                    @if ($object->wikipediaUrl)
                        <a class="font-medium" style="color: var(--link);" href="{{ $object->wikipediaUrl }}" rel="noopener" target="_blank">{{ __('Wikipedia ↗') }}</a>
                    @endif
                </div>
            </div>
        </header>

        @if ($object->notes)
            <p class="mt-6 text-base leading-relaxed" style="color: var(--text); max-width: var(--container-prose);">{{ $object->notes }}</p>
        @endif

        {{-- Pluto only: the pub-quiz dedication, with the distance in AU --}}
        @if (PlutoDistance::appliesTo($object))
            <x-proto-callout :distance="PlutoDistance::for($object, $position)" />
        @endif

        {{-- Where is it now --}}
        @if ($position)
            <section class="mt-10" aria-labelledby="position-heading">
                <h2 id="position-heading" class="mb-3 text-sm font-semibold uppercase tracking-[0.16em]" style="color: var(--accent);">{{ __('Where is it now') }}</h2>
                <div class="surface grid gap-6 p-6 sm:grid-cols-[1fr_auto] sm:items-center">
                    <div>
                        <dl class="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3">
                            <div>
                                <dt class="text-xs uppercase tracking-wide" style="color: var(--muted);">{{ __('Distance from Sun') }}</dt>
                                <dd class="font-serif text-xl tabular-nums" style="color: var(--text);">{{ Format::au($position->distanceFromSunAu) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase tracking-wide" style="color: var(--muted);">{{ __('True anomaly') }}</dt>
                                <dd class="font-serif text-xl tabular-nums" style="color: var(--text);">{{ Format::degrees($position->trueAnomalyDeg) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase tracking-wide" style="color: var(--muted);">{{ __('As of') }}</dt>
                                <dd class="text-sm" style="color: var(--text);">{{ Format::date($position->inputDate) }}</dd>
                            </div>
                        </dl>
                        @if ($position->accuracyNote)
                            <p class="mt-4 text-xs leading-relaxed" style="color: var(--color-faint); max-width: 60ch;">{{ $position->accuracyNote }}</p>
                        @endif
                    </div>
                    <div class="justify-self-center sm:justify-self-end">
                        <x-orbit-diagram :eccentricity="$object->orbital?->eccentricity ?? 0"
                                         :true-anomaly="$position->trueAnomalyDeg"
                                         :label="__('Orbit of :name', ['name' => $object->name])" />
                    </div>
                </div>
            </section>
        @endif

        {{-- Property cards --}}
        <div class="mt-10 grid gap-6 lg:grid-cols-2">
            @if ($hasOrbital)
                <section class="surface p-6" aria-labelledby="orbital-heading">
                    <h2 id="orbital-heading" class="mb-3 font-serif text-xl font-medium">{{ __('Orbital elements') }}</h2>
                    <dl>
                        @php $o = $object->orbital; @endphp
                        <x-prop-row :label="__('Semi-major axis')" :value="Format::au($o->semiMajorAxisAu)" />
                        <x-prop-row :label="__('Eccentricity')" :value="Format::number($o->eccentricity, 4)" />
                        <x-prop-row :label="__('Inclination')" :value="Format::degrees($o->inclinationDeg)" />
                        <x-prop-row :label="__('Orbital period')" :value="Format::periodDays($o->orbitalPeriodDays)" />
                        <x-prop-row :label="__('Perihelion')" :value="Format::au($o->perihelionAu)" />
                        <x-prop-row :label="__('Aphelion')" :value="Format::au($o->aphelionAu)" />
                        <x-prop-row :label="__('Longitude of ascending node')" :value="Format::degrees($o->longitudeAscendingNodeDeg)" />
                        <x-prop-row :label="__('Argument of periapsis')" :value="Format::degrees($o->argumentPeriapsisDeg)" />
                        <x-prop-row :label="__('Epoch')" :value="$o->epoch" :hint="$o->frame" />
                    </dl>
                </section>
            @endif

            @if ($hasPhysical)
                <section class="surface p-6" aria-labelledby="physical-heading">
                    <h2 id="physical-heading" class="mb-3 font-serif text-xl font-medium">{{ __('Physical properties') }}</h2>
                    <dl>
                        @php $p = $object->physical; @endphp
                        <x-prop-row :label="__('Mean radius')" :value="Format::km($p->radiusKm)" />
                        <x-prop-row :label="__('Equatorial radius')" :value="Format::km($p->equatorialRadiusKm)" />
                        <x-prop-row :label="__('Polar radius')" :value="Format::km($p->polarRadiusKm)" />
                        <x-prop-row :label="__('Mass')" :value="Format::massKg($p->massKg)" />
                        <x-prop-row :label="__('Density')" :value="Format::unit($p->densityGCm3, 'g/cm³', 3)" />
                        <x-prop-row :label="__('Rotation period')" :value="Format::hours($p->rotationPeriodHours)" />
                        <x-prop-row :label="__('Axial tilt')" :value="Format::degrees($p->axialTiltDeg)" />
                        <x-prop-row :label="__('Surface gravity')" :value="Format::unit($p->surfaceGravityMS2, 'm/s²', 2)" />
                        <x-prop-row :label="__('Escape velocity')" :value="Format::unit($p->escapeVelocityKmS, 'km/s', 2)" />
                    </dl>
                </section>
            @endif

            @if ($hasVisual)
                <section class="surface p-6" aria-labelledby="visual-heading">
                    <h2 id="visual-heading" class="mb-3 font-serif text-xl font-medium">{{ __('Visual & observation') }}</h2>
                    <dl>
                        @php $v = $object->visual; @endphp
                        <x-prop-row :label="__('Geometric albedo')" :value="Format::number($v->geometricAlbedo, 3)" />
                        <x-prop-row :label="__('Bond albedo')" :value="Format::number($v->bondAlbedo, 3)" />
                        <x-prop-row :label="__('Absolute magnitude (H)')" :value="Format::number($v->absoluteMagnitudeH, 2)" />
                        <x-prop-row :label="__('Colour index (B–V)')" :value="Format::number($v->colourBV, 2)" />
                        <x-prop-row :label="__('Spectral type')" :value="$v->spectralType" />
                        @if ($v->safeColourHex())
                            <x-prop-row :label="__('Representative colour')">
                                <span class="inline-flex items-center gap-2">
                                    <span class="inline-block h-3.5 w-3.5 rounded-full" style="background: {{ $v->safeColourHex() }};"></span>
                                    {{ $v->safeColourHex() }}
                                </span>
                            </x-prop-row>
                        @endif
                    </dl>
                </section>
            @endif

            @if ($object->discoverer || $object->discoveryDate || count($object->classifications))
                <section class="surface p-6" aria-labelledby="discovery-heading">
                    <h2 id="discovery-heading" class="mb-3 font-serif text-xl font-medium">{{ __('Discovery & classification') }}</h2>
                    <dl>
                        <x-prop-row :label="__('Discovered by')" :value="$object->discoverer" />
                        <x-prop-row :label="__('Discovery date')" :value="Format::date($object->discoveryDate)" />
                    </dl>
                    @if (count($object->classifications))
                        <div class="mt-4 flex flex-wrap gap-2">
                            @foreach ($object->classifications as $label)
                                <x-badge>{{ ObjectType::classification($label) }}</x-badge>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif
        </div>

        {{-- Rings --}}
        @if (count($rings))
            <section class="mt-10" aria-labelledby="rings-heading">
                <h2 id="rings-heading" class="mb-3 font-serif text-2xl font-medium">{{ __('Ring system') }}</h2>
                <div class="surface overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-left" style="border-color: var(--border); color: var(--muted);">
                                <th class="px-4 py-3 font-medium">{{ __('Ring') }}</th>
                                <th class="px-4 py-3 text-right font-medium">{{ __('Inner radius') }}</th>
                                <th class="px-4 py-3 text-right font-medium">{{ __('Outer radius') }}</th>
                                <th class="px-4 py-3 text-right font-medium">{{ __('Width') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rings as $ring)
                                <tr class="border-b last:border-0" style="border-color: var(--border);">
                                    <td class="px-4 py-3 font-medium" style="color: var(--text);">{{ $ring->name }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums" style="color: var(--muted);">{{ Format::km($ring->innerRadiusKm) ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums" style="color: var(--muted);">{{ Format::km($ring->outerRadiusKm) ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums" style="color: var(--muted);">{{ Format::km($ring->effectiveWidthKm()) ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        {{-- Moons (sortable) --}}
        @if (count($moons))
            @php
                $cols = [
                    ['field' => 'name', 'label' => __('Name'), 'align' => 'left'],
                    ['field' => 'radiusKm', 'label' => __('Radius'), 'align' => 'right'],
                    ['field' => 'semiMajorAxisAu', 'label' => __('Distance'), 'align' => 'right'],
                    ['field' => 'orbitalPeriodDays', 'label' => __('Period'), 'align' => 'right'],
                ];
            @endphp
            <section class="mt-10" aria-labelledby="moons-heading" wire:loading.class="opacity-60">
                <h2 id="moons-heading" class="mb-3 font-serif text-2xl font-medium">
                    {{ __(':count moons', ['count' => count($moons)]) }}
                </h2>
                <div class="surface overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-left" style="border-color: var(--border); color: var(--muted);">
                                @foreach ($cols as $col)
                                    <th class="px-4 py-3 font-medium @if ($col['align'] === 'right') text-right @endif">
                                        <button type="button" wire:click="sortBy('{{ $col['field'] }}')"
                                                class="inline-flex items-center gap-1 hover:underline"
                                                @if ($sortField === $col['field']) style="color: var(--accent);" @endif>
                                            {{ $col['label'] }}
                                            @if ($sortField === $col['field'])
                                                <span aria-hidden="true">{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                                            @endif
                                        </button>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($moons as $moon)
                                <tr class="border-b last:border-0" style="border-color: var(--border);" wire:key="moon-{{ $moon->id }}">
                                    <td class="px-4 py-3">
                                        <a class="font-medium" style="color: var(--link);" href="{{ route('objects.show', $moon->slug()) }}">{{ $moon->name }}</a>
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums" style="color: var(--muted);">{{ Format::km($moon->radiusKm) ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums" style="color: var(--muted);">{{ Format::au($moon->semiMajorAxisAu, 4) ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums" style="color: var(--muted);">{{ Format::periodDays($moon->orbitalPeriodDays) ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        {{-- Sources --}}
        @if (count($object->sources))
            <section class="mt-10" aria-labelledby="sources-heading">
                <h2 id="sources-heading" class="mb-3 text-sm font-semibold uppercase tracking-[0.16em]" style="color: var(--accent);">{{ __('Sources') }}</h2>
                <ul class="space-y-2 text-sm" style="color: var(--muted);">
                    @foreach ($object->sources as $source)
                        <li class="flex flex-wrap items-baseline gap-x-2">
                            @if ($source->tableName)
                                <span class="text-xs" style="color: var(--color-faint);">{{ str_replace('_', ' ', $source->tableName) }}:</span>
                            @endif
                            @if ($source->sourceUrl)
                                <a class="link-quiet underline" href="{{ $source->sourceUrl }}" rel="noopener" target="_blank">{{ $source->sourceName }}</a>
                            @else
                                <span>{{ $source->sourceName }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    @endif
</div>

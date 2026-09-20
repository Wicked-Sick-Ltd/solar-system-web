@php use App\Support\Format; @endphp
<div>
    {{-- Hero --}}
    <section class="pt-6">
        <p class="mb-3 text-xs font-semibold uppercase tracking-[0.16em]" style="color: var(--accent);">
            {{ config('site.tagline') }}
        </p>
        <h1 class="text-4xl font-medium leading-tight sm:text-5xl" style="max-width: 18ch;">
            {{ __('The solar system, catalogued and freely browseable.') }}
        </h1>
        <p class="mt-5 text-lg leading-relaxed" style="color: var(--muted); max-width: var(--container-prose);">
            {{ __('Planets, moons, dwarf planets, asteroids, comets and the icy worlds beyond Neptune — drawn from NASA/JPL and the IAU Minor Planet Center, refreshed nightly. The underlying data is public domain and free for you to use.') }}
        </p>
        <div class="mt-6 flex flex-wrap items-center gap-3">
            <a href="{{ route('objects.index') }}"
               class="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold"
               style="background-color: var(--accent); color: #07090f;">
                {{ __('Browse the catalogue') }}
            </a>
            <a href="{{ route('api') }}"
               class="inline-flex items-center gap-2 rounded-lg border px-4 py-2.5 text-sm font-medium"
               style="border-color: var(--border); color: var(--text);">
                {{ __('Use the API') }}
            </a>
        </div>
    </section>

    {{-- Stats strip --}}
    <section class="mt-12" aria-label="{{ __('Catalogue statistics') }}">
        @if ($apiDown || ! $stats)
            <x-api-down :section="__('Live catalogue counts')" />
        @else
            <div class="surface grid grid-cols-2 divide-x divide-y sm:grid-cols-4 lg:grid-cols-5"
                 style="--tw-divide-opacity: 1; border-color: var(--border);">
                @php
                    $strip = [
                        ['n' => $stats->totalObjects, 'label' => __('objects')],
                        ['n' => $stats->planets(), 'label' => __('planets')],
                        ['n' => $stats->moons(), 'label' => __('moons')],
                        ['n' => $stats->asteroids(), 'label' => __('asteroids')],
                        ['n' => $stats->comets(), 'label' => __('comets')],
                    ];
                @endphp
                @foreach ($strip as $stat)
                    <div class="p-5" style="border-color: var(--border);">
                        <div class="font-serif text-2xl font-medium tabular-nums" style="color: var(--text);">
                            {{ Format::count($stat['n']) }}
                        </div>
                        <div class="text-xs uppercase tracking-wide" style="color: var(--muted);">{{ $stat['label'] }}</div>
                    </div>
                @endforeach
            </div>
            @if ($stats->lastRefreshed())
                <p class="mt-3 text-xs" style="color: var(--color-faint);">
                    {{ __('Catalogue last refreshed :when.', ['when' => $stats->lastRefreshed()->diffForHumans()]) }}
                    <time datetime="{{ $stats->lastRefreshed()->toIso8601String() }}"></time>
                </p>
            @endif
        @endif
    </section>

    {{-- Featured object today --}}
    @if ($featured)
        <section class="mt-14" aria-labelledby="featured-heading">
            <div class="mb-4 flex items-baseline justify-between">
                <h2 id="featured-heading" class="text-sm font-semibold uppercase tracking-[0.16em]" style="color: var(--accent);">
                    {{ __('Featured today') }}
                </h2>
            </div>
            <a href="{{ route('objects.show', $featured->slug()) }}"
               class="surface block overflow-hidden p-6 transition-colors sm:p-8"
               onmouseover="this.style.borderColor='color-mix(in srgb, var(--accent) 45%, var(--border))'"
               onmouseout="this.style.borderColor='var(--border)'">
                <div class="flex flex-col gap-6 sm:flex-row sm:items-center">
                    @php $colour = $featured->visual?->safeColourHex(); @endphp
                    <div class="shrink-0">
                        <span aria-hidden="true" class="block h-24 w-24 rounded-full sm:h-28 sm:w-28"
                              style="background: radial-gradient(circle at 32% 30%, color-mix(in srgb, {{ $colour ?? 'var(--accent)' }} 90%, white), {{ $colour ?? 'var(--accent)' }} 65%, #05070d);
                                     box-shadow: 0 0 40px color-mix(in srgb, {{ $colour ?? 'var(--accent)' }} 35%, transparent);"></span>
                    </div>
                    <div class="min-w-0">
                        @if ($featured->typeLabel())
                            <x-badge tone="amber">{{ $featured->typeLabel() }}</x-badge>
                        @endif
                        <h3 class="mt-2 font-serif text-3xl font-medium" style="color: var(--text);">{{ $featured->name }}</h3>
                        @if ($featured->designation && $featured->designation !== $featured->name)
                            <p class="text-sm" style="color: var(--color-faint);">{{ $featured->designation }}</p>
                        @endif
                        <dl class="mt-4 flex flex-wrap gap-x-8 gap-y-2 text-sm tabular-nums" style="color: var(--muted);">
                            @if ($d = Format::km($featured->physical?->diameterKm()))
                                <div><dt class="text-xs uppercase tracking-wide">{{ __('Diameter') }}</dt><dd style="color: var(--text);">{{ $d }}</dd></div>
                            @endif
                            @if ($a = Format::au($featured->orbital?->semiMajorAxisAu))
                                <div><dt class="text-xs uppercase tracking-wide">{{ __('Distance') }}</dt><dd style="color: var(--text);">{{ $a }}</dd></div>
                            @endif
                            @if ($p = Format::periodDays($featured->orbital?->orbitalPeriodDays))
                                <div><dt class="text-xs uppercase tracking-wide">{{ __('Orbital period') }}</dt><dd style="color: var(--text);">{{ $p }}</dd></div>
                            @endif
                        </dl>
                        @if ($featured->notes)
                            <p class="mt-4 text-sm leading-relaxed" style="color: var(--muted); max-width: 60ch;">{{ \Illuminate\Support\Str::limit($featured->notes, 180) }}</p>
                        @endif
                    </div>
                </div>
            </a>
        </section>
    @endif

    {{-- Quick-browse sections --}}
    <section class="mt-14" aria-labelledby="browse-heading">
        <h2 id="browse-heading" class="mb-4 text-sm font-semibold uppercase tracking-[0.16em]" style="color: var(--accent);">
            {{ __('Browse by kind') }}
        </h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($sections as $section)
                <a href="{{ route($section['route'], $section['params'] ?? []) }}"
                   class="surface group flex flex-col gap-2 p-5 transition-colors"
                   onmouseover="this.style.borderColor='color-mix(in srgb, var(--accent) 45%, var(--border))'"
                   onmouseout="this.style.borderColor='var(--border)'">
                    <div class="flex items-baseline justify-between gap-3">
                        <h3 class="font-serif text-xl font-medium" style="color: var(--text);">{{ $section['label'] }}</h3>
                        @if ($section['count'] !== null)
                            <span class="text-sm tabular-nums" style="color: var(--accent);">{{ Format::count($section['count']) }}</span>
                        @endif
                    </div>
                    <p class="text-sm leading-relaxed" style="color: var(--muted);">{{ $section['blurb'] }}</p>
                </a>
            @endforeach
        </div>
    </section>
</div>

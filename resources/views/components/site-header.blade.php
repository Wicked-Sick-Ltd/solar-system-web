@php
    $nav = [
        ['label' => __('Planets'), 'route' => 'planets.index', 'active' => 'planets*'],
        ['label' => __('Dwarf planets'), 'route' => 'dwarf-planets', 'active' => 'dwarf-planets'],
        ['label' => __('Asteroids'), 'route' => 'asteroids', 'active' => 'asteroids'],
        ['label' => __('Comets'), 'route' => 'comets', 'active' => 'comets'],
        ['label' => __('TNOs'), 'route' => 'tnos', 'active' => 'tnos'],
        ['label' => __('Exoplanets'), 'route' => 'exoplanets.index', 'active' => 'exoplanets*'],
        ['label' => __('All objects'), 'route' => 'objects.index', 'active' => 'objects*'],
    ];
@endphp

<header x-data="{ open: false }"
        class="sticky top-0 z-40 border-b backdrop-blur"
        style="border-color: var(--border); background-color: color-mix(in srgb, var(--bg) 88%, transparent);">
    <div class="mx-auto flex w-full max-w-6xl items-center gap-4 px-4 py-3 sm:px-6 lg:px-8">
        {{-- Wordmark --}}
        <a href="{{ route('home') }}" class="group flex items-center gap-2.5">
            <span aria-hidden="true" class="inline-block h-3.5 w-3.5 rounded-full"
                  style="background: radial-gradient(circle at 30% 30%, var(--color-amber-soft), var(--accent) 60%, #8a6a2a); box-shadow: 0 0 14px color-mix(in srgb, var(--accent) 60%, transparent);"></span>
            <span class="wordmark text-xl font-medium" style="color: var(--text);">{{ config('site.name') }}</span>
        </a>

        {{-- Desktop nav --}}
        <nav class="ml-2 hidden items-center gap-1 lg:flex" aria-label="{{ __('Primary') }}">
            @foreach ($nav as $item)
                <a href="{{ route($item['route']) }}"
                   @class([
                       'rounded-md px-2 py-2 text-sm font-medium transition-colors',
                   ])
                   @style([
                       'color: var(--accent)' => request()->routeIs($item['active']),
                       'color: var(--muted)' => ! request()->routeIs($item['active']),
                   ])
                   onmouseover="this.style.color='var(--text)'"
                   onmouseout="this.style.color='{{ request()->routeIs($item['active']) ? 'var(--accent)' : 'var(--muted)' }}'"
                   @if (request()->routeIs($item['active'])) aria-current="page" @endif>
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="ml-auto flex items-center gap-2">
            {{-- Search (compact) --}}
            <form action="{{ route('search') }}" method="GET" role="search"
                  class="hidden items-center md:flex">
                <label for="header-search" class="sr-only">{{ __('Search the catalogue') }}</label>
                <div class="flex items-center rounded-lg border px-2.5"
                     style="border-color: var(--border); background-color: var(--bg-elevated);">
                    <svg class="h-4 w-4 shrink-0" style="color: var(--muted)" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <circle cx="9" cy="9" r="6" stroke="currentColor" stroke-width="1.6"/>
                        <path d="m18 18-4.5-4.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                    <input id="header-search" type="search" name="q" value="{{ request('q') }}"
                           placeholder="{{ __('Search…') }}" autocomplete="off"
                           class="w-36 bg-transparent px-2 py-1.5 text-sm focus:outline-none lg:w-32"
                           style="color: var(--text);">
                </div>
            </form>

            {{-- Random object --}}
            <a href="{{ route('random') }}" title="{{ __('Jump to a random object') }}"
               class="hidden items-center justify-center rounded-lg border p-2 sm:inline-flex"
               style="border-color: var(--border); color: var(--muted);"
               aria-label="{{ __('Random object') }}">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <rect x="2.5" y="2.5" width="15" height="15" rx="3" stroke="currentColor" stroke-width="1.5"/>
                    <circle cx="7" cy="7" r="1.2" fill="currentColor"/>
                    <circle cx="13" cy="13" r="1.2" fill="currentColor"/>
                    <circle cx="13" cy="7" r="1.2" fill="currentColor"/>
                    <circle cx="7" cy="13" r="1.2" fill="currentColor"/>
                </svg>
            </a>

            {{-- Theme toggle --}}
            <button type="button"
                    x-data="{
                        theme: document.documentElement.getAttribute('data-theme') || 'dark',
                        toggle() {
                            window.applyTheme(this.theme === 'dark' ? 'light' : 'dark');
                        }
                    }"
                    x-on:theme-changed.window="theme = $event.detail.theme"
                    @click="toggle()"
                    class="inline-flex items-center justify-center rounded-lg border p-2"
                    style="border-color: var(--border); color: var(--muted);"
                    :aria-label="theme === 'dark' ? '{{ __('Switch to light theme') }}' : '{{ __('Switch to dark theme') }}'">
                <svg x-show="theme === 'dark'" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M15.5 11.5A6 6 0 0 1 8.5 4.5a.5.5 0 0 0-.7-.6 7 7 0 1 0 8.3 8.3.5.5 0 0 0-.6-.7Z"/>
                </svg>
                <svg x-show="theme === 'light'" x-cloak class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M10 5a5 5 0 1 0 0 10 5 5 0 0 0 0-10Zm0-4a.9.9 0 0 1 .9.9V3a.9.9 0 0 1-1.8 0V1.9A.9.9 0 0 1 10 1Zm0 15.2a.9.9 0 0 1 .9.9v1.1a.9.9 0 1 1-1.8 0v-1.1a.9.9 0 0 1 .9-.9ZM19 10a.9.9 0 0 1-.9.9H17a.9.9 0 0 1 0-1.8h1.1A.9.9 0 0 1 19 10ZM3.9 10a.9.9 0 0 1-.9.9H1.9a.9.9 0 0 1 0-1.8H3a.9.9 0 0 1 .9.9Zm12.4-6.3a.9.9 0 0 1 0 1.3l-.8.8a.9.9 0 1 1-1.3-1.3l.8-.8a.9.9 0 0 1 1.3 0ZM5.8 14.2a.9.9 0 0 1 0 1.3l-.8.8a.9.9 0 0 1-1.3-1.3l.8-.8a.9.9 0 0 1 1.3 0Zm10.5.8a.9.9 0 0 1-1.3 1.3l-.8-.8a.9.9 0 0 1 1.3-1.3l.8.8ZM5.8 5.8A.9.9 0 0 1 4.5 7l-.8-.8a.9.9 0 0 1 1.3-1.3l.8.8Z"/>
                </svg>
            </button>

            {{-- Mobile menu button --}}
            <button type="button" @click="open = !open"
                    class="inline-flex items-center justify-center rounded-lg border p-2 lg:hidden"
                    style="border-color: var(--border); color: var(--muted);"
                    :aria-expanded="open" aria-controls="mobile-nav" aria-label="{{ __('Menu') }}">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                    <path x-show="!open" d="M3 6h14M3 10h14M3 14h14" stroke-linecap="round"/>
                    <path x-show="open" x-cloak d="M5 5l10 10M15 5 5 15" stroke-linecap="round"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- Mobile nav drawer --}}
    <nav id="mobile-nav" x-show="open" x-cloak x-collapse class="border-t lg:hidden"
         style="border-color: var(--border); background-color: var(--bg-elevated);"
         aria-label="{{ __('Primary') }}">
        <div class="mx-auto w-full max-w-6xl px-4 py-3 sm:px-6">
            <form action="{{ route('search') }}" method="GET" role="search" class="mb-3 md:hidden">
                <label for="mobile-search" class="sr-only">{{ __('Search the catalogue') }}</label>
                <input id="mobile-search" type="search" name="q" value="{{ request('q') }}"
                       placeholder="{{ __('Search the catalogue…') }}" autocomplete="off"
                       class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none"
                       style="border-color: var(--border); background-color: var(--bg); color: var(--text);">
            </form>
            <div class="grid gap-1">
                @foreach ($nav as $item)
                    <a href="{{ route($item['route']) }}"
                       class="rounded-md px-3 py-2 text-sm font-medium"
                       @style([
                           'color: var(--accent)' => request()->routeIs($item['active']),
                           'color: var(--text)' => ! request()->routeIs($item['active']),
                       ])
                       @if (request()->routeIs($item['active'])) aria-current="page" @endif>
                        {{ $item['label'] }}
                    </a>
                @endforeach
                <a href="{{ route('random') }}" class="rounded-md px-3 py-2 text-sm font-medium" style="color: var(--text);">{{ __('Random object') }}</a>
                <a href="{{ route('about') }}" class="rounded-md px-3 py-2 text-sm font-medium" style="color: var(--text);">{{ __('About') }}</a>
            </div>
        </div>
    </nav>
</header>

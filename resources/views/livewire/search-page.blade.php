<div>
    <x-page-header :title="__('Search')" :eyebrow="__('Find an object')" />

    <form action="{{ route('search') }}" method="get" role="search" class="mb-8">
        <label for="search-q" class="sr-only">{{ __('Search the catalogue') }}</label>
        <div class="flex items-center rounded-xl border px-4 focus-within:ring-2 focus-within:ring-[var(--accent)]"
             style="border-color: var(--border); background-color: var(--bg-elevated);">
            <svg class="h-5 w-5 shrink-0" style="color: var(--muted)" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                <circle cx="9" cy="9" r="6" stroke="currentColor" stroke-width="1.6"/>
                <path d="m18 18-4.5-4.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
            <input id="search-q" name="q" type="search" value="{{ $q }}" wire:model.live.debounce.300ms="q"
                   maxlength="{{ \App\Livewire\SearchPage::QUERY_LIMIT }}" aria-describedby="search-help{{ $queryTooLong ? ' search-error' : '' }}"
                   @if ($queryTooLong) aria-invalid="true" @endif
                   placeholder="{{ __('Try “Saturn”, “Halley”, “TRAPPIST-1”…') }}" autocomplete="off" autofocus
                   class="min-w-0 w-full bg-transparent px-3 py-3.5 text-lg focus:outline-none" style="color: var(--text);">
            <button type="submit" class="rounded px-2 py-2 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--accent)]" style="color: var(--accent);">{{ __('Search') }}</button>
        </div>
        <p id="search-help" class="mt-2 text-sm" style="color: var(--muted);">{{ __('Find solar-system objects by name, designation or discoverer, and exoplanets by planet or host name.') }}</p>
        <p wire:loading wire:target="q" role="status" class="mt-2 text-sm" style="color: var(--accent);">{{ __('Searching…') }}</p>
    </form>

    @if ($queryTooLong)
        <p id="search-error" role="alert" class="surface p-5">{{ __('Please use a search of :limit characters or fewer.', ['limit' => \App\Livewire\SearchPage::QUERY_LIMIT]) }}</p>
    @elseif ($query === '')
        <x-empty-state :title="__('Search the catalogue')">
            {{ __('Start typing to find planets, moons, asteroids, comets and worlds beyond our solar system.') }}
        </x-empty-state>
    @else
        <p role="status" aria-live="polite" aria-atomic="true" class="mb-6 text-sm" style="color: var(--muted);">
            @if ($solarUnavailable && $exoplanetsUnavailable)
                {{ __('Search is temporarily unavailable. Please try again in a moment.') }}
            @elseif ($solarUnavailable || $exoplanetsUnavailable)
                {{ __('Showing results from one catalogue. The other catalogue is temporarily unavailable.') }}
            @elseif (count($results) === 0 && $exoplanets->isEmpty())
                {{ __('No matches for “:q”. Check the spelling or try another name.', ['q' => $query]) }}
            @else
                {{ trans_choice(':count result shown|:count results shown', count($results) + $exoplanets->count(), ['count' => count($results) + $exoplanets->count()]) }}
            @endif
        </p>

        <div class="space-y-8">
            <section aria-labelledby="solar-results-heading">
                <h2 id="solar-results-heading" class="mb-3 text-xl font-semibold">{{ __('Solar system') }}</h2>
                @if ($solarUnavailable)
                    <x-api-down :section="__('Solar-system search')" />
                @elseif (count($results) === 0)
                    <p class="surface p-5" style="color: var(--muted);">{{ __('No solar-system objects match this search.') }}</p>
                @else
                    <ul class="surface divide-y" style="border-color: var(--border);">
                        @foreach ($results as $result)
                            <li style="border-color: var(--border);" wire:key="solar-{{ $result->id }}">
                                <a href="{{ route('objects.show', $result->slug()) }}"
                                   class="flex items-center justify-between gap-4 rounded px-4 py-3.5 transition-colors hover:bg-[var(--bg-elevated-2)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--accent)]">
                                    <div class="min-w-0">
                                        <p class="truncate font-medium" style="color: var(--text);">{{ $result->name }}</p>
                                        @if ($result->designation && $result->designation !== $result->name)
                                            <p class="truncate text-xs" style="color: var(--color-faint);">{{ $result->designation }}</p>
                                        @endif
                                    </div>
                                    @if ($result->typeLabel())
                                        <x-badge class="shrink-0">{{ $result->typeLabel() }}</x-badge>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    @if ($solarHasMore)
                        <p class="mt-3 text-sm" style="color: var(--muted);">{{ __('Showing the first :count solar-system matches. Refine your search to find a specific object.', ['count' => count($results)]) }}</p>
                    @endif
                @endif
            </section>

            <section aria-labelledby="exoplanet-results-heading">
                <h2 id="exoplanet-results-heading" class="mb-3 text-xl font-semibold">{{ __('Exoplanets') }}</h2>
                @if ($exoplanetsUnavailable)
                    <x-api-down :section="__('Exoplanet search')" />
                @elseif ($exoplanets->isEmpty())
                    <p class="surface p-5" style="color: var(--muted);">{{ __('No exoplanets or host systems match this search.') }}</p>
                @else
                    <ul class="surface divide-y" style="border-color: var(--border);">
                        @foreach ($exoplanets->items as $planet)
                            <li class="px-4 py-3.5" style="border-color: var(--border);" wire:key="exoplanet-{{ $planet->id }}">
                                <div class="flex items-center justify-between gap-4">
                                    <a href="{{ route('exoplanets.show', $planet->id) }}" class="min-w-0 break-words rounded font-medium underline decoration-transparent underline-offset-4 hover:decoration-current focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--accent)]">{{ $planet->name }}</a>
                                    <x-badge class="shrink-0">{{ __('Exoplanet') }}</x-badge>
                                </div>
                                @if ($planet->hostId !== '' && $planet->hostName !== '')
                                    <p class="mt-1 text-sm" style="color: var(--muted);">{{ __('Host system:') }} <a href="{{ route('systems.show', $planet->hostId) }}" class="rounded underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--accent)]">{{ $planet->hostName }}</a></p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    @if ($exoplanets->hasMore)
                        <a href="{{ route('exoplanets.index', ['q' => $query]) }}" class="mt-3 inline-block rounded text-sm underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--accent)]" style="color: var(--accent);">{{ __('Browse all matching exoplanets') }}</a>
                    @endif
                @endif
            </section>
        </div>
    @endif
</div>

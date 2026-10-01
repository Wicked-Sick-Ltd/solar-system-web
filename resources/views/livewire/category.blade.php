<div>
    <x-page-header :title="$copy['title']" :eyebrow="$copy['eyebrow']" :lead="$copy['lead']" />

    @if ($kind === 'asteroid')
        <form action="{{ route('asteroids') }}" method="get" class="surface mb-8 space-y-5 p-5" aria-label="{{ __('Asteroid filters') }}">
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold">{{ __('Filter asteroids') }}</h2>
                <a href="{{ route('asteroids', $order === 'id' ? ['order' => 'id'] : []) }}" wire:click.prevent="clearFilters" class="rounded text-sm underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--accent)]">{{ __('Clear filters') }}</a>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach (['orbit' => __('Orbit class'), 'diameter' => __('Minimum diameter'), 'moid' => __('Earth MOID'), 'quality' => __('Orbit uncertainty'), 'order' => __('Browse order')] as $field => $label)
                    <div>
                        <label for="asteroid-{{ $field }}" class="mb-1.5 block text-sm">{{ $label }}</label>
                        <x-form-select :id="'asteroid-'.$field" :name="$field" wire:model.live="{{ $field }}" :options="$filterOptions[$field]" :selected="${$field}" class="focus-visible:ring-2 focus-visible:ring-[var(--accent)]" />
                    </div>
                @endforeach
                <div>
                    <label for="asteroid-discovered" class="mb-1.5 block text-sm">{{ __('Discovered in or after year') }}</label>
                    <input id="asteroid-discovered" name="discovered" type="number" min="1600" max="{{ date('Y') }}" step="1" value="{{ $discovered }}" wire:model.live.debounce.400ms="discovered" placeholder="{{ __('Any year') }}" class="w-full rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border); background-color: var(--bg);">
                </div>
            </div>
            <div class="flex flex-wrap gap-x-6 gap-y-3">
                @foreach (['neo' => __('Near-Earth only'), 'pha' => __('Potentially hazardous only'), 'named' => __('Named only')] as $field => $label)
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="{{ $field }}" value="1" wire:model.live="{{ $field }}" @checked(${$field}) style="accent-color: var(--accent);"> {{ $label }}</label>
                @endforeach
                <button type="submit" class="rounded text-sm font-medium underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--accent)]">{{ __('Apply filters') }}</button>
            </div>
            <p class="text-sm" style="color: var(--muted);">{{ __('Earth MOID is the minimum distance between two orbital paths, not a predicted close approach. Potentially hazardous is a classification, not a prediction of impact. Filters exclude objects without the required measurements.') }}</p>
            <p class="text-sm" style="color: var(--muted);">{{ __('Orbital-distance pages retain the usual catalogue order. Choose catalogue ID to browse the full catalogue from the beginning in a consistent order.') }}</p>
        </form>
    @endif

    @if ($inputErrors !== [])
        <div class="surface space-y-3 p-5" role="alert">
            @foreach ($inputErrors as $error)
                <p>{{ $error }}</p>
            @endforeach
            @if ($kind === 'asteroid')
                <a href="{{ route('asteroids') }}" class="inline-block underline">{{ __('Reset filters and position') }}</a>
                <a href="{{ $fullCatalogueUrl }}" class="inline-block underline">{{ __('Browse full catalogue by ID') }}</a>
            @endif
        </div>
    @elseif ($apiDown)
        <x-api-down :section="$copy['title']" />
    @elseif ($results->isEmpty())
        <x-empty-state :title="$kind === 'asteroid' ? __('No asteroids match these filters at this position') : __('Nothing here yet')">
            @if ($kind === 'asteroid')
                {{ __('Widen the filters above or return to the first results. Missing measurements are excluded when their filter is active.') }}
                <a href="{{ $firstUrl }}" class="mt-3 block underline">{{ __('First results with these filters') }}</a>
                <a href="{{ route('search') }}" class="mt-2 block underline">{{ __('Search by name or designation') }}</a>
            @endif
        </x-empty-state>
    @else
        @if ($kind === 'asteroid')
            <p role="status" aria-live="polite" class="mb-4 text-sm" style="color: var(--muted);">
                @if ($order === 'id')
                    {{ __('Showing :count objects in catalogue ID order', ['count' => $results->count()]) }}
                @else
                    {{ __('Showing :from–:to', ['from' => $results->from(), 'to' => $results->to()]) }}
                @endif
            </p>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" wire:loading.class="opacity-50">
                @foreach ($results->items as $object)
                    <x-object-card :object="$object" wire:key="cat-{{ $object->id }}" />
                @endforeach
            </div>
            <nav class="mt-8 flex flex-wrap items-center justify-between gap-4 text-sm" aria-label="{{ __('Page navigation') }}">
                @if ($order === 'id')
                    <a href="{{ $firstUrl }}" class="rounded border px-4 py-2" style="border-color: var(--border);">{{ __('First results') }}</a>
                    @if ($results->hasMore && $results->nextAfter !== null)
                        <a href="{{ $nextUrl }}" rel="next" class="rounded border px-4 py-2" style="border-color: var(--border);">{{ __('Next') }} →</a>
                    @endif
                @else
                    @if ($page > 1)
                        <a href="{{ $previousUrl }}" rel="prev" class="rounded border px-4 py-2" style="border-color: var(--border);">← {{ __('Previous') }}</a>
                    @else
                        <span></span>
                    @endif
                    <span>{{ __('Page :n', ['n' => $page]) }}</span>
                    @if ($results->hasMore && ! $atPageLimit)
                        <a href="{{ $nextUrl }}" rel="next" class="rounded border px-4 py-2" style="border-color: var(--border);">{{ __('Next') }} →</a>
                    @elseif ($results->hasMore)
                        <a href="{{ $fullCatalogueUrl }}" class="rounded border px-4 py-2" style="border-color: var(--border);">{{ __('Browse full catalogue by ID from the beginning') }}</a>
                    @endif
                @endif
            </nav>
        @elseif ($paginated)
            <x-pagination :results="$results" :page="$page">
                @foreach ($results->items as $object)
                    <x-object-card :object="$object" wire:key="cat-{{ $object->id }}" />
                @endforeach
            </x-pagination>
        @else
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($results->items as $object)
                    <x-object-card :object="$object" wire:key="cat-{{ $object->id }}" />
                @endforeach
            </div>
        @endif
    @endif
</div>

<div>
    <x-page-header :title="__('All objects')"
                   :eyebrow="__('Catalogue')"
                   :lead="__('Every body in the catalogue, filterable by type, parent, size and near-Earth status. Filters live in the URL, so any view you reach is a shareable link.')" />

    <div class="grid gap-8 lg:grid-cols-[16rem_1fr]">
        <aside class="lg:sticky lg:top-20 lg:self-start" aria-label="{{ __('Filters') }}">
            <form action="{{ route('objects.index') }}" method="get" wire:submit="applyFilters" class="surface space-y-5 p-5" aria-label="{{ __('Object filters') }}">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold" style="color: var(--text);">{{ __('Filter') }}</h2>
                    @if ($hasFilters)
                        <a href="{{ route('objects.index') }}" wire:navigate class="text-xs underline" style="color: var(--link);">
                            {{ __('Clear all') }}
                        </a>
                    @endif
                </div>

                <div>
                    <label for="f-type" class="mb-1.5 block text-xs font-medium uppercase tracking-wide" style="color: var(--muted);">{{ __('Type') }}</label>
                    <x-form-select id="f-type" name="type" wire:model.live="type" :options="$typeOptions" :selected="$type" />
                </div>

                <div>
                    <label for="f-parent" class="mb-1.5 block text-xs font-medium uppercase tracking-wide" style="color: var(--muted);">{{ __('Parent body') }}</label>
                    <x-form-select id="f-parent" name="parent" wire:model.live="parent" :options="$parentOptions" :selected="$parent" />
                </div>

                <div>
                    <label for="f-size" class="mb-1.5 block text-xs font-medium uppercase tracking-wide" style="color: var(--muted);">{{ __('Size') }}</label>
                    <x-form-select id="f-size" name="size" wire:model.live="size" :options="$sizeOptions" :selected="$size" />
                </div>

                <div class="space-y-2.5 border-t pt-4" style="border-color: var(--border);">
                    <label class="flex items-center gap-2.5 text-sm" style="color: var(--text);">
                        <input type="checkbox" name="neo" value="1" @checked($neo) wire:model.live="neo" class="rounded" style="accent-color: var(--accent);">
                        {{ __('Near-Earth objects only') }}
                    </label>
                    <label class="flex items-center gap-2.5 text-sm" style="color: var(--text);">
                        <input type="checkbox" name="named" value="1" @checked($named) wire:model.live="named" class="rounded" style="accent-color: var(--accent);">
                        {{ __('Named objects only') }}
                    </label>
                </div>
                <button type="submit" class="rounded border px-4 py-2 text-sm" style="border-color: var(--border);">{{ __('Apply filters') }}</button>
                <p class="text-sm" style="color: var(--muted);">{{ __('Size ranges use radius, include both stated bounds, and exclude objects without a reported radius.') }}</p>
            </form>
        </aside>

        <div>
            <p wire:loading role="status" class="mb-4 text-sm" style="color: var(--accent);">{{ __('Updating objects…') }}</p>
            @if ($rawInputErrors !== [])
                <div class="surface space-y-3 p-5" role="alert">
                    @foreach ($rawInputErrors as $error)<p>{{ $error }}</p>@endforeach
                    <a href="{{ route('objects.index') }}" wire:navigate class="underline">{{ __('Reset filters and page') }}</a>
                </div>
            @elseif ($apiDown)
                <x-api-down :section="__('The object catalogue')" />
            @elseif ($results->isEmpty())
                <x-empty-state :title="$page > 1 ? __('No objects at this page') : __('No objects match those filters')">
                    @if ($page > 1)
                        {{ __('This page is beyond the current results. The catalogue may have changed since the link was saved.') }}
                        <a href="{{ $firstUrl }}" wire:navigate class="mt-3 block underline">{{ __('First page with these filters') }}</a>
                    @else
                        {{ __('Try widening the size range or clearing a filter.') }}
                    @endif
                </x-empty-state>
            @else
                <x-pagination :results="$results" :page="$page" :native-links="true" :previous-url="$previousUrl" :next-url="$nextUrl">
                    @foreach ($results->items as $object)
                        <x-object-card :object="$object" wire:key="obj-{{ $object->id }}" />
                    @endforeach
                </x-pagination>
                @if ($results->hasMore && $atPageLimit)
                    <p class="mt-4 text-sm" role="status">{{ __('The browsing page limit has been reached. Narrow the filters to explore more of this catalogue.') }}</p>
                @endif
            @endif
        </div>
    </div>
</div>

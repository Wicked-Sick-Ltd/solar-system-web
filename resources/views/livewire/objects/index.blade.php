<div>
    <x-page-header :title="__('All objects')"
                   :eyebrow="__('Catalogue')"
                   :lead="__('Every body in the catalogue, filterable by type, parent, size and near-Earth status. Filters live in the URL, so any view you reach is a shareable link.')" />

    <div class="grid gap-8 lg:grid-cols-[16rem_1fr]">
        <aside class="lg:sticky lg:top-20 lg:self-start" aria-label="{{ __('Filters') }}">
            <div class="surface space-y-5 p-5">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold" style="color: var(--text);">{{ __('Filter') }}</h2>
                    @if ($hasFilters)
                        <button type="button" wire:click="clearFilters" class="text-xs underline" style="color: var(--link);">
                            {{ __('Clear all') }}
                        </button>
                    @endif
                </div>

                <div>
                    <label for="f-type" class="mb-1.5 block text-xs font-medium uppercase tracking-wide" style="color: var(--muted);">{{ __('Type') }}</label>
                    <x-form-select id="f-type" wire:model.live="type" :options="$typeOptions" />
                </div>

                <div>
                    <label for="f-parent" class="mb-1.5 block text-xs font-medium uppercase tracking-wide" style="color: var(--muted);">{{ __('Parent body') }}</label>
                    <x-form-select id="f-parent" wire:model.live="parent" :options="$parentOptions" />
                </div>

                <div>
                    <label for="f-size" class="mb-1.5 block text-xs font-medium uppercase tracking-wide" style="color: var(--muted);">{{ __('Size') }}</label>
                    <x-form-select id="f-size" wire:model.live="size" :options="$sizeOptions" />
                </div>

                <div class="space-y-2.5 border-t pt-4" style="border-color: var(--border);">
                    <label class="flex items-center gap-2.5 text-sm" style="color: var(--text);">
                        <input type="checkbox" wire:model.live="neo" class="rounded" style="accent-color: var(--accent);">
                        {{ __('Near-Earth objects only') }}
                    </label>
                    <label class="flex items-center gap-2.5 text-sm" style="color: var(--text);">
                        <input type="checkbox" wire:model.live="named" class="rounded" style="accent-color: var(--accent);">
                        {{ __('Named objects only') }}
                    </label>
                </div>
            </div>
        </aside>

        <div>
            @if ($apiDown)
                <x-api-down :section="__('The object catalogue')" />
            @elseif ($results->isEmpty())
                <x-empty-state :title="__('No objects match those filters')">
                    {{ __('Try widening the size range or clearing a filter.') }}
                </x-empty-state>
            @else
                <div class="mb-4 flex items-center justify-between text-sm" style="color: var(--muted);">
                    <p wire:loading.remove>
                        {{ __('Showing :from–:to', ['from' => $results->from(), 'to' => $results->to()]) }}
                    </p>
                    <p wire:loading style="color: var(--accent);">{{ __('Loading…') }}</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" wire:loading.class="opacity-50">
                    @foreach ($results->items as $object)
                        <x-object-card :object="$object" wire:key="obj-{{ $object->id }}" />
                    @endforeach
                </div>

                <nav class="mt-8 flex items-center justify-between" aria-label="{{ __('Page navigation') }}">
                    @if ($results->hasPrevious())
                        <button type="button" wire:click="$set('page', {{ $page - 1 }})"
                                class="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium"
                                style="border-color: var(--border); color: var(--text);">
                            ← {{ __('Previous') }}
                        </button>
                    @else
                        <span></span>
                    @endif

                    <span class="text-sm tabular-nums" style="color: var(--muted);">{{ __('Page :n', ['n' => $page]) }}</span>

                    @if ($results->hasMore)
                        <button type="button" wire:click="$set('page', {{ $page + 1 }})"
                                class="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium"
                                style="border-color: var(--border); color: var(--text);">
                            {{ __('Next') }} →
                        </button>
                    @else
                        <span></span>
                    @endif
                </nav>
            @endif
        </div>
    </div>
</div>

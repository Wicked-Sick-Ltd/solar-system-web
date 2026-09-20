@props(['results', 'page'])

<div class="mb-4 flex items-center justify-between text-sm" style="color: var(--muted);">
    <p wire:loading.remove>
        {{ __('Showing :from–:to', ['from' => $results->from(), 'to' => $results->to()]) }}
    </p>
    <p wire:loading style="color: var(--accent);">{{ __('Loading…') }}</p>
</div>

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" wire:loading.class="opacity-50">
    {{ $slot }}
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

@props(['results', 'page', 'nativeLinks' => false, 'previousUrl' => null, 'nextUrl' => null])

<div class="mb-4 flex items-center justify-between text-sm" style="color: var(--muted);">
    <p wire:loading.remove role="status" aria-live="polite" aria-atomic="true">
        {{ __('Showing :from–:to', ['from' => $results->from(), 'to' => $results->to()]) }}
    </p>
    <p wire:loading style="color: var(--accent);">{{ __('Loading…') }}</p>
</div>

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" wire:loading.class="opacity-50">
    {{ $slot }}
</div>

<nav class="mt-8 flex flex-wrap items-center justify-between gap-4" aria-label="{{ __('Page navigation') }}">
    @if ($results->hasPrevious())
        @if ($nativeLinks && $previousUrl)
            <a href="{{ $previousUrl }}" rel="prev" wire:navigate
               class="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium"
               style="border-color: var(--border); color: var(--text);">← {{ __('Previous') }}</a>
        @elseif (! $nativeLinks)
            <button type="button" wire:click="$set('page', {{ $page - 1 }})" wire:loading.attr="disabled"
                class="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium"
                style="border-color: var(--border); color: var(--text);">
                ← {{ __('Previous') }}
            </button>
        @endif
    @else
        <span></span>
    @endif

    <span class="text-sm tabular-nums" style="color: var(--muted);">{{ __('Page :n', ['n' => $page]) }}</span>

    @if ($results->hasMore && (! $nativeLinks || $nextUrl))
        @if ($nativeLinks)
            <a href="{{ $nextUrl }}" rel="next" wire:navigate
               class="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium"
               style="border-color: var(--border); color: var(--text);">{{ __('Next') }} →</a>
        @else
            <button type="button" wire:click="$set('page', {{ $page + 1 }})" wire:loading.attr="disabled"
                class="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium"
                style="border-color: var(--border); color: var(--text);">
                {{ __('Next') }} →
            </button>
        @endif
    @else
        <span></span>
    @endif
</nav>

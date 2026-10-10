@props(['code', 'heading', 'showSearch' => false])

{{-- A calm, on-brand error screen. Used by every resources/views/errors/* view
     so a failure looks intentional and never shows a stack trace. --}}
<div class="mx-auto flex min-h-[55vh] max-w-xl flex-col items-center justify-center py-16 text-center">
    <p class="wordmark text-7xl font-medium tabular-nums sm:text-8xl" style="color: var(--accent);">{{ $code }}</p>
    <h1 class="mt-4 text-2xl font-medium sm:text-3xl">{{ $heading }}</h1>

    <div class="mt-3 text-base leading-relaxed" style="color: var(--muted);">
        {{ $slot }}
    </div>

    @if ($showSearch)
        <form action="{{ route('search') }}" method="GET" role="search" class="mt-6 w-full max-w-sm">
            <label for="err-search" class="sr-only">{{ __('Search the catalogue') }}</label>
            <input id="err-search" type="search" name="q" autocomplete="off"
                   placeholder="{{ __('Search the catalogue…') }}"
                   class="w-full rounded-lg border px-4 py-2.5 text-sm focus:outline-none"
                   style="border-color: var(--border); background-color: var(--bg-elevated); color: var(--text);">
        </form>
    @endif

    <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
        <a href="{{ route('home') }}"
           class="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold"
           style="background-color: var(--accent-fill); color: var(--on-accent);">{{ __('Back to home') }}</a>
        <a href="{{ route('objects.index') }}"
           class="inline-flex items-center gap-2 rounded-lg border px-4 py-2.5 text-sm font-medium"
           style="border-color: var(--border); color: var(--text);">{{ __('Browse the catalogue') }}</a>
    </div>
</div>

@props(['url', 'title', 'text' => null, 'label' => null, 'showLink' => false])

@php
    $text ??= $title;
    $label ??= __('Share');
    $linkedIn = 'https://www.linkedin.com/sharing/share-offsite/?'.http_build_query(['url' => $url], '', '&', PHP_QUERY_RFC3986);
    $x = 'https://x.com/intent/tweet?'.http_build_query(['text' => $text, 'url' => $url], '', '&', PHP_QUERY_RFC3986);
    $fieldId = 'share-link-'.substr(sha1($url.$label), 0, 8);
    $button = 'inline-flex min-h-11 items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium';
@endphp

{{-- Copy and native share need JavaScript, so those buttons stay x-cloak'd without it;
     the LinkedIn and X links are plain links and always work. --}}
<div {{ $attributes->merge(['class' => 'flex flex-col gap-3']) }}
     role="group" aria-label="{{ $label }}"
     x-data="{
        canShare: false,
        showField: @js((bool) $showLink),
        message: '',
        init() { this.canShare = typeof navigator.share === 'function'; },
        async copy() {
            try {
                await navigator.clipboard.writeText(@js($url));
                this.message = @js(__('Link copied to clipboard.'));
            } catch (e) {
                this.showField = true;
                this.$nextTick(() => { this.$refs.link.focus(); this.$refs.link.select(); });
                this.message = @js(__('Copying is blocked here — the link is selected, so press Ctrl+C or ⌘C.'));
            }
        },
        async share() {
            try {
                await navigator.share({ title: @js($title), text: @js($text), url: @js($url) });
            } catch (e) {}
        },
     }">
    <div class="flex flex-wrap items-center gap-2">
        <button type="button" x-cloak x-show="canShare" @click="share()" class="{{ $button }}"
                style="background-color: var(--accent-fill); border-color: var(--accent-fill); color: var(--on-accent);">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                <path d="M10 13V3m0 0L6.5 6.5M10 3l3.5 3.5M5 10H4a1 1 0 0 0-1 1v5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-5a1 1 0 0 0-1-1h-1" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            {{ __('Share…') }}
        </button>
        <button type="button" x-cloak x-show="true" @click="copy()" class="{{ $button }}"
                style="border-color: var(--border); color: var(--text);">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                <path d="M8.5 11.5a3 3 0 0 0 4.24 0l2.5-2.5a3 3 0 0 0-4.24-4.24l-.75.75M11.5 8.5a3 3 0 0 0-4.24 0l-2.5 2.5a3 3 0 0 0 4.24 4.24l.75-.75" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
            {{ __('Copy link') }}
        </button>
        <a href="{{ $linkedIn }}" target="_blank" rel="noopener noreferrer" class="{{ $button }}"
           style="border-color: var(--border); color: var(--text);">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M4.98 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5ZM3 9.75h4v11H3v-11Zm6.5 0h3.83v1.5h.05c.53-1 1.84-2.05 3.79-2.05 4.05 0 4.8 2.67 4.8 6.13v5.42h-4v-4.8c0-1.15-.02-2.62-1.6-2.62-1.6 0-1.84 1.25-1.84 2.54v4.88h-4v-11Z"/>
            </svg>
            {{ __('LinkedIn') }}<span class="sr-only"> {{ __('(opens in a new tab)') }}</span>
        </a>
        <a href="{{ $x }}" target="_blank" rel="noopener noreferrer" class="{{ $button }}"
           style="border-color: var(--border); color: var(--text);">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M17.75 3h3.07l-6.7 7.66L22 21h-6.17l-4.83-6.32L5.47 21H2.4l7.17-8.2L2 3h6.33l4.37 5.78L17.75 3Zm-1.08 16.17h1.7L7.4 4.74H5.57l11.1 14.43Z"/>
            </svg>
            {{ __('Post on X') }}<span class="sr-only"> {{ __('(opens in a new tab)') }}</span>
        </a>
    </div>

    <div @if (! $showLink) hidden @endif x-bind:hidden="!showField" class="flex max-w-xl flex-col gap-1">
        <label for="{{ $fieldId }}" class="text-xs font-semibold uppercase tracking-wide" style="color: var(--muted);">{{ __('Permalink') }}</label>
        <input id="{{ $fieldId }}" x-ref="link" type="url" readonly value="{{ $url }}" @focus="$el.select()"
               class="min-h-11 w-full rounded-lg border px-3 py-2 text-sm tabular-nums"
               style="background-color: var(--bg-elevated-2); border-color: var(--border); color: var(--text);">
    </div>

    <p role="status" aria-live="polite" class="min-h-5 text-sm" style="color: var(--muted);" x-text="message"></p>
</div>

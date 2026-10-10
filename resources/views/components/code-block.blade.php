@props(['label', 'id'])

<div class="mt-3" x-data="{ notice: '' }">
    <div class="mb-1 flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
        <p id="{{ $id }}-label" class="min-w-0 text-xs font-semibold" style="color: var(--muted);">{{ $label }}</p>
        <button type="button"
            class="min-h-11 shrink-0 rounded-lg border px-3 text-sm font-medium"
            style="border-color: var(--border); color: var(--text); background: var(--bg-elevated);"
            aria-label="{{ __('Copy :label', ['label' => $label]) }}"
            @click="
                const node = document.getElementById(@js($id));
                const text = node ? node.innerText : '';
                const show = (message) => {
                    notice = message;
                    setTimeout(() => { if (notice === message) notice = ''; }, 2000);
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(() => show(@js(__('Copied')))).catch(() => show(@js(__('Select the text and copy it from the menu'))));
                } else {
                    show(@js(__('Select the text and copy it from the menu')));
                }
            ">{{ __('Copy') }}</button>
    </div>
    <pre id="{{ $id }}" tabindex="0" aria-labelledby="{{ $id }}-label" class="overflow-x-auto rounded-lg p-4 text-sm" style="background: var(--bg); color: var(--text);"><code class="block whitespace-pre font-mono">{!! trim($slot) !!}</code></pre>
    <p class="sr-only" aria-live="polite" x-text="notice"></p>
</div>

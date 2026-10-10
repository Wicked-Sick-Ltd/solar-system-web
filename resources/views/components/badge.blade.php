@props(['tone' => 'neutral'])

@php
    $styles = match ($tone) {
        'amber' => 'background-color: var(--accent-soft-bg); color: var(--accent-soft-text); border-color: var(--accent-soft-border);',
        'blue' => 'background-color: color-mix(in srgb, var(--color-blue) 16%, transparent); color: var(--color-blue-soft); border-color: color-mix(in srgb, var(--color-blue) 35%, transparent);',
        'danger' => 'background-color: color-mix(in srgb, #ff6b6b 16%, transparent); color: #ffb4b4; border-color: color-mix(in srgb, #ff6b6b 35%, transparent);',
        default => 'background-color: var(--bg-elevated-2); color: var(--muted); border-color: var(--border);',
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-medium']) }}
      style="{{ $styles }}">
    {{ $slot }}
</span>

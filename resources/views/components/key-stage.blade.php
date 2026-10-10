@props(['stages' => []])

@php
    $codes = array_values(is_array($stages) ? $stages : [(string) $stages]);
    $parts = explode(' · ', \App\Support\KeyStage::span($codes));
@endphp

<span {{ $attributes->merge(['class' => 'inline min-w-0 max-w-full whitespace-normal']) }}>
    <span aria-hidden="true">
        @foreach ($parts as $part)
            <span class="whitespace-nowrap">{{ $part }}</span>@if (! $loop->last) · @endif
        @endforeach
    </span>
    <span class="sr-only">{{ \App\Support\KeyStage::accessible($codes) }}</span>
</span>

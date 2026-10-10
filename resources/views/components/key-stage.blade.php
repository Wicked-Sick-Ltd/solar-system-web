@props(['stages' => []])

@php
    $codes = array_values(is_array($stages) ? $stages : [(string) $stages]);
@endphp

<span {{ $attributes->merge(['class' => 'inline min-w-0 max-w-full whitespace-normal']) }}>
    <span aria-hidden="true">{{ \App\Support\KeyStage::span($codes) }}</span>
    <span class="sr-only">{{ \App\Support\KeyStage::accessible($codes) }}</span>
</span>

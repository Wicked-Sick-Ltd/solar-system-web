<div>
    <x-page-header :title="__('The planets')"
                   :eyebrow="__('Eight worlds')"
                   :lead="__('From scorched, sunward Mercury to deep-blue Neptune on the cold edge of the system — the eight planets in order of their distance from the Sun.')" />

    @if ($apiDown)
        <x-api-down :section="__('The planets')" />
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($planets as $planet)
                <x-object-card :object="$planet" wire:key="planet-{{ $planet->id }}" />
            @endforeach
        </div>
    @endif
</div>

<div>
    <x-page-header :title="$copy['title']" :eyebrow="$copy['eyebrow']" :lead="$copy['lead']" />

    @if ($apiDown)
        <x-api-down :section="$copy['title']" />
    @elseif ($results->isEmpty())
        <x-empty-state :title="__('Nothing here yet')" />
    @else
        @if ($paginated)
            <x-pagination :results="$results" :page="$page">
                @foreach ($results->items as $object)
                    <x-object-card :object="$object" wire:key="cat-{{ $object->id }}" />
                @endforeach
            </x-pagination>
        @else
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($results->items as $object)
                    <x-object-card :object="$object" wire:key="cat-{{ $object->id }}" />
                @endforeach
            </div>
        @endif
    @endif
</div>

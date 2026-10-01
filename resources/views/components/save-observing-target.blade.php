@props(['catalogue', 'targetId', 'targetLabel'])
<a class="inline-flex min-h-11 items-center underline" href="{{ route('observing.journal', ['catalogue' => $catalogue, 'target' => $targetId, 'label' => $targetLabel]) }}">{{ __('Add to an observing list or journal') }} →</a>

@if (\App\Services\Observing\NightTargets::canPlan($target))
    <a href="{{ route('observe.night', ['targets' => [$target->id]]) }}" class="inline-block min-h-11 content-center underline" aria-label="{{ __('Prepare a night plan for :target', ['target' => $target->name]) }}">{{ __('Prepare a night plan') }}</a>
@endif

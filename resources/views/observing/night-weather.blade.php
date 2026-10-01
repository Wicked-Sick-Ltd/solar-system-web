<x-layouts.app>
    <div class="mx-auto max-w-5xl space-y-6">
        <x-page-header :title="__('Forecast for your selected interval')" :eyebrow="__('Optional weather')" />
        @if($problem)<p role="alert" class="surface p-5">{{ __($problem) }}</p>@endif
        @if($forecast)@include('observing.partials.night-weather', ['forecast' => $forecast])@endif
        <p><a class="inline-flex min-h-11 items-center underline" href="{{ route('observe.night') }}">{{ __('Return to the night planner') }}</a></p>
    </div>
</x-layouts.app>

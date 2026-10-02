<x-layouts.app>
    <x-page-header :title="__('Shortlist request unavailable')" />
    <p role="alert">{{ $problem }}</p>
    <p class="mt-4"><a class="underline" href="{{ route('observe.shortlist') }}">{{ __('Return to the shortlist form') }}</a></p>
</x-layouts.app>

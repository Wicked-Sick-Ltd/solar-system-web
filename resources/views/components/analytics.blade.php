@php $gaId = config('site.analytics.ga_measurement_id'); @endphp
@if ($gaId)
    {{-- The id only. gtag itself is injected by the cookie banner after consent. --}}
    <meta name="ga-measurement-id" content="{{ $gaId }}">
@endif

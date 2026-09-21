@php $gaId = config('site.analytics.ga_measurement_id'); @endphp
@if ($gaId)
    {{-- The id and the address gtag may report. gtag itself is injected by the cookie banner after consent. --}}
    <meta name="ga-measurement-id" content="{{ $gaId }}">
    <meta name="ga-page-location" content="{{ \App\Support\Analytics::pageLocation() }}">
@endif

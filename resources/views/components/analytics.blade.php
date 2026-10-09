@php
    $gaId = \App\Support\Analytics::measurementId();
    $script = $gaId ? \App\Support\Analytics::clientScript() : '';
@endphp
@if ($gaId && $script !== '')
    {{-- First-party only. gtag.js is injected by analytics-consent.js after consent, so the initial document has no third-party script for CSP, Lighthouse or fixture budgets. --}}
    <meta name="ga-measurement-id" content="{{ $gaId }}">
    <meta name="ga-page-location" content="{{ \App\Support\Analytics::pageLocation() }}">
    <script>
{!! $script !!}
    </script>
@endif

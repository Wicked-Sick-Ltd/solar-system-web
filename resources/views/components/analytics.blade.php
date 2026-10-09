@php $gaId = config('site.analytics.ga_measurement_id'); @endphp
@if ($gaId)
    {{-- Measurement id and a redacted page_location for the first hit. gtag.js is
         injected by the cookie banner only after analytics consent. Consent Mode
         v2 defaults run here so analytics_storage stays denied until then. --}}
    <meta name="ga-measurement-id" content="{{ $gaId }}">
    <meta name="ga-page-location" content="{{ \App\Support\Analytics::pageLocation() }}">
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){ window.dataLayer.push(arguments); }
        window.gtag = gtag;
        gtag('consent', 'default', {
            ad_storage: 'denied',
            ad_user_data: 'denied',
            ad_personalization: 'denied',
            analytics_storage: 'denied',
            wait_for_update: 500
        });
    </script>
@endif

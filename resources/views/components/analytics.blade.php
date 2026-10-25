@php
    $gaId = \App\Support\Analytics::measurementId();
    $gtmId = \App\Support\Analytics::gtmContainerId();
    $script = ($gaId || $gtmId) ? \App\Support\Analytics::clientScript() : '';
@endphp
@if (($gaId || $gtmId) && $script !== '')
    {{-- Consent Mode v2 and the redacted page location run before either Google tag. gtag.js is in the document whenever a measurement id is set; analytics storage is granted unless localStorage pu_cookie_consent is essential. data-cfasync keeps Cloudflare Rocket Loader from deferring the tags. --}}
    @if ($gaId)
        <meta name="ga-measurement-id" content="{{ $gaId }}">
    @endif
    @if ($gtmId)
        <meta name="gtm-container-id" content="{{ $gtmId }}">
    @endif
    <meta name="ga-page-location" content="{{ \App\Support\Analytics::pageLocation() }}">
    <script data-cfasync="false">
@if ($gaId)
window.__puGtagScriptTag = true;
@endif
{!! $script !!}
    </script>
    @if ($gaId)
        <script data-cfasync="false" async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
    @endif
    @if ($gtmId)
        <!-- Google Tag Manager -->
        <script data-cfasync="false">(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.setAttribute('data-cfasync','false');j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','{{ $gtmId }}');</script>
        <!-- End Google Tag Manager -->
    @endif
@endif

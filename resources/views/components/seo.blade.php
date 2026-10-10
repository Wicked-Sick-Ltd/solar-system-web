@php
    /** @var \App\Support\Seo $seo */
    $seo = app(\App\Support\Seo::class);
    $image = $seo->getImage();
    $siteName = config('site.name');
@endphp
<title>{{ $seo->fullTitle() }}</title>
<meta name="description" content="{{ $seo->getDescription() }}">
<link rel="canonical" href="{{ $seo->getCanonical() }}">
@if ($seo->isNoindex())
    <meta name="robots" content="noindex, follow">
@else
    <meta name="robots" content="index, follow, max-image-preview:large">
@endif

{{-- Open Graph --}}
<meta property="og:type" content="{{ $seo->getType() }}">
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:title" content="{{ $seo->metaTitle() }}">
<meta property="og:description" content="{{ $seo->getDescription() }}">
<meta property="og:url" content="{{ $seo->getCanonical() }}">
<meta property="og:locale" content="en_GB">
@if ($image)
    <meta property="og:image" content="{{ $image }}">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:width" content="{{ \App\Support\ShareImage::WIDTH }}">
    <meta property="og:image:height" content="{{ \App\Support\ShareImage::HEIGHT }}">
    <meta property="og:image:alt" content="{{ $seo->getImageAlt() }}">
@endif

{{-- Twitter --}}
<meta name="twitter:card" content="{{ $image ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $seo->metaTitle() }}">
<meta name="twitter:description" content="{{ $seo->getDescription() }}">
@if ($image)
    <meta name="twitter:image" content="{{ $image }}">
    <meta name="twitter:image:alt" content="{{ $seo->getImageAlt() }}">
@endif

{{-- JSON-LD structured data --}}
@foreach ($seo->getJsonLd() as $schema)
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endforeach

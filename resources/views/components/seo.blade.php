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
@endif

{{-- Twitter --}}
<meta name="twitter:card" content="{{ $image ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $seo->metaTitle() }}">
<meta name="twitter:description" content="{{ $seo->getDescription() }}">
@if ($image)
    <meta name="twitter:image" content="{{ $image }}">
@endif

{{-- JSON-LD structured data --}}
@foreach ($seo->getJsonLd() as $schema)
    <script type="application/ld+json">@json($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
@endforeach

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0a0e1a" media="(prefers-color-scheme: dark)">
    <meta name="theme-color" content="#f7f5ef" media="(prefers-color-scheme: light)">

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="mask-icon" href="{{ asset('favicon.svg') }}" color="#e0b872">

    {{-- Set the theme before first paint to avoid a flash. Default: dark. --}}
    <script>
        (function () {
            try {
                var t = localStorage.getItem('theme') || 'dark';
                document.documentElement.setAttribute('data-theme', t);
            } catch (e) {}
        })();
    </script>

    <x-seo />
    <x-analytics />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen antialiased" style="background-color: var(--bg); color: var(--text);">
    <a href="#main"
       class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-3 focus:rounded-lg focus:px-4 focus:py-2 focus:font-medium"
       style="background-color: var(--accent); color: #07090f;">
        {{ __('Skip to content') }}
    </a>

    <x-site-header />

    <main id="main" class="mx-auto w-full max-w-6xl px-4 pb-24 pt-8 sm:px-6 lg:px-8">
        {{ $slot }}
    </main>

    <x-site-footer />
    <x-cookie-banner />

    @livewireScripts
</body>
</html>

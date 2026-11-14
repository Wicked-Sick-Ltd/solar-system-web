@php app(\App\Support\Seo::class)->title(__('Page not found'))->noindex()->publishCanonical(false); @endphp
<x-layouts.app>
    <x-error-page :code="404" :heading="__('Lost in space')" :show-search="true">
        {{ __('We couldn’t find that page or object. It may have been renamed, or never existed. Try a search, or head back to a known orbit.') }}
    </x-error-page>
</x-layouts.app>

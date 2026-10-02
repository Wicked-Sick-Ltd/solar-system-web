@props(['sections'])

<nav aria-label="{{ __('On this page') }}" class="surface mb-6 p-4 print:hidden">
    <p class="mb-2 text-sm font-semibold">{{ __('On this page') }}</p>
    <ul class="flex flex-wrap gap-x-4 gap-y-1">
        @foreach ($sections as $section)
            <li class="min-w-0 max-w-full">
                <a href="#{{ $section['id'] }}" class="inline-flex min-h-11 items-center break-words py-2 text-sm underline">{{ $section['label'] }}</a>
            </li>
        @endforeach
    </ul>
</nav>

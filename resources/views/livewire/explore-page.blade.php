<div>
    <x-page-header :title="__('Explore the universe')" :eyebrow="__('Follow your curiosity')"
        :lead="__('Start close to home, visit a world around another star, or look at the bigger picture. Every catalogue is free to browse.')" />
    <form action="{{ route('search') }}" method="GET" role="search" class="surface mb-8 p-5">
        <label for="explore-search" class="mb-2 block font-medium">{{ __('Find an object or planetary system') }}</label>
        <div class="flex flex-wrap gap-3">
            <input id="explore-search" name="q" type="search" maxlength="200" placeholder="{{ __('Saturn, Ceres, TRAPPIST-1…') }}"
                class="min-w-0 flex-1 rounded border px-3 py-2" style="background: var(--bg); border-color: var(--border)">
            <button class="rounded border px-4 py-2" style="border-color: var(--border)">{{ __('Search') }}</button>
        </div>
    </form>
    <div class="grid gap-5 md:grid-cols-3">
        <section class="surface p-6" aria-labelledby="explore-solar">
            <h2 id="explore-solar" class="text-2xl">{{ __('Our solar system') }}</h2>
            <p class="mt-3" style="color: var(--muted)">{{ __('Meet the planets and the smaller worlds orbiting our Sun.') }}</p>
            <ul class="mt-5 space-y-3">
                @foreach (['planets.index' => __('Planets'), 'dwarf-planets' => __('Dwarf planets'), 'asteroids' => __('Asteroids'), 'comets' => __('Comets'), 'tnos' => __('Beyond Neptune'), 'objects.index' => __('All solar-system objects')] as $route => $label)
                    <li><a class="underline" href="{{ route($route) }}">{{ $label }}</a></li>
                @endforeach
                <li><a class="underline" href="{{ route('objects.index', ['type' => 'moon']) }}">{{ __('Moons') }}</a></li>
            </ul>
        </section>
        <section class="surface p-6" aria-labelledby="explore-exo">
            <h2 id="explore-exo" class="text-2xl">{{ __('Other worlds') }}</h2>
            <p class="mt-3" style="color: var(--muted)">{{ __('Discover planets beyond our solar system. Follow each measurement back to its source.') }}</p>
            <ul class="mt-5 space-y-3">
                <li><a class="underline" href="{{ route('exoplanets.index') }}">{{ __('Browse exoplanets') }}</a></li>
                <li><a class="underline" href="{{ route('exoplanets.index', ['q' => 'TRAPPIST-1']) }}">{{ __('Explore the TRAPPIST-1 system') }}</a></li>
                <li><a class="underline" href="{{ route('learn') }}#finding-planets">{{ __('How do we find these planets?') }}</a></li>
            </ul>
        </section>
        <section class="surface p-6" aria-labelledby="explore-maps">
            <h2 id="explore-maps" class="text-2xl">{{ __('See the connections') }}</h2>
            <p class="mt-3" style="color: var(--muted)">{{ __('Explore positions in space. Maps include explanations of their scale, coverage and limitations.') }}</p>
            <ul class="mt-5 space-y-3">
                <li><a class="underline" href="{{ route('orrery') }}">{{ __('Solar-system orrery') }}</a></li>
                <li><a class="underline" href="{{ route('galaxy') }}">{{ __('Galaxy explorer') }}</a></li>
                <li><a class="underline" href="{{ route('random') }}">{{ __('Surprise me with an object') }}</a></li>
            </ul>
        </section>
    </div>
</div>

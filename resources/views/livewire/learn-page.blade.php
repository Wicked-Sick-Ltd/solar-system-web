<div class="mx-auto max-w-3xl">
    <x-page-header :title="__('A little curiosity goes a long way')" :eyebrow="__('Learn with real observations')"
        :lead="__('Try these short explorations on your own, with family or in a classroom. Start with the question, then open the extra detail when you are ready.')" />
    <div class="space-y-8">
        <section id="distances" class="surface scroll-mt-24 p-6" aria-labelledby="distance-heading">
            <h2 id="distance-heading" class="text-2xl">{{ __('How far away is a star?') }}</h2>
            <p class="mt-3 leading-relaxed">{{ __('A light-year measures distance: how far light travels through empty space in a year. A star ten light-years away is so distant that the light we see has travelled for about ten years.') }}</p>
            <p class="mt-3 leading-relaxed"><strong>{{ __('Try it:') }}</strong> {{ __('Choose two systems on the galaxy map. Read their distances. Which light has travelled longer to reach us? Write down the names and distances so someone else can follow your comparison.') }}</p>
            <a class="mt-4 inline-block underline" href="{{ route('galaxy') }}">{{ __('Compare systems on the map') }} →</a>
            <details class="mt-4 border-t pt-4" style="border-color: var(--border)">
                <summary class="cursor-pointer font-medium">{{ __('Go deeper: reading the map') }}</summary>
                <p class="mt-3 leading-relaxed">{{ __('The map positions represent catalogued host stars. Their markers are enlarged so you can select them. Missing points can mean missing measurements, and areas with many discoveries can reflect where telescopes looked. The outline of the Milky Way is schematic.') }}</p>
            </details>
            <p class="mt-4 text-sm"><a class="underline" href="https://spaceplace.nasa.gov/light-year/en/" rel="noopener" target="_blank">{{ __('Read more: NASA Space Place — light-years') }}</a></p>
        </section>
        <section id="finding-planets" class="surface scroll-mt-24 p-6" aria-labelledby="finding-heading">
            <h2 id="finding-heading" class="text-2xl">{{ __('How do we find a planet around another star?') }}</h2>
            <p class="mt-3 leading-relaxed">{{ __('A planet passing in front of its star can block a little starlight. Repeated dips are one clue astronomers investigate. Another clue is the star moving towards and away from us as an orbiting planet pulls on it.') }}</p>
            <p class="mt-3 leading-relaxed"><strong>{{ __('Try it:') }}</strong> {{ __('Browse planets discovered by the transit method, then switch to radial velocity. Open a planet from each group and compare which measurements are available. An unknown value is a gap in the catalogue, not a zero.') }}</p>
            <a class="mt-4 inline-block underline" href="{{ route('exoplanets.index', ['method' => 'Transit']) }}">{{ __('Explore transit discoveries') }} →</a>
            <details class="mt-4 border-t pt-4" style="border-color: var(--border)">
                <summary class="cursor-pointer font-medium">{{ __('Go deeper: different ways of looking') }}</summary>
                <p class="mt-3 leading-relaxed">{{ __('Transit observations need an orbit aligned to cross the star from our viewpoint. Radial velocity measures shifts in the wavelengths of starlight. Each method favours some kinds of planet, so discovery counts are not a fair sample of every planet in space.') }}</p>
            </details>
            <p class="mt-4 text-sm"><a class="underline" href="https://science.nasa.gov/exoplanets/how-we-find-and-characterize/" rel="noopener" target="_blank">{{ __('Read more: NASA — finding and characterizing planets') }}</a></p>
        </section>
        <section id="measurements" class="surface scroll-mt-24 p-6" aria-labelledby="measurement-heading">
            <h2 id="measurement-heading" class="text-2xl">{{ __('What does a measurement really tell us?') }}</h2>
            <p class="mt-3 leading-relaxed">{{ __('Read the value together with its unit, uncertainty and source. A plus and minus range describes reported uncertainty. A less-than or greater-than sign marks a limit rather than an exact measurement.') }}</p>
            <p class="mt-3 leading-relaxed"><strong>{{ __('Try it:') }}</strong> {{ __('Open an exoplanet page. Record one measurement, its unit, any uncertainty or limit, the snapshot retrieval date and the source link. Explain which parts you know and which are still uncertain.') }}</p>
            <a class="mt-4 inline-block underline" href="{{ route('exoplanets.index') }}">{{ __('Investigate an exoplanet') }} →</a>
            <details class="mt-4 border-t pt-4" style="border-color: var(--border)">
                <summary class="cursor-pointer font-medium">{{ __('Go deeper: follow the evidence') }}</summary>
                <p class="mt-3 leading-relaxed">{{ __('Our exoplanet pages use the NASA Planetary Systems Composite Parameters catalogue. Its values can come from different studies and need not form a single consistent model. Follow the archive references before combining values in a calculation. A retrieval date says when data was collected from the archive, not when the observation was made.') }}</p>
                <a class="mt-3 inline-block underline" href="{{ route('api') }}">{{ __('Use the data yourself') }} →</a>
            </details>
            <p class="mt-4 text-sm"><a class="underline" href="https://exoplanetarchive.ipac.caltech.edu/docs/PSCompPars.html" rel="noopener" target="_blank">{{ __('Reference: NASA Exoplanet Archive composite parameters') }}</a></p>
        </section>
    </div>
</div>

<div class="mx-auto max-w-5xl">
    <x-page-header :title="__('Astronomy for higher education')" :eyebrow="__('University teaching and independent study')"
        :lead="__('Work with real catalogue data, question its limits and make a scientific argument someone else can check. These free undergraduate activities suit tutorials, practical classes and independent study.')" />

    <x-section-navigation :sections="[
        ['id' => 'activities', 'label' => __('Choose an activity')],
        ['id' => 'teaching', 'label' => __('Teaching with the data')],
        ['id' => 'next-steps', 'label' => __('Further resources')],
    ]" />

    <section id="activities" class="scroll-mt-24" aria-labelledby="activities-heading">
        <h2 id="activities-heading" class="mb-5 text-2xl">{{ __('Choose an activity') }}</h2>
        <div class="space-y-6">
            @foreach ($activities as $id => $activity)
                <article id="{{ $id }}" class="surface scroll-mt-24 p-5 sm:p-7" aria-labelledby="{{ $id }}-heading">
                    <div class="flex flex-wrap items-center gap-3 text-sm">
                        <x-badge tone="amber">{{ $activity['level'] }}</x-badge>
                        <span style="color: var(--muted)">{{ $activity['duration'] }}</span>
                    </div>
                    <h3 id="{{ $id }}-heading" class="mt-4 text-2xl">{{ $activity['title'] }}</h3>
                    <p class="mt-3 leading-relaxed">{{ $activity['summary'] }}</p>
                    <p class="mt-3 text-sm" style="color: var(--muted)"><strong>{{ __('Before you start:') }}</strong> {{ $activity['prerequisites'] }}</p>
                    <ul class="mt-4 list-disc space-y-1 pl-5 text-sm">
                        @foreach ($activity['outcomes'] as $outcome)
                            <li>{{ $outcome }}</li>
                        @endforeach
                    </ul>
                    <div class="mt-5 flex flex-wrap gap-x-6 gap-y-3">
                        <a href="{{ route('higher-education.handout', ['activity' => $id]) }}" class="inline-flex min-h-11 items-center rounded-lg px-4 py-2 text-sm font-semibold" style="background-color: var(--accent); color: #07090f;">{{ __('Open printable handout') }} <span class="sr-only">: {{ $activity['title'] }}</span></a>
                        <a href="{{ route($activity['start_route']) }}" class="inline-flex min-h-11 items-center text-sm underline">{{ $activity['start_label'] }}</a>
                    </div>
                    <details class="mt-5 border-t pt-4" style="border-color: var(--border)">
                        <summary class="cursor-pointer font-medium">{{ __('Assignment and assessment') }}</summary>
                        <p class="mt-3 text-sm leading-relaxed">{{ $activity['deliverable'] }}</p>
                        <ul class="mt-3 list-disc space-y-1 pl-5 text-sm">
                            @foreach ($activity['assessment'] as $criterion)
                                <li>{{ $criterion }}</li>
                            @endforeach
                        </ul>
                    </details>
                </article>
            @endforeach
        </div>
    </section>

    <section id="teaching" class="surface mt-8 scroll-mt-24 p-5 sm:p-7" aria-labelledby="teaching-heading">
        <h2 id="teaching-heading" class="text-2xl">{{ __('Teaching with the data') }}</h2>
        <div class="mt-4 space-y-3 leading-relaxed">
            <p>{{ __('Each handout includes a student worksheet, teaching notes, assessment criteria and primary-source references. Open it and use your browser’s Print command to print or Save as PDF. The print layout supports A4; teaching notes begin on a separate page.') }}</p>
            <p>{{ __('Students do not need an account or a telescope. A browser and a spreadsheet are enough to begin; the API offers a route into Python or other research tools. The activities are suggested teaching materials, not an accredited course.') }}</p>
            <p>{{ __('Catalogue values and coverage change. Preserve the selected rows, date, filters and source references for each class. Missing values are not zero, limits are not exact measurements, and a discovery catalogue is not a complete census.') }}</p>
            <p>{{ __('Use the original studies for scientific claims and check each source’s reuse terms. You can adapt these activity instructions under the site repository’s MIT licence; source data and third-party material retain their own terms.') }}</p>
        </div>
    </section>

    <section id="next-steps" class="mt-8 scroll-mt-24" aria-labelledby="next-steps-heading">
        <h2 id="next-steps-heading" class="text-2xl">{{ __('Further resources') }}</h2>
        <ul class="mt-4 flex flex-wrap gap-x-6 gap-y-3">
            <li><a class="inline-flex min-h-11 items-center underline" href="{{ route('api') }}">{{ __('API and data access') }}</a></li>
            <li><a class="inline-flex min-h-11 items-center underline" href="{{ route('galaxy') }}">{{ __('Explore the galaxy map') }}</a></li>
            <li><a class="inline-flex min-h-11 items-center underline" href="{{ route('educators') }}">{{ __('Primary and secondary resources') }}</a></li>
        </ul>
    </section>
</div>

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ app(\App\Support\Seo::class)->fullTitle() }}</title>
    <meta name="description" content="{{ $activity['summary'] }}">
    <link rel="canonical" href="{{ app(\App\Support\Seo::class)->getCanonical() }}">
    <style>
        :root { color-scheme: light; font-family: system-ui, sans-serif; color: #172336; background: #edf1f5; }
        * { box-sizing: border-box; }
        body { margin: 0; line-height: 1.5; }
        a { color: #174f7a; text-underline-offset: .15em; overflow-wrap: anywhere; }
        a:focus-visible { outline: 3px solid #ad5300; outline-offset: 4px; }
        .controls, main { max-width: 850px; margin: 0 auto; }
        .controls { padding: 1.3rem 1.5rem; }
        .controls p { margin: .6rem 0 0; font-size: .9rem; }
        .sheet { background: white; padding: 2.5rem; margin-bottom: 1.5rem; }
        .brand { color: #3b596d; font-size: .78rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; }
        h1 { font-size: 2rem; line-height: 1.15; margin: .8rem 0; }
        h2 { font-size: 1.2rem; line-height: 1.3; margin: 1.3rem 0 .5rem; }
        h3 { font-size: 1rem; margin: 1rem 0 .4rem; }
        p { margin: .5rem 0; }
        .metadata { font-size: .9rem; color: #40556b; }
        .question { border-left: 3px solid #a9691c; padding-left: .9rem; margin: 1rem 0; font-weight: 600; }
        ol, ul { padding-left: 1.4rem; margin: .5rem 0; }
        li { padding-left: .2rem; margin-bottom: .65rem; }
        .record p { min-height: 3rem; border-bottom: 1px solid #b4bdc7; padding-bottom: 1.5rem; font-size: .85rem; }
        .references { font-size: .85rem; }
        .references span { display: block; color: #40556b; overflow-wrap: anywhere; }
        footer { border-top: 1px solid #b4bdc7; margin-top: 1.4rem; padding-top: .6rem; font-size: .75rem; color: #40556b; }
        @media (max-width: 600px) { .sheet { padding: 1.4rem; } h1 { font-size: 1.7rem; } }
        @page { size: A4; margin: 14mm; }
        @media print {
            :root { background: white; color: #111; font-size: 9pt; }
            .controls { display: none; }
            main { max-width: none; }
            .sheet { padding: 0; margin: 0; }
            .teaching { break-before: page; }
            h1 { font-size: 21pt; } h2 { font-size: 12pt; margin-top: .9rem; }
            h1, h2, h3 { break-after: avoid; }
            li, .record p, .references li { break-inside: avoid; }
            .record p { min-height: 2.5rem; }
            a { color: inherit; }
        }
    </style>
</head>
<body>
    <nav class="controls" aria-label="{{ __('Handout navigation') }}">
        <a href="{{ route('higher-education') }}#{{ $activityId }}">← {{ __('All university activities') }}</a>
        <p>{{ __('Use your browser’s Print command (Ctrl+P or ⌘P) to print or Save as PDF. Select A4 paper. Teaching notes start on a separate page; use the print preview to choose student pages only.') }}</p>
    </nav>
    <main>
        <section class="sheet" aria-labelledby="worksheet-title">
            <p class="brand">{{ config('site.name') }} · {{ __('Student worksheet') }}</p>
            <h1 id="worksheet-title">{{ $activity['title'] }}</h1>
            <p class="metadata">{{ $activity['level'] }} · {{ $activity['duration'] }}</p>
            <p class="metadata"><strong>{{ __('Preparation:') }}</strong> {{ $activity['prerequisites'] }}</p>
            <p class="question">{{ $activity['question'] }}</p>
            <h2>{{ __('Data and starting point') }}</h2>
            <p>{{ $activity['data'] }}</p>
            <p><a href="{{ route($activity['start_route']) }}">{{ $activity['start_label'] }}</a> · <span class="metadata">{{ route($activity['start_route']) }}</span></p>
            <h2>{{ __('Investigation') }}</h2>
            <ol>
                @foreach ($activity['steps'] as $step)
                    <li>{{ $step }}</li>
                @endforeach
            </ol>
            <h2>{{ __('Submit') }}</h2>
            <p>{{ $activity['deliverable'] }}</p>
            <div class="record">
                <h2>{{ $activity['record_heading'] }}</h2>
                @foreach ($activity['record_prompts'] as $prompt)
                    <p>{{ $prompt }}</p>
                @endforeach
            </div>
            <footer>{{ __('Keep source values, units and dates together. Unknown does not mean zero.') }}</footer>
        </section>
        <section class="sheet teaching" aria-labelledby="teaching-title">
            <p class="brand">{{ config('site.name') }} · {{ __('Teaching notes') }}</p>
            <h2 id="teaching-title">{{ $activity['title'] }}</h2>
            <h3>{{ __('Learning outcomes') }}</h3>
            <ul>@foreach ($activity['outcomes'] as $outcome)<li>{{ $outcome }}</li>@endforeach</ul>
            <h3>{{ __('Discussion and interpretation') }}</h3>
            @foreach ($activity['teaching_notes'] as $note)<p>{{ $note }}</p>@endforeach
            <h3>{{ __('Assessment criteria') }}</h3>
            <ul>@foreach ($activity['assessment'] as $criterion)<li>{{ $criterion }}</li>@endforeach</ul>
            <h3>{{ __('Primary sources and further reading') }}</h3>
            <ul class="references">
                @foreach ($activity['sources'] as $source)
                    <li><a href="{{ $source['url'] }}">{{ $source['title'] }}</a><span>{{ $source['url'] }}</span></li>
                @endforeach
            </ul>
            <p class="metadata">{{ __('Before class, check the selected data and source links. Keep a dated copy of the inputs so the activity remains repeatable when the catalogue changes.') }}</p>
            <footer>{{ __('Activity instructions may be adapted under the repository’s MIT licence. Source data and third-party material retain their own terms.') }}</footer>
        </section>
    </main>
</body>
</html>

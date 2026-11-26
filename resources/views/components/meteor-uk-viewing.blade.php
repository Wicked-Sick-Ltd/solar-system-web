@props(['viewing', 'faq' => [], 'copy' => []])
@php
    $time = static fn (\DateTimeInterface $t): string => $t->format('H:i');
    $night = __('night of :dates', ['dates' => \App\Support\Sky\UkMeteorViewing::nightLabel($viewing['night_start_local'], $viewing['night_end_local'])]);
@endphp
<section class="surface mb-8 p-5 sm:p-7" aria-labelledby="uk-viewing" data-testid="meteor-uk-viewing">
    <h2 id="uk-viewing" class="mb-2 font-serif text-2xl">{{ __('When and where to see it from the UK') }}</h2>
    <p class="mb-4 text-sm" style="color: var(--muted);">{{ __('Peak :night', ['night' => $night]) }}</p>
    @if(filled($copy['intro'] ?? null))
        <p class="mb-4 leading-relaxed">{{ $copy['intro'] }}</p>
    @endif
    <dl class="grid gap-x-8 lg:grid-cols-2">
        <x-prop-row :label="__('Next peak')"
            :value="__('About :time on :date (UK time)', ['time' => $time($viewing['peak_local']), 'date' => $viewing['peak_local']->format('l j F Y')])" />
        <x-prop-row :label="__('Peak in UK darkness?')"
            :value="$viewing['peak_in_dark'] ? __('Yes') : __('No, it falls in daylight. The :night is the closest dark sky to it.', ['night' => $night])" />
        <x-prop-row :label="__('Dark sky')"
            :value="__(':start to :end', ['start' => $time($viewing['dark_start_local']), 'end' => $time($viewing['dark_end_local'])])" />
        <x-prop-row :label="__('Best UK viewing time')"
            :value="$viewing['best_start_local'] === null
                ? __('The radiant stays below 30° while the sky is dark, so expect low rates from the UK.')
                : __(':start to :end, while the sky is dark and the radiant is at least 30° up', ['start' => $time($viewing['best_start_local']), 'end' => $time($viewing['best_end_local'])])" />
        <x-prop-row :label="__('Where to look')"
            :value="$viewing['never_rises']
                ? __('The radiant stays below the UK horizon during darkness.')
                : __('Radiant highest at about :time, :alt° up in the :dir. Meteors can appear anywhere in the sky and trace back to that point.', [
                    'time' => $time($viewing['highest_local']),
                    'alt' => (int) round($viewing['highest_altitude_deg']),
                    'dir' => \App\Support\Sky\UkMeteorViewing::direction($viewing['highest_azimuth_deg']),
                ])" />
        <x-prop-row :label="__('Moon')"
            :value="__(':Phase Moon, :pct% lit, at the peak. :up', [
                'pct' => (int) round($viewing['moon_illumination'] * 100),
                'phase' => \App\Support\Sky\UkMeteorViewing::moonPhaseName($viewing['moon_illumination'], $viewing['moon_waxing']),
                'up' => $viewing['moon_up_fraction'] <= 0.0
                    ? __('Below the horizon for the whole best viewing time.')
                    : ($viewing['moon_up_fraction'] >= 1.0
                        ? __('Up for the whole best viewing time.')
                        : __('Up for about :share% of the best viewing time.', ['share' => (int) round($viewing['moon_up_fraction'] * 100)])),
            ])" />
    </dl>
    @foreach(($copy['notes'] ?? []) as $note)
        <p class="mt-3 leading-relaxed">{{ $note }}</p>
    @endforeach
    @if($faq !== [])
        <h3 class="mb-2 mt-6 font-serif text-xl">{{ __('Questions') }}</h3>
        <div class="space-y-3">
            @foreach($faq as $item)
                <div>
                    <p class="font-semibold">{{ $item['question'] }}</p>
                    <p class="leading-relaxed">{{ $item['answer'] }}</p>
                </div>
            @endforeach
        </div>
    @endif
    <p class="mt-5 text-sm leading-relaxed" style="color: var(--muted);">
        {{ __('Calculated for central London from the peak solar longitude and radiant in the IAU Meteor Data Center parameter set :set below, with low-precision Sun and Moon positions. Dark means the Sun is at least 12° below the horizon. Times are approximate to a few minutes and differ elsewhere in the UK, most for darkness further north. This is not a forecast of meteor rates or weather.', ['set' => $viewing['ad_no']]) }}
    </p>
</section>

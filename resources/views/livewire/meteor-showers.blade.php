<div>
    <x-page-header :title="__('Meteor showers')" :eyebrow="__('Observe & discover')"
        :lead="__('Meteor showers happen when Earth encounters streams of debris. Explore the IAU Meteor Data Center catalogue and the different observation campaigns behind each shower.')" />

    <div class="surface mb-6 grid gap-4 p-5 sm:grid-cols-3">
        <div>
            <label for="meteor-date" class="mb-2 block text-sm">{{ __('Approximate activity on date') }}</label>
            <input id="meteor-date" type="date" min="0001-01-01" max="9999-12-31" wire:model.live="activeOn"
                   aria-describedby="meteor-date-help{{ $invalidDate ? ' meteor-date-error' : '' }}"
                   @if($invalidDate) aria-invalid="true" @endif
                   class="w-full rounded border p-2" style="border-color: var(--border); background: var(--bg);">
        </div>
        <label class="flex items-center gap-3 self-center" for="meteor-established">
            <input id="meteor-established" type="checkbox" wire:model.live="establishedOnly">
            <span class="text-sm">{{ __('Established showers only (MDC codes 1 and 6)') }}</span>
        </label>
        <button type="button" wire:click="clearFilters" class="self-end rounded border p-2" style="border-color: var(--border);">{{ __('Clear filters') }}</button>
    </div>
    <p id="meteor-date-help" class="mb-6 text-sm leading-relaxed" style="color: var(--muted);">
        {{ __('The date filter selects parameter sets whose activity peak is within 15° of the Sun’s approximate ecliptic longitude on that date. It is a seasonal guide, not a prediction of visibility, meteor rates or exact activity dates. Clouds, moonlight, your location and the radiant’s height also matter. Sets without a reported peak are excluded by this filter.') }}
    </p>

    <div aria-live="polite">
        @if($rawInputErrors !== [])
            <div role="alert">
                @foreach($rawInputErrors as $error)
                    <p>{{ $error }}</p>
                @endforeach
                <a href="{{ route('meteor-showers.index') }}" class="underline">{{ __('Reset filters') }}</a>
            </div>
        @elseif($invalidDate)
            <p id="meteor-date-error" role="alert">{{ __('Enter a real date in YYYY-MM-DD format, from year 0001 to 9999, or clear the date filter.') }}</p>
        @elseif($apiDown)
            <x-api-down :section="__('The meteor-shower catalogue')" />
        @elseif($results->showers === [])
            <x-empty-state :title="__('No shower parameter sets returned')">
                {{ __('Try clearing the filters. This can also mean the backend has not yet loaded the meteor-shower catalogue; an empty response does not distinguish these cases.') }}
            </x-empty-state>
        @else
            <p class="mb-4 text-sm" style="color: var(--muted);">
                {{ __(':sets matching parameter sets grouped into :showers showers.', ['sets' => \App\Support\Format::count($results->parameterSetCount), 'showers' => \App\Support\Format::count(count($results->showers))]) }}
                {{ __('A shower may have several parameter sets from different observation campaigns. Open it to see all sets, including those outside these filters.') }}
            </p>
            @if($results->possiblyTruncated)
                <p class="surface mb-6 border-l-2 p-4" style="border-color: var(--accent);">
                    {{ __('The 1,000 parameter-set limit was reached. More showers or parameter sets may exist; the API does not report a total or provide another page. This list and its groups may be incomplete. Narrow the filters, or use the database download for the full published catalogue.') }}
                </p>
            @endif
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($results->showers as $shower)
                    <a href="{{ route('meteor-showers.show', $shower->code) }}" wire:key="meteor-{{ $shower->iauNo }}"
                       class="surface block p-5">
                        <p class="text-xs uppercase tracking-wide" style="color: var(--accent);">{{ $shower->code }} · {{ __('IAU :number', ['number' => $shower->iauNo]) }}</p>
                        <h2 class="mt-2 font-serif text-xl">{{ $shower->name }}</h2>
                        <p class="mt-2 text-sm" style="color: var(--muted);">{{ __(':count matching parameter sets', ['count' => count($shower->parameterSets)]) }}</p>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
    <p class="mt-8 text-sm" style="color: var(--muted);">{{ __('Source: IAU Meteor Data Center (MDC). At most 1,000 parameter sets are retrieved per filter selection; shower counts refer only to returned data.') }}</p>
</div>

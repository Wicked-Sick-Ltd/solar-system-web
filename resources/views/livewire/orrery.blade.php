<div>
    <x-page-header :title="__('Orrery')" :eyebrow="__('Where the worlds are')"
                   :lead="__('A top-down model of the solar system out to Pluto, positioned for a selected date from catalogue orbital elements. Distances use a square-root scale so the inner and outer worlds are both legible — it is not to scale.')" />

    {{-- Native GET controls remain usable without JavaScript. --}}
    <form action="{{ route('orrery') }}" method="get" wire:submit="$refresh" aria-label="{{ __('Orrery date') }}" class="mb-6 flex flex-wrap items-center gap-3">
        @foreach ([-30 => __('Back 30 days'), -1 => __('Back one day')] as $days => $label)
            @if ($stepDates[$days] !== null)
                <a href="{{ route('orrery', ['date' => $stepDates[$days]]) }}" wire:navigate class="inline-flex min-h-11 items-center rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border); color: var(--text);" aria-label="{{ $label }}">{{ $days }}d</a>
            @endif
        @endforeach
        <label class="sr-only" for="orrery-date">{{ __('Date (UTC)') }}</label>
        <input id="orrery-date" name="date" type="date" value="{{ $dateValue }}" min="0001-01-01" max="9999-12-31" required wire:model.live="date"
               @if ($invalidDate) aria-invalid="true" aria-describedby="orrery-date-error" @endif
               class="min-h-11 rounded-lg border px-3 py-2 text-base tabular-nums focus:outline-none"
               style="border-color: var(--border); background-color: var(--bg-elevated); color: var(--text);">
        <button type="submit" class="min-h-11 rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border);">{{ __('Apply date') }}</button>
        @foreach ([1 => __('Forward one day'), 30 => __('Forward 30 days')] as $days => $label)
            @if ($stepDates[$days] !== null)
                <a href="{{ route('orrery', ['date' => $stepDates[$days]]) }}" wire:navigate class="inline-flex min-h-11 items-center rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border); color: var(--text);" aria-label="{{ $label }}">+{{ $days }}d</a>
            @endif
        @endforeach
        <a href="{{ route('orrery') }}" wire:navigate class="inline-flex min-h-11 items-center rounded-lg px-3 py-2 text-sm font-medium" style="background-color: var(--accent); color: #07090f;">{{ __('Today') }}</a>
        @if ($prettyDate)<span class="ml-auto text-sm tabular-nums" style="color: var(--muted);">{{ $prettyDate }} UTC</span>@endif
    </form>
    <p wire:loading role="status" class="mb-4 text-sm">{{ __('Updating positions…') }}</p>

    @if ($invalidDate)
        <p id="orrery-date-error" role="alert" class="mb-4" style="color: var(--error);">{{ __('Choose a valid date in YYYY-MM-DD format, from year 0001 to 9999. No positions were requested.') }}</p>
    @elseif ($apiDown)
        <x-api-down :section="__('The orrery')" />
    @elseif (count($bodies) === 0)
        <x-empty-state :title="__('Positions unavailable for this date')">
            {{ __('The catalogue may lack orbital elements, or position requests may have failed. Try again or choose another date.') }}
        </x-empty-state>
    @else
        <p class="mb-4 text-sm" role="status">
            {{ __('Showing :shown of :expected selected bodies.', ['shown' => count($bodies), 'expected' => $expectedBodies]) }}
            @if ($missingBodies !== []) {{ __('Positions unavailable for: :names.', ['names' => implode(', ', $missingBodies)]) }} @endif
        </p>
        <div class="surface overflow-hidden p-2 sm:p-4" wire:loading.class="opacity-60">
            <svg viewBox="0 0 600 600" class="mx-auto h-auto w-full" style="max-width: 640px;"
                 role="group" aria-label="{{ __('Solar system positions for :date', ['date' => $prettyDate]) }}">
                {{-- Orbit rings --}}
                @foreach ($bodies as $body)
                    <circle cx="300" cy="300" r="{{ $body['r'] }}" fill="none"
                            stroke="var(--border)" stroke-width="1" opacity="0.5" />
                @endforeach

                {{-- The Sun --}}
                <circle cx="300" cy="300" r="9" fill="var(--accent)" />
                <circle cx="300" cy="300" r="16" fill="none" stroke="var(--accent)" stroke-width="0.75" opacity="0.35" />

                {{-- Bodies --}}
                @foreach ($bodies as $body)
                    <a href="{{ route('objects.show', $body['slug']) }}" wire:key="orrery-{{ $body['slug'] }}">
                        <circle cx="{{ $body['cx'] }}" cy="{{ $body['cy'] }}" r="6"
                                fill="{{ $body['colour'] }}" stroke="#05070d" stroke-width="0.75">
                            <title>{{ $body['label'] }} — {{ \App\Support\Format::au($body['distance']) }} {{ __('from the Sun') }}</title>
                        </circle>
                        <text x="{{ $body['cx'] + 10 }}" y="{{ $body['cy'] + 4 }}"
                              font-size="12" fill="var(--muted)" style="font-family: var(--font-sans);">{{ $body['label'] }}</text>
                    </a>
                @endforeach
            </svg>
        </div>

        <p class="mt-4 text-xs leading-relaxed" style="color: var(--color-faint); max-width: var(--container-prose);">
            {{ __('Positions use approximate two-body Kepler propagation. Accuracy varies by object and epoch; use JPL Horizons for precision work. Only bodies with usable positions are plotted. The circles show each body’s current radial distance, not its orbital path.') }}
        </p>
        <ul aria-label="{{ __('Plotted bodies and distances from the Sun') }}" class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
            @foreach ($bodies as $body)
                <li><a class="underline" href="{{ route('objects.show', $body['slug']) }}">{{ $body['label'] }}</a> — {{ \App\Support\Format::au($body['distance']) }} {{ __('from the Sun') }}</li>
            @endforeach
        </ul>
    @endif
</div>

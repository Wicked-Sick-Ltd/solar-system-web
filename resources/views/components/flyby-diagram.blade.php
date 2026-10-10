@props(['frame'])

{{-- Static figure. No animation, so reduced-motion preferences have nothing to disable. --}}
<figure {{ $attributes->merge(['class' => 'flyby-diagram min-w-0']) }} data-flyby @if ($frame->compact) data-flyby-compact @endif>
    <figcaption id="flyby-heading" class="mb-3 font-serif font-medium {{ $frame->compact ? 'text-base' : 'text-xl' }}">{{ __('Flyby geometry') }}</figcaption>
    <svg viewBox="0 0 {{ $frame->width }} {{ $frame->height }}"
         class="h-auto w-full max-w-full" role="img" aria-labelledby="flyby-title flyby-desc">
        <title id="flyby-title">{{ $frame->title }}</title>
        <desc id="flyby-desc">{{ $frame->summary }}</desc>

        <rect x="0.5" y="0.5" width="{{ $frame->width - 1 }}" height="{{ $frame->height - 1 }}"
              class="flyby-frame" rx="12" />

        @if ($frame->trajectoryApproximate)
            <text x="24" y="36" class="flyby-note" font-size="15">{{ __('approximate') }}</text>
        @endif

        <circle data-flyby-moon-orbit cx="{{ $frame->earthX }}" cy="{{ $frame->earthY }}" r="{{ $frame->moonOrbitRadius }}"
                class="flyby-moon-orbit" />

        <line data-flyby-sun x1="{{ $frame->sun['x1'] }}" y1="{{ $frame->sun['y1'] }}"
              x2="{{ $frame->sun['x2'] }}" y2="{{ $frame->sun['y2'] }}" class="flyby-sun" />
        <circle cx="{{ $frame->sun['x2'] }}" cy="{{ $frame->sun['y2'] }}" r="5" class="flyby-sun-disc" />
        <text x="{{ $frame->sun['labelX'] }}" y="{{ $frame->sun['labelY'] }}" class="flyby-label" font-size="13"
              text-anchor="middle">{{ __('Sun') }}</text>

        <polyline data-flyby-path points="{{ $frame->pathPoints }}" class="flyby-path" />

        @if ($frame->arrow)
            <polygon data-flyby-arrow points="{{ $frame->arrow['points'] }}" class="flyby-arrow" />
        @endif

        @foreach ($frame->ticks as $tick)
            <circle cx="{{ $tick['x'] }}" cy="{{ $tick['y'] }}" r="2.4" class="flyby-tick" />
            <text x="{{ $tick['x'] }}" y="{{ $tick['y'] - 14 }}" class="flyby-tick-label" font-size="12" text-anchor="middle">{{ $tick['label'] }}</text>
        @endforeach

        <line x1="{{ $frame->closestX }}" y1="{{ $frame->closestY }}" x2="{{ $frame->labelX }}" y2="{{ $frame->labelY + 4 }}"
              class="flyby-leader" />
        <circle data-flyby-closest cx="{{ $frame->closestX }}" cy="{{ $frame->closestY }}" r="4.5" class="flyby-closest" />
        <text x="{{ $frame->labelX }}" y="{{ $frame->labelY }}" class="flyby-label" font-size="14"
              text-anchor="{{ $frame->labelAnchor }}">{{ $frame->distanceLabel }}</text>
        <text x="{{ $frame->labelX }}" y="{{ $frame->labelY + 16 }}" class="flyby-label" font-size="13"
              text-anchor="{{ $frame->labelAnchor }}">{{ $frame->timeLabel }}</text>

        <circle cx="{{ $frame->earthX }}" cy="{{ $frame->earthY }}" r="{{ $frame->earthRadius }}" class="flyby-earth" />
        <text x="{{ $frame->earthX }}" y="{{ $frame->earthY + $frame->earthRadius + 14 }}" class="flyby-label" font-size="13"
              text-anchor="middle">{{ __('Earth') }}</text>

        @if ($frame->moon)
            <circle data-flyby-moon cx="{{ $frame->moon['x'] }}" cy="{{ $frame->moon['y'] }}" r="4" class="flyby-moon" />
            <text x="{{ $frame->moon['x'] + 8 }}" y="{{ $frame->moon['y'] - 8 }}" class="flyby-label" font-size="13">{{ __('Moon') }}</text>
        @endif

        @if ($frame->inset)
            <g transform="translate({{ $frame->inset['x'] }} {{ $frame->inset['y'] }})">
                <rect x="0" y="0" width="{{ $frame->inset['size'] }}" height="{{ $frame->inset['size'] }}" class="flyby-inset" rx="8" />
                <text x="10" y="18" class="flyby-note" font-size="11">{{ __('Heliocentric, approximate') }}</text>
                @if ($frame->inset['earthPath'] !== '')
                    <polyline points="{{ $frame->inset['earthPath'] }}" class="flyby-inset-earth" />
                @endif
                @if ($frame->inset['objectPath'] !== '')
                    <polyline points="{{ $frame->inset['objectPath'] }}" class="flyby-inset-object" />
                @endif
                <circle cx="75" cy="75" r="2.5" class="flyby-sun-disc" />
                <circle cx="{{ $frame->inset['earthX'] }}" cy="{{ $frame->inset['earthY'] }}" r="3" class="flyby-earth" />
                <circle cx="{{ $frame->inset['objectX'] }}" cy="{{ $frame->inset['objectY'] }}" r="3.2" class="flyby-closest" />
            </g>
        @endif
    </svg>
    <p id="flyby-summary" class="mt-3 text-sm leading-relaxed" style="color: var(--text); max-width: var(--container-prose);">{{ $frame->summary }}</p>
    @unless ($frame->trajectoryApproximate)
        <p class="mt-1 text-xs" style="color: var(--muted);">
            <a href="https://ssd.jpl.nasa.gov/horizons/" rel="noopener noreferrer">{{ __('JPL Horizons') }}</a>
        </p>
    @endunless
</figure>

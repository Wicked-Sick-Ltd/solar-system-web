@props([
    'name',                 // the body drawn as "object" (the page's object, or its parent for a moon)
    'position',             // App\Services\SolarApi\Data\Position — heliocentric, today
    'earth',                // App\Services\SolarApi\Data\Position — Earth, same date
    'elements' => null,     // ?App\Services\SolarApi\Data\OrbitalElements — for the orbit path
    'colour' => null,       // hex, e.g. from visual.dominant_colour_hex
    'isEarth' => false,     // true when the object IS Earth (draw one marker, not two)
    'note' => null,         // e.g. "The Moon is shown at its parent, Earth."
])

@php
    use App\Support\Format;
    use App\Support\OrbitPlot;

    // A 320×320 canvas, Sun at the centre, everything to one linear scale.
    $size = 320.0; $centre = 160.0; $maxRadius = 138.0;
    $scale = OrbitPlot::scale([$position, $earth], $elements, $maxRadius);
    $maxAu = $maxRadius / $scale;

    $sun = OrbitPlot::toSvg(0.0, 0.0, $scale, $centre);
    $earthPt = OrbitPlot::toSvg((float) $earth->xAu, (float) $earth->yAu, $scale, $centre);
    $objPt = OrbitPlot::toSvg((float) $position->xAu, (float) $position->yAu, $scale, $centre);

    $path = $elements ? OrbitPlot::orbitPath($elements) : [];
    $pathAttr = implode(' ', array_map(
        fn ($p) => implode(',', array_map(fn ($v) => round($v, 1), OrbitPlot::toSvg($p['x'], $p['y'], $scale, $centre))),
        $path,
    ));

    $distanceAu = OrbitPlot::distanceAu($position, $earth);
    // Fallback is the accent, not the link blue — Earth's orbit is already blue.
    $objColour = $colour ?: 'var(--accent)';
    $earthColour = '#6b93d6';

    // Faint inner-planet rings for context once the view is wide enough that
    // Earth's orbit is small (Jupiter, Saturn, main-belt views) — never on the
    // inner planets' own pages, and not once they would collapse into dots
    // around the Sun (beyond ~12 AU: Uranus, Neptune, TNOs, long-period comets).
    $contextRings = ($maxAu >= 1.7 && $maxAu <= 12.0) ? ['Mercury' => 0.387, 'Venus' => 0.723, 'Mars' => 1.524] : [];

    // Label offsets: push each label away from the Sun so it doesn't sit on the line.
    $labelOffset = fn (array $pt, float $dx = 9.0) => [
        'x' => round($pt['cx'] + ($pt['cx'] >= $centre ? $dx : -$dx), 1),
        'y' => round($pt['cy'] + 4, 1),
        'anchor' => $pt['cx'] >= $centre ? 'start' : 'end',
    ];
    $objLabel = $labelOffset($objPt);
    $earthLabel = $labelOffset($earthPt);
    $r = fn (float $v) => round($v, 1);
@endphp

<figure class="relative-position" style="max-width: 400px;">
    {{-- 40px of horizontal gutter either side so edge labels ("Earth", "Saturn") aren't clipped. --}}
    <svg viewBox="-40 0 {{ $size + 80 }} {{ $size }}" class="h-auto w-full" role="img"
         aria-label="{{ __(':name, Earth and the Sun seen from above the ecliptic, to scale, for :date', ['name' => $name, 'date' => Format::date($position->inputDate) ?? __('today')]) }}">
        {{-- Context rings (outer views only) --}}
        @foreach ($contextRings as $ring => $au)
            <circle cx="{{ $centre }}" cy="{{ $centre }}" r="{{ $r($au * $scale) }}"
                    fill="none" stroke="var(--border)" stroke-width="0.6" opacity="0.5">
                <title>{{ __('Orbit of :name', ['name' => $ring]) }}</title>
            </circle>
        @endforeach

        {{-- Earth's orbit --}}
        <circle cx="{{ $centre }}" cy="{{ $centre }}" r="{{ $r(1.0 * $scale) }}"
                fill="none" stroke="{{ $earthColour }}" stroke-width="0.9" opacity="0.55" />

        {{-- The object's orbit, projected onto the ecliptic --}}
        @if ($pathAttr !== '')
            <polyline points="{{ $pathAttr }}" fill="none" stroke="{{ $objColour }}"
                      stroke-width="1.1" opacity="0.7" />
        @endif

        {{-- Earth–object line --}}
        @unless ($isEarth)
            <line x1="{{ $r($earthPt['cx']) }}" y1="{{ $r($earthPt['cy']) }}"
                  x2="{{ $r($objPt['cx']) }}" y2="{{ $r($objPt['cy']) }}"
                  stroke="var(--muted)" stroke-width="0.9" stroke-dasharray="3 3" opacity="0.8" />
        @endunless

        {{-- The Sun --}}
        <circle data-body="sun" cx="{{ $sun['cx'] }}" cy="{{ $sun['cy'] }}" r="6" fill="var(--accent)">
            <title>{{ __('Sun') }}</title>
        </circle>
        <circle cx="{{ $sun['cx'] }}" cy="{{ $sun['cy'] }}" r="11" fill="none" stroke="var(--accent)" stroke-width="0.6" opacity="0.4" />

        {{-- Earth --}}
        <circle data-body="earth" cx="{{ $r($earthPt['cx']) }}" cy="{{ $r($earthPt['cy']) }}" r="4"
                fill="{{ $earthColour }}" stroke="#05070d" stroke-width="0.75">
            <title>{{ __('Earth — :d from the Sun', ['d' => Format::au($earth->distanceFromSunAu)]) }}</title>
        </circle>
        @unless ($isEarth)
            <text x="{{ $earthLabel['x'] }}" y="{{ $earthLabel['y'] }}" text-anchor="{{ $earthLabel['anchor'] }}"
                  font-size="11" fill="var(--muted)" style="font-family: var(--font-sans);">{{ __('Earth') }}</text>
        @endunless

        {{-- The object --}}
        <circle data-body="object" cx="{{ $r($objPt['cx']) }}" cy="{{ $r($objPt['cy']) }}" r="{{ $isEarth ? 4 : 5 }}"
                fill="{{ $objColour }}" stroke="#05070d" stroke-width="0.75">
            <title>{{ __(':name — :d from the Sun', ['name' => $name, 'd' => Format::au($position->distanceFromSunAu)]) }}</title>
        </circle>
        <text x="{{ $objLabel['x'] }}" y="{{ $objLabel['y'] }}" text-anchor="{{ $objLabel['anchor'] }}"
              font-size="11" font-weight="600" fill="var(--text)" style="font-family: var(--font-sans);">{{ $name }}</text>
    </svg>
    <figcaption class="mt-2 text-xs leading-relaxed" style="color: var(--color-faint);">
        @if ($note)
            {{ $note }}
        @endif
        {{ __('Seen from above the ecliptic, north up, to scale. Grid: Earth\'s orbit in blue.') }}
        @if ($distanceAu !== null && ! $isEarth)
            {{ __(':name is :au from Earth today (:light).', ['name' => $name, 'au' => Format::au($distanceAu), 'light' => OrbitPlot::lightTime($distanceAu)]) }}
        @endif
    </figcaption>
</figure>

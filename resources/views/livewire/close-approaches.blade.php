@php
    use App\Support\Format;
@endphp

<div>
    <x-page-header :title="__('Close approaches')" :eyebrow="__('Passing Earth')"
                   :lead="__('Asteroids and comets that will pass within about 20 lunar distances of Earth in the next :days days, soonest first.', ['days' => $days])" />

    @if ($apiDown)
        <x-api-down :section="__('Close approaches')" />
    @elseif ($approaches === [])
        <x-empty-state :title="__('Nothing passing close in the next :days days', ['days' => $days])" />
    @else
        <div class="surface overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-left" style="border-color: var(--border); color: var(--muted);">
                        <th class="px-4 py-3 font-medium">{{ __('When (UTC)') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Object') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ __('Lunar distances') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ __('Distance') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ __('Relative speed') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($approaches as $approach)
                        <tr class="border-b last:border-0" style="border-color: var(--border);" wire:key="ca-{{ $approach->objectId }}-{{ $approach->cdIso }}">
                            <td class="px-4 py-3 tabular-nums" style="color: var(--text);">{{ str_replace(['T', 'Z'], [' ', ''], (string) $approach->cdIso) }}</td>
                            <td class="px-4 py-3">
                                @if ($approach->objectId)
                                    <a class="link-quiet underline" href="{{ route('objects.show', $approach->objectId) }}" wire:navigate>{{ $approach->name }}</a>
                                @else
                                    {{ $approach->name }}
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums" style="color: var(--text);">{{ $approach->lunarDistances() !== null ? number_format($approach->lunarDistances(), 1).' LD' : '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums" style="color: var(--muted);">{{ Format::au($approach->distAu, 4) ?? '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums" style="color: var(--muted);">{{ Format::unit($approach->vRelKmS, 'km/s', 1) ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-2 text-xs" style="color: var(--color-faint);">{{ __('1 lunar distance = 384,400 km. Nominal distances; the smallest objects have wider uncertainties. Source: JPL close-approach data, refreshed nightly.') }}</p>
    @endif
</div>

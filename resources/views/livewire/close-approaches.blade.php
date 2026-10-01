@php
    use App\Support\CloseApproachFormat;
@endphp

<div>
    <x-page-header :title="__('Close approaches')" :eyebrow="__('Passing Earth')"
                   :lead="__('Catalogue encounters within about 20 lunar distances of Earth in a :days-day window starting today (UTC), soonest first.', ['days' => $days])" />

    <p class="mb-4 text-sm" style="color: var(--muted);">{{ __('Window: :from 00:00 UTC to :to 00:00 UTC.', ['from' => $windowStart, 'to' => $windowEnd]) }}</p>
    @if ($apiDown)
        <x-api-down :section="__('Close approaches')" />
    @elseif ($approaches === [])
        <x-empty-state :title="__('No close approaches returned')">
            {{ __('The catalogue returned no records for this window. Coverage may be incomplete, and older catalogue builds may not include close-approach data. This does not establish that no encounters occur.') }}
        </x-empty-state>
    @else
        @if ($limitReached)
            <p class="mb-4 text-sm" role="status">{{ __('The catalogue returned its limit of :limit closest encounters in this window. Other encounters may be omitted; the returned records are shown soonest first.', ['limit' => $limit]) }}</p>
        @endif
        <div class="surface overflow-x-auto" tabindex="0" role="region" aria-label="{{ __('Close-approach results; scroll horizontally for all columns') }}">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-left" style="border-color: var(--border); color: var(--muted);">
                        <th scope="col" class="px-4 py-3 font-medium">{{ __('When (UTC)') }}</th>
                        <th scope="col" class="px-4 py-3 font-medium">{{ __('Object') }}</th>
                        <th scope="col" class="px-4 py-3 text-right font-medium">{{ __('Lunar distances') }}</th>
                        <th scope="col" class="px-4 py-3 text-right font-medium">{{ __('Distance') }}</th>
                        <th scope="col" class="px-4 py-3 text-right font-medium">{{ __('Relative speed') }}</th>
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
                            <td class="px-4 py-3 text-right tabular-nums" style="color: var(--text);">{{ CloseApproachFormat::measurement($approach->lunarDistances(), 'LD', 1) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums" style="color: var(--muted);">{{ CloseApproachFormat::measurement($approach->distAu, 'AU', 4) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums" style="color: var(--muted);">{{ CloseApproachFormat::measurement($approach->vRelKmS, 'km/s', 1) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-2 text-xs" style="color: var(--color-faint);">{{ __('1 lunar distance = 384,400 km. Nominal distances; uncertainties vary by object and orbit solution. A less-than sign marks a positive value below the table’s display precision; a dash means not reported. Source: JPL close-approach data in the catalogue snapshot.') }}</p>
    @endif
</div>

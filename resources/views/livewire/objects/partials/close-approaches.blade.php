@php
    use App\Support\Format;
@endphp

<section class="mt-10" aria-labelledby="ca-heading">
    <h2 id="ca-heading" class="mb-3 font-serif text-2xl font-medium">
        {{ __('Close approaches') }}
        @if ($closeApproachCount)
            <span class="text-base font-normal" style="color: var(--muted);">{{ __(':count on record', ['count' => Format::count($closeApproachCount)]) }}</span>
        @endif
    </h2>
    <div class="surface overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b text-left" style="border-color: var(--border); color: var(--muted);">
                    <th class="px-4 py-3 font-medium">{{ __('When (UTC)') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Body') }}</th>
                    <th class="px-4 py-3 text-right font-medium">{{ __('Distance') }}</th>
                    <th class="px-4 py-3 text-right font-medium">{{ __('Lunar distances') }}</th>
                    <th class="px-4 py-3 text-right font-medium">{{ __('Relative speed') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($closeApproaches as $closeApproach)
                    <tr class="border-b last:border-0" style="border-color: var(--border);">
                        <td class="px-4 py-3 tabular-nums" style="color: var(--text);">{{ str_replace(['T', 'Z'], [' ', ''], (string) $closeApproach->cdIso) }}</td>
                        <td class="px-4 py-3" style="color: var(--text);">{{ $closeApproach->body }}</td>
                        <td class="px-4 py-3 text-right tabular-nums" style="color: var(--muted);">{{ Format::au($closeApproach->distAu, 4) ?? '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums" style="color: var(--muted);">{{ $closeApproach->lunarDistances() !== null ? number_format($closeApproach->lunarDistances(), 1).' LD' : '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums" style="color: var(--muted);">{{ Format::unit($closeApproach->vRelKmS, 'km/s', 1) ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="mt-2 text-xs" style="color: var(--color-faint);">{{ __('Next ten from today. 1 lunar distance = 384,400 km. Source: JPL close-approach data.') }}</p>
</section>

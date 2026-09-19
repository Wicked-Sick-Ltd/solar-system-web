@php
    use App\Support\Format;

    $columns = [
        ['field' => 'name', 'label' => __('Name'), 'align' => 'left'],
        ['field' => 'radiusKm', 'label' => __('Radius'), 'align' => 'right'],
        ['field' => 'massKg', 'label' => __('Mass'), 'align' => 'right'],
        ['field' => 'densityGCm3', 'label' => __('Density'), 'align' => 'right'],
        ['field' => 'semiMajorAxisAu', 'label' => __('Distance'), 'align' => 'right'],
        ['field' => 'orbitalPeriodDays', 'label' => __('Period'), 'align' => 'right'],
    ];
@endphp

<section class="mt-10" aria-labelledby="moons-heading" wire:loading.class="opacity-60">
    <h2 id="moons-heading" class="mb-3 font-serif text-2xl font-medium">
        {{ __(':count moons', ['count' => count($moons)]) }}
    </h2>
    <div class="surface overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b text-left" style="border-color: var(--border); color: var(--muted);">
                    @foreach ($columns as $column)
                        <th class="px-4 py-3 font-medium @if ($column['align'] === 'right') text-right @endif">
                            <button type="button" wire:click="sortBy('{{ $column['field'] }}')"
                                    class="inline-flex items-center gap-1 hover:underline"
                                    @if ($sortField === $column['field']) style="color: var(--accent);" @endif>
                                {{ $column['label'] }}
                                @if ($sortField === $column['field'])
                                    <span aria-hidden="true">{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </button>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($moons as $moon)
                    <tr class="border-b last:border-0" style="border-color: var(--border);" wire:key="moon-{{ $moon->id }}">
                        <td class="px-4 py-3">
                            <a class="font-medium" style="color: var(--link);" href="{{ route('objects.show', $moon->slug()) }}">{{ $moon->name }}</a>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums" style="color: var(--muted);">{{ Format::km($moon->radiusKm) ?? '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums" style="color: var(--muted);">{{ Format::massKg($moon->massKg) ?? '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums" style="color: var(--muted);">{{ Format::unit($moon->densityGCm3, 'g/cm³', 2) ?? '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums" style="color: var(--muted);">{{ Format::au($moon->semiMajorAxisAu, 4) ?? '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums" style="color: var(--muted);">{{ Format::periodDays($moon->orbitalPeriodDays) ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

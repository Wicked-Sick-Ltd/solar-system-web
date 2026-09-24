<x-layouts.app>
    <x-page-header :title="__('Your visibility alerts')"
                   :lead="__('We will email you when a saved object is up after dark from the saved location.')" />

    @if (session('status'))
        <p class="mb-4 rounded-lg border px-4 py-3 text-sm" style="border-color: var(--border); color: var(--text);">
            {{ session('status') }}
        </p>
    @endif

    @if ($alerts->isEmpty())
        <x-empty-state :title="__('No alerts yet')"
                       :body="__('Open any object page, set your location in the sky panel, then save a visibility alert.')" />
    @else
        <section class="surface overflow-x-auto" aria-label="{{ __('Saved alerts') }}">
            <table class="w-full text-sm">
                <thead>
                <tr class="border-b text-left" style="border-color: var(--border); color: var(--muted);">
                    <th class="px-4 py-3 font-medium">{{ __('Object') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Location') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Last triggered') }}</th>
                    <th class="px-4 py-3 text-right font-medium">{{ __('Action') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($alerts as $alert)
                    <tr class="border-b last:border-0" style="border-color: var(--border);">
                        <td class="px-4 py-3">
                            <a class="underline" style="color: var(--link);" href="{{ route('objects.show', $alert->object_id) }}">{{ $alert->object_id }}</a>
                        </td>
                        <td class="px-4 py-3 tabular-nums">{{ number_format((float) $alert->latitude, 2) }}, {{ number_format((float) $alert->longitude, 2) }}</td>
                        <td class="px-4 py-3">{{ $alert->last_triggered_at?->diffForHumans() ?? __('Never') }}</td>
                        <td class="px-4 py-3 text-right">
                            <form method="POST" action="{{ route('alerts.destroy', $alert) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-lg border px-3 py-1.5"
                                        style="border-color: var(--border); color: var(--text);">{{ __('Remove') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>
    @endif
</x-layouts.app>

<x-layouts.app>
    <x-page-header :title="__('Observing target starter catalogues')" :eyebrow="__('Stars and deep sky')" :lead="__('Explore bright stars, historical double-star records and a selected deep-sky sample with traceable sources.')" />
    <form method="get" action="{{ route('observing-targets.index') }}" role="search" aria-label="{{ __('Search observing starter targets') }}" class="surface mb-6 grid gap-4 p-5 sm:grid-cols-3">
        <label class="block text-sm">{{ __('Name or identifier contains') }}<input name="q" type="search" value="{{ $values['q'] }}" maxlength="200" placeholder="M 31 or HR 2491" class="mt-2 block min-h-11 w-full rounded border px-3 py-2" style="background: var(--bg); border-color: var(--border);"></label>
        <label class="block text-sm">{{ __('Catalogue family') }}<select name="family" class="mt-2 block min-h-11 w-full rounded border px-3 py-2" style="background: var(--bg); border-color: var(--border);">
            <option value="">{{ __('All starter records') }}</option>
            @foreach (\App\Services\SolarApi\Data\StarterTarget::FAMILIES as $value => $label)<option value="{{ $value }}" @selected($values['family'] === $value)>{{ __($label) }}</option>@endforeach
        </select></label>
        <div class="flex flex-wrap items-end gap-4"><button type="submit" class="min-h-11 rounded border px-4 py-2" style="border-color: var(--border);">{{ __('Search sample') }}</button><a href="{{ route('observing-targets.index') }}" class="inline-block min-h-11 content-center underline">{{ __('Reset') }}</a></div>
    </form>
    @include('starter-targets.context')
    @if ($errors !== [])
        <div role="alert" class="surface space-y-3 p-5">@foreach ($errors as $error)<p>{{ $error }}</p>@endforeach
            <p>{{ __('Correct the filters and search to start from page one.') }}</p>
            @if ($filters)<a class="inline-block min-h-11 content-center underline" href="{{ route('observing-targets.index', $filters->query(1)) }}">{{ __('First page with these filters') }}</a>@endif
        </div>
    @elseif ($apiDown)
        <x-api-down :section="__('The observing starter catalogues')" />
    @elseif ($catalogue && $filters)
        @if ($catalogue->total === 0)
            <x-empty-state :title="__('No records match in this starter sample')">{{ __('Try a catalogue identifier or reset the filters. Absence from this bounded sample does not mean an object does not exist.') }}</x-empty-state>
        @else
            <p class="mb-4 text-sm">{{ __(':count matching records · page :page of :pages', ['count' => $catalogue->total, 'page' => $filters->page, 'pages' => $pages]) }}</p>
            <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" aria-label="{{ __('Starter catalogue results') }}">
                @foreach ($catalogue->targets as $target)
                    <li class="surface min-w-0 space-y-3 p-5">
                        <h2 class="text-xl font-semibold"><a href="{{ route('observing-targets.show', $target->id) }}" class="inline-block min-h-11 content-center break-words underline">{{ $target->name }}</a></h2>
                        <p class="break-words text-sm">{{ implode(' · ', $target->aliases) }}</p>
                        <p class="text-sm">{{ implode(' · ', array_map(fn ($family) => __(\App\Services\SolarApi\Data\StarterTarget::FAMILIES[$family]), $target->families)) }}</p>
                        <p class="text-sm">{{ __('Recorded magnitude') }}: {{ $target->measurement('magnitude') }} {{ $target->data['magnitude_flag'] ?? '' }} · {{ $target->data['magnitude_band'] }}</p>
                        @if (in_array('double_star', $target->families, true))<p class="text-sm">{{ __('Historical separation') }}: {{ $target->measurement('separation_arcsec', 'arcsec') }} · {{ __('date unknown') }}</p>@endif
                        <p class="break-all text-xs" style="color: var(--muted);">{{ $target->id }}</p>
                    </li>
                @endforeach
            </ul>
            <nav aria-label="{{ __('Starter catalogue pages') }}" class="my-6 flex flex-wrap justify-between gap-4">
                @if ($filters->page > 1)<a rel="prev" class="inline-block min-h-11 content-center underline" href="{{ route('observing-targets.index', $filters->query($filters->page - 1)) }}">← {{ __('Previous') }}</a>@else<span></span>@endif
                @if ($filters->page < $pages)<a rel="next" class="inline-block min-h-11 content-center underline" href="{{ route('observing-targets.index', $filters->query($filters->page + 1)) }}">{{ __('Next') }} →</a>@endif
            </nav>
        @endif
        <div class="mt-8 grid gap-4 lg:grid-cols-2">@foreach ($catalogue->sources as $source)@include('starter-targets.source', ['source' => $source])@endforeach</div>
    @endif
</x-layouts.app>

<div>
    <nav aria-label="{{ __('Object of the day by date') }}" class="mb-8 flex flex-wrap items-center justify-between gap-3 text-sm">
        @if ($previous)
            <a href="{{ \App\Support\ObjectOfTheDay::url($previous) }}" class="link-quiet inline-flex min-h-11 items-center" rel="prev">
                <span aria-hidden="true">←&nbsp;</span>{{ __('Previous day') }}<span class="sr-only">: {{ $previous->format('j F Y') }}</span>
            </a>
        @else
            <span></span>
        @endif
        @if ($next)
            <a href="{{ \App\Support\ObjectOfTheDay::url($next) }}" class="link-quiet inline-flex min-h-11 items-center" rel="next">
                {{ __('Next day') }}<span class="sr-only">: {{ $next->format('j F Y') }}</span><span aria-hidden="true">&nbsp;→</span>
            </a>
        @elseif (! $isToday)
            <a href="{{ route('today') }}" class="link-quiet inline-flex min-h-11 items-center">{{ __('Today’s object') }}</a>
        @endif
    </nav>

    @if ($apiDown)
        <x-page-header :title="__('Object of the day')" />
        <x-api-down :section="__('The object of the day')" />
    @else
        @php $colour = $object->visual?->safeColourHex(); @endphp
        <article aria-labelledby="today-heading">
            <p class="mb-3 text-xs font-semibold uppercase tracking-[0.16em]" style="color: var(--accent);">
                {{ __('Object of the day') }} · <time datetime="{{ $day->format('Y-m-d') }}">{{ $day->format('l j F Y') }}</time>
            </p>

            <div class="flex flex-col gap-6 sm:flex-row sm:items-center">
                <span aria-hidden="true" class="block h-28 w-28 shrink-0 rounded-full sm:h-36 sm:w-36"
                      style="background: radial-gradient(circle at 32% 30%, color-mix(in srgb, {{ $colour ?? 'var(--accent)' }} 90%, white), {{ $colour ?? 'var(--accent)' }} 65%, #05070d);
                             box-shadow: 0 0 60px color-mix(in srgb, {{ $colour ?? 'var(--accent)' }} 35%, transparent);"></span>
                <div class="min-w-0">
                    @if ($object->typeLabel())
                        <x-badge tone="amber">{{ $object->typeLabel() }}</x-badge>
                    @endif
                    <h1 id="today-heading" class="mt-2 font-serif text-4xl font-medium sm:text-5xl">{{ $object->name }}</h1>
                    @if ($object->designation && $object->designation !== $object->name)
                        <p class="mt-1 text-sm" style="color: var(--color-faint);">{{ $object->designation }}</p>
                    @endif
                </div>
            </div>

            @if ($fact)
                <section class="surface mt-8 p-6 sm:p-8" aria-labelledby="fact-heading">
                    <h2 id="fact-heading" class="text-xs font-semibold uppercase tracking-[0.16em]" style="color: var(--accent);">{{ __('Fun fact') }}</h2>
                    <p class="mt-3 font-serif text-2xl leading-snug" style="color: var(--text); max-width: 40ch;">{{ $fact }}</p>
                    <p class="mt-3 text-xs" style="color: var(--color-faint);">{{ __('Worked out from the catalogue’s own measurements for :name.', ['name' => $object->name]) }}</p>
                </section>
            @endif

            @if ($stats !== [])
                <section class="mt-8" aria-labelledby="stats-heading">
                    <h2 id="stats-heading" class="sr-only">{{ __('Key stats') }}</h2>
                    <dl class="grid gap-4 sm:grid-cols-3">
                        @foreach ($stats as $stat)
                            <div class="surface p-5">
                                <dt class="text-xs uppercase tracking-wide" style="color: var(--muted);">{{ $stat['label'] }}</dt>
                                <dd class="mt-1 font-serif text-2xl tabular-nums" style="color: var(--text);">{{ $stat['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endif

            <p class="mt-6">
                <a href="{{ route('objects.show', $object->slug()) }}" class="link-quiet inline-flex min-h-11 items-center font-medium underline">
                    {{ __('Explore the full record for :name', ['name' => $object->name]) }}<span aria-hidden="true">&nbsp;→</span>
                </a>
            </p>

            <section class="mt-10" aria-labelledby="share-heading">
                <h2 id="share-heading" class="mb-4 font-serif text-2xl font-medium">{{ __('Share this object') }}</h2>
                <x-share-links :url="$shareUrl"
                               :title="__('Object of the day: :name', ['name' => $object->name])"
                               :text="$fact ? __(':name, the object of the day: :fact', ['name' => $object->name, 'fact' => $fact]) : __(':name is the Public Universe object of the day.', ['name' => $object->name])"
                               :label="__('Share :name', ['name' => $object->name])"
                               :show-link="true" />
                <figure class="mt-6 max-w-xl">
                    <img src="{{ \App\Support\ShareImage::todayUrl($day) }}" width="1200" height="630" loading="lazy" decoding="async"
                         class="h-auto w-full rounded-xl border" style="border-color: var(--border);"
                         alt="{{ __('Share card for :name: :fact', ['name' => $object->name, 'fact' => $fact ?? $object->typeLabel()]) }}">
                    <figcaption class="mt-2 text-xs" style="color: var(--color-faint);">{{ __('The card shown when this link is shared.') }}</figcaption>
                </figure>
            </section>
        </article>
    @endif
</div>

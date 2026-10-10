<x-layouts.app>
    @push('head')
        <link rel="alternate" type="application/atom+xml" title="{{ __('What’s new') }}" href="{{ route('releases.feed') }}">
        <link rel="alternate" type="application/rss+xml" title="{{ __('What’s new') }}" href="{{ route('releases.feed.rss') }}">
    @endpush
    <x-page-header :title="__('What’s new')" :eyebrow="__('Public Universe updates')" :lead="__('Shipped improvements to exploring and understanding astronomical data, grouped by the day they merged.')" />
    @unless ($version)
        <p class="mb-8 text-sm" style="color: var(--muted);">
            <a class="underline" href="{{ route('releases.feed') }}">{{ __('Subscribe with Atom') }}</a>
            <span aria-hidden="true"> · </span>
            <a class="underline" href="{{ route('releases.feed.rss') }}">{{ __('Subscribe with RSS') }}</a>
        </p>
    @endunless
    @if ($version)<a href="{{ route('releases.index') }}" class="mb-6 inline-block underline">← {{ __('All updates') }}</a>@endif
    @if ($releases->isEmpty() && $changes === [])
        <x-empty-state :title="__('Community preview')">{{ __('Published community release notes will appear here after the first release is deployed and verified.') }}</x-empty-state>
    @else
        @if ($releases->isNotEmpty())
            <div class="space-y-8">
                @foreach ($releases as $release)
                    <article class="surface min-w-0 space-y-5 p-5 sm:p-8" aria-labelledby="release-{{ $release->id }}">
                        <div>
                            <p class="mb-2 text-sm" style="color: var(--muted);">{{ __('Version :version', ['version' => $release->version]) }} · <time datetime="{{ $release->published_at->toIso8601String() }}">{{ $release->published_at->format('j F Y') }}</time></p>
                            <h2 id="release-{{ $release->id }}" class="break-words text-2xl font-semibold"><a href="{{ route('releases.show', $release->version) }}" class="underline decoration-transparent underline-offset-4 hover:decoration-current">{{ $release->notes['title'] }}</a></h2>
                        </div>
                        <p class="max-w-3xl break-words">{{ $release->notes['summary'] }}</p>
                        @foreach ($release->notes['sections'] as $heading => $items)
                            <section class="space-y-2">
                                <h3 class="break-words text-lg font-semibold">{{ $heading }}</h3>
                                <ul class="list-disc space-y-2 pl-5">
                                    @foreach ($items as $item)<li class="break-words">{{ $item }}</li>@endforeach
                                </ul>
                            </section>
                        @endforeach
                    </article>
                @endforeach
            </div>
        @endif
        @if (! $version && $changes !== [])
            <div class="{{ $releases->isNotEmpty() ? 'mt-12' : '' }} space-y-10">
                @foreach ($changes as $group)
                    <section class="space-y-4" aria-labelledby="changes-{{ $group['version'] ?? $group['date'] }}">
                        <h2 id="changes-{{ $group['version'] ?? $group['date'] }}" class="text-xl font-semibold">
                            @if ($group['version'])
                                {{ __('Version :version', ['version' => $group['version']]) }}
                                <span class="text-base font-normal" style="color: var(--muted);">· <time datetime="{{ $group['date'] }}">{{ \Illuminate\Support\Carbon::parse($group['date'])->format('j F Y') }}</time></span>
                            @else
                                <time datetime="{{ $group['date'] }}">{{ \Illuminate\Support\Carbon::parse($group['date'])->format('j F Y') }}</time>
                            @endif
                        </h2>
                        <ul class="space-y-4">
                            @foreach ($group['entries'] as $entry)
                                <li id="pr-{{ $entry['number'] }}" class="surface min-w-0 p-5">
                                    <a class="block break-words underline decoration-transparent underline-offset-4 hover:decoration-current" href="{{ $entry['url'] }}" rel="noopener noreferrer">
                                        <span class="text-sm font-semibold" style="color: var(--muted);">{{ match ($entry['type']) {
                                            'feat' => __('New'),
                                            'fix' => __('Fixed'),
                                            'perf' => __('Faster'),
                                            'refactor' => __('Improved'),
                                            'docs' => __('Documentation'),
                                            default => __('Update'),
                                        } }}</span>
                                        <span class="mt-1 block text-lg">{{ $entry['summary'] }}</span>
                                        <span class="mt-2 block text-sm" style="color: var(--muted);">{{ __('Pull request :number', ['number' => $entry['number']]) }}<span class="sr-only"> {{ __('on GitHub') }}</span></span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>
        @endif
    @endif
</x-layouts.app>

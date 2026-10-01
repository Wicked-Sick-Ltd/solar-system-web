<x-layouts.app>
    <x-page-header :title="__('What’s new')" :eyebrow="__('Public Universe updates')" :lead="__('Published improvements to exploring, understanding and reusing astronomical data.')" />
    @if ($version)<a href="{{ route('releases.index') }}" class="mb-6 inline-block underline">← {{ __('All updates') }}</a>@endif
    @if ($releases->isEmpty())
        <x-empty-state :title="__('Community preview')">{{ __('Published community release notes will appear here after the first release is deployed and verified.') }}</x-empty-state>
    @else
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
</x-layouts.app>

<div>
    <x-page-header :title="__('Classroom handouts')" :eyebrow="__('For schools')">
        {{ __('Free A4 handouts for teachers, built on the same live NASA/JPL data as the rest of the site. Print them, share them in the staff room, or put them on the board.') }}
    </x-page-header>

    <p class="mb-10 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm" style="color: var(--muted);">
        <span class="inline-flex items-center gap-1.5">
            <svg class="h-4 w-4 shrink-0" style="color: var(--accent);" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                <circle cx="10" cy="10" r="7.5" stroke="currentColor" stroke-width="1.5"/>
                <path d="m6.5 10.5 2.3 2.3L13.5 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span>{{ __('Free, with no adverts.') }}</span>
        </span>
        <span aria-hidden="true">·</span>
        <span>{{ __('Pupils never need an account.') }}</span>
        <span aria-hidden="true">·</span>
        <span>{{ __('Location is optional and never needed to browse.') }}</span>
    </p>

    @if (count($handouts) === 0)
        <x-empty-state :title="__('No handouts yet')" />
    @else
        <ul class="grid gap-6 md:grid-cols-2" role="list">
            @foreach ($handouts as $handout)
                <li id="{{ $handout->id }}" class="surface flex flex-col overflow-hidden">
                    <div class="grid gap-6 p-5 sm:grid-cols-[9rem_1fr] sm:p-6">
                        <a href="{{ $handout->url() }}" download="{{ $handout->filename() }}"
                           class="block overflow-hidden rounded-lg border"
                           style="border-color: var(--border); background-color: var(--bg-elevated-2);"
                           aria-label="{{ __('Download :title (PDF)', ['title' => $handout->title]) }}">
                            <img src="{{ $handout->thumbnailUrl() }}" width="640" height="905"
                                 alt="{{ __('Front page of :title', ['title' => $handout->title]) }}"
                                 class="h-auto w-full" loading="eager" decoding="async">
                        </a>

                        <div class="flex min-w-0 flex-col">
                            <div><x-badge tone="amber">{{ $handout->keyStage }}</x-badge></div>
                            <h2 class="mt-3 font-serif text-2xl font-medium leading-tight" style="color: var(--text);">{{ $handout->title }}</h2>
                            <p class="mt-2 text-sm leading-relaxed" style="color: var(--muted);">{{ $handout->description }}</p>

                            <div class="mt-auto pt-5">
                                <a href="{{ $handout->url() }}" download="{{ $handout->filename() }}"
                                   type="application/pdf"
                                   class="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold"
                                   style="background-color: var(--accent); color: #07090f;">
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                        <path d="M10 3v9m0 0 3.5-3.5M10 12 6.5 8.5M4 15.5h12" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    <span>{{ __('Download PDF') }} <span class="font-normal opacity-80">({{ $handout->downloadMeta() }})</span></span>
                                </a>
                            </div>
                        </div>
                    </div>

                    @if (count($handout->previewUrls()) > 0)
                        <div class="border-t px-5 py-4 sm:px-6" style="border-color: var(--border);">
                            <p class="mb-3 text-xs font-semibold uppercase tracking-wider" style="color: var(--muted);">{{ __('Inside') }}</p>
                            <ul class="grid grid-cols-3 gap-3" role="list">
                                @foreach ($handout->previewUrls() as $i => $preview)
                                    <li class="overflow-hidden rounded-md border" style="border-color: var(--border); background-color: var(--bg-elevated-2);">
                                        <img src="{{ $preview }}" width="360" height="509"
                                             alt="{{ __('Page :n of :title', ['n' => $i + 2, 'title' => $handout->title]) }}"
                                             class="h-auto w-full" loading="lazy" decoding="async">
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    <div class="mt-12 space-y-4 text-base leading-relaxed" style="max-width: var(--container-prose); color: var(--text);">
        <h2 class="font-serif text-2xl font-medium">{{ __('Using them in class') }}</h2>
        <p style="color: var(--muted);">
            {{ __('Every activity in the handouts points at a page on this site, so a whiteboard or a tablet is all you need. The data behind it is public domain, from NASA, JPL and the IAU Minor Planet Center, so you can reuse it in your own worksheets.') }}
            <a class="underline" style="color: var(--link);" href="{{ route('orrery') }}">{{ __('Open the orrery') }}</a>
            {{ __('to show where the planets are today, or') }}
            <a class="underline" style="color: var(--link);" href="{{ route('planets.index') }}">{{ __('start with the planets') }}</a>.
        </p>

        <h2 class="pt-2 font-serif text-2xl font-medium">{{ __('Tell us what would help') }}</h2>
        <p style="color: var(--muted);">
            {{ __('Used one of these in a lesson? Want a sheet for a different year group or topic? We would love to hear from you:') }}
            <a class="underline" style="color: var(--link);" href="mailto:{{ config('site.contact_email') }}?subject={{ rawurlencode('Solar — classroom handouts') }}">{{ config('site.contact_email') }}</a>.
        </p>
    </div>
</div>

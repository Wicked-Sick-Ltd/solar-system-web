@php($profiles = \App\Support\Social::profiles())
@if ($profiles !== [])
    <ul {{ $attributes->merge(['class' => 'flex flex-wrap gap-2 text-sm']) }} data-testid="social-links">
        @foreach ($profiles as $network => $url)
            @php($label = \App\Support\Social::label($network))
            <li>
                <a href="{{ $url }}" rel="me noopener" target="_blank"
                   aria-label="{{ __('Follow :name on :network (opens in a new tab)', ['name' => config('site.name'), 'network' => $label]) }}"
                   class="inline-flex min-h-11 items-center gap-2 rounded-md border px-3 py-2 transition-colors hover:opacity-80 focus-visible:outline-2 focus-visible:outline-offset-2"
                   style="border-color: var(--border); color: var(--text); outline-color: var(--link);">
                    <svg aria-hidden="true" focusable="false" class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="currentColor">
                        @switch($network)
                            @case('x')
                                <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                                @break
                            @default
                                <path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm1 15h-2v-6h2zm0-8h-2V7h2z"/>
                        @endswitch
                    </svg>
                    <span>{{ __('Follow on :network', ['network' => $label]) }}</span>
                </a>
            </li>
        @endforeach
    </ul>
@endif

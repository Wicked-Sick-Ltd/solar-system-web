<div class="grid gap-4 sm:grid-cols-[1fr_auto] sm:items-end" id="newsletter">
    <div>
        <h2 class="text-xs font-semibold uppercase tracking-wider" style="color: var(--muted);">{{ __('Newsletter') }}</h2>
        <p class="mt-2 max-w-md text-sm leading-relaxed" style="color: var(--muted);">
            {{ __('Occasional notes when something new lands on the site — new data, new tools, nothing else. Unsubscribe any time.') }}
        </p>
    </div>

    @if ($state === 'pending')
        <p class="text-sm" role="status" style="color: var(--text);">
            <strong style="color: var(--accent);">{{ __('Check your inbox.') }}</strong>
            {{ __('We\'ve sent a confirmation link — nothing happens until you click it.') }}
        </p>
    @elseif ($state === 'subscribed')
        <p class="text-sm" role="status" style="color: var(--text);">{{ __('You\'re already on the list — thank you.') }}</p>
    @else
        <form id="newsletter-form" class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-start" wire:submit="subscribe">
            <div class="sm:w-72">
                <label for="newsletter-email" class="sr-only">{{ __('Email address') }}</label>
                <input id="newsletter-email" type="email" name="email" wire:model="email" required autocomplete="email"
                       inputmode="email" placeholder="{{ __('you@example.com') }}"
                       class="w-full rounded-lg border px-3 py-2 text-sm"
                       style="background-color: var(--bg-elevated); border-color: var(--border); color: var(--text);"
                       @error('email') aria-invalid="true" aria-describedby="newsletter-error" @enderror>
                {{-- Honeypot: off-screen, not tab-reachable, ignored by real people. --}}
                <div aria-hidden="true" style="position:absolute; left:-10000px; top:auto; width:1px; height:1px; overflow:hidden;">
                    <label for="newsletter-website">{{ __('Leave this field empty') }}</label>
                    <input id="newsletter-website" type="text" name="website" wire:model="website" tabindex="-1" autocomplete="off">
                </div>
                @error('email')
                    <p id="newsletter-error" class="mt-1 text-xs" style="color: #ffb4b4;">{{ $message }}</p>
                @enderror
                @if ($state === 'error')
<p role="alert" class="mt-1 text-xs" style="color: #ffb4b4;">{{ __('Sorry — we could not sign you up just now. Please try again later.') }}</p>
                @endif
            </div>
            <button type="submit" class="rounded-lg px-4 py-2 text-sm font-medium" wire:loading.attr="disabled"
                    style="background-color: var(--accent); color: #07090f;">
                <span wire:loading.remove>{{ __('Subscribe') }}</span>
                <span wire:loading>{{ __('Sending…') }}</span>
            </button>
        </form>
        <p class="text-xs sm:col-span-2" style="color: var(--color-faint);">
            {{ __('Double opt-in via Mailchimp. See our') }} <a class="link-quiet underline" href="{{ route('privacy') }}">{{ __('privacy policy') }}</a>.
        </p>
    @endif
</div>

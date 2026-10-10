<x-layouts.app>
    <div class="mx-auto max-w-2xl">
        <x-page-header :title="__('Feedback & contact')" :eyebrow="__('Help shape Public Universe')"
            :lead="__('Suggest a feature, tell us about a problem, or simply say hello. Your message goes privately to our team.')" />

        @if (session('feedback_sent'))
            <p class="surface mb-6 p-5" role="status">{{ __('Thank you — your message has been sent to our inbox. If you included an email address, we can reply there.') }}</p>
        @endif
        @if ($errors->any())
            <div class="surface mb-6 border p-5" role="alert" tabindex="-1" id="feedback-errors">
                <h2 class="font-semibold">{{ __('Please check your message') }}</h2>
                <ul class="mt-2 list-disc pl-5">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif
        @unless ($canSend)
            <p class="surface mb-6 p-5">{{ __('The form is temporarily unavailable. You can still contact us by email:') }} <a class="underline" href="mailto:hello@publicuniverse.net">hello@publicuniverse.net</a>.</p>
        @endunless

        <form class="surface space-y-6 p-5 sm:p-7" method="POST" action="{{ route('feedback.store') }}">
            @csrf
            <div>
                <label for="category" class="block font-semibold">{{ __('What is your message about?') }} <span class="text-sm font-normal">{{ __('(required)') }}</span></label>
                <select id="category" name="category" required class="mt-2 w-full rounded-lg border p-3" style="border-color: var(--border); background: var(--bg-elevated); color: var(--text);" @error('category') aria-invalid="true" aria-describedby="category-error" @enderror>
                    <option value="">{{ __('Choose a topic') }}</option>
                    @foreach (config('feedback.categories') as $value => $label)
                        <option value="{{ $value }}" @selected(old('category') === $value)>{{ __($label) }}</option>
                    @endforeach
                </select>
                @error('category')<p id="category-error" class="mt-2 text-sm">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="message" class="block font-semibold">{{ __('Your message') }} <span class="text-sm font-normal">{{ __('(required)') }}</span></label>
                <p id="message-help" class="mt-1 text-sm" style="color: var(--muted);">{{ __('10–5,000 characters. For a bug, describe what you tried, what happened and which page you were using. Please leave out passwords, precise locations and other private information.') }}</p>
                <textarea id="message" name="message" rows="8" minlength="10" maxlength="5000" required aria-describedby="message-help @error('message') message-error @enderror" @error('message') aria-invalid="true" @enderror class="mt-2 w-full rounded-lg border p-3" style="border-color: var(--border); background: var(--bg-elevated); color: var(--text);">{{ old('message') }}</textarea>
                @error('message')<p id="message-error" class="mt-2 text-sm">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="email" class="block font-semibold">{{ __('Email address') }} <span class="text-sm font-normal">{{ __('(optional)') }}</span></label>
                <p id="email-help" class="mt-1 text-sm" style="color: var(--muted);">{{ __('Include this only if you would like a reply. You do not need an account.') }}</p>
                <input id="email" name="email" type="email" autocomplete="email" maxlength="254" value="{{ old('email') }}" aria-describedby="email-help @error('email') email-error @enderror" @error('email') aria-invalid="true" @enderror class="mt-2 w-full rounded-lg border p-3" style="border-color: var(--border); background: var(--bg-elevated); color: var(--text);">
                @error('email')<p id="email-error" class="mt-2 text-sm">{{ $message }}</p>@enderror
            </div>
            <div hidden aria-hidden="true">
                <label for="website">{{ __('Leave this field empty') }}</label>
                <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
            </div>
            <p class="text-sm" style="color: var(--muted);">{{ __('Messages are sent to hello@publicuniverse.net, not posted publicly. We use the details you provide to handle your enquiry. We do not add you to a mailing list.') }} <a class="underline" href="{{ route('privacy') }}#feedback">{{ __('How we handle feedback') }}</a></p>
            <button type="submit" @disabled(! $canSend) class="rounded-lg px-5 py-3 font-semibold disabled:opacity-50" style="background-color: var(--accent-fill); color: var(--on-accent);">{{ __('Send message') }}</button>
        </form>
        <p class="mt-5 text-sm" style="color: var(--muted);">{{ __('Prefer email?') }} <a class="underline" href="mailto:hello@publicuniverse.net">hello@publicuniverse.net</a></p>
    </div>
</x-layouts.app>

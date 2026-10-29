<x-layouts.app>
    <div class="mx-auto max-w-2xl">
        <x-page-header :title="__('Request API access')" :eyebrow="__('For bulk and automated use')"
            :lead="__('Tell us who you are and what you need. Academic email addresses are flagged for the free institution tier. We review the request before a key is issued.')" />

        @if (session('api_access_sent'))
            <p class="surface mb-6 p-5" role="status">
                {{ __('Thank you. Your request is stored and the team has been notified when those notifications are configured.') }}
                @if (session('api_access_institution'))
                    {{ __('This address looks academic, so the request is flagged as eligible for the free institution tier.') }}
                @else
                    {{ __('This address was not flagged as academic. You can still ask for a free key, or write from an academic address if the institution tier is the one you need.') }}
                @endif
            </p>
        @endif
        @if ($errors->any())
            <div class="surface mb-6 border p-5" role="alert" tabindex="-1" id="api-access-errors">
                <h2 class="font-semibold">{{ __('Please check the form') }}</h2>
                <ul class="mt-2 list-disc pl-5">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form class="surface space-y-6 p-5 sm:p-7" method="POST" action="{{ route('api-access.store') }}">
            @csrf
            <div>
                <label for="organisation" class="block font-semibold">{{ __('Organisation') }} <span class="text-sm font-normal">{{ __('(required)') }}</span></label>
                <input id="organisation" name="organisation" type="text" required maxlength="200" autocomplete="organization" value="{{ old('organisation') }}" @error('organisation') aria-invalid="true" @enderror class="mt-2 w-full rounded-lg border p-3" style="border-color: var(--border); background: var(--bg-elevated); color: var(--text);">
                @error('organisation')<p class="mt-2 text-sm">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="contact_name" class="block font-semibold">{{ __('Contact name') }} <span class="text-sm font-normal">{{ __('(required)') }}</span></label>
                <input id="contact_name" name="contact_name" type="text" required maxlength="200" autocomplete="name" value="{{ old('contact_name') }}" @error('contact_name') aria-invalid="true" @enderror class="mt-2 w-full rounded-lg border p-3" style="border-color: var(--border); background: var(--bg-elevated); color: var(--text);">
                @error('contact_name')<p class="mt-2 text-sm">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="email" class="block font-semibold">{{ __('Academic or work email') }} <span class="text-sm font-normal">{{ __('(required)') }}</span></label>
                <p id="email-help" class="mt-1 text-sm" style="color: var(--muted);">{{ __('Addresses at academic domains such as .ac.uk and .edu are flagged as eligible for the free institution tier.') }}</p>
                <input id="email" name="email" type="email" required maxlength="254" autocomplete="email" value="{{ old('email') }}" aria-describedby="email-help" @error('email') aria-invalid="true" @enderror class="mt-2 w-full rounded-lg border p-3" style="border-color: var(--border); background: var(--bg-elevated); color: var(--text);">
                @error('email')<p class="mt-2 text-sm">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="use_case" class="block font-semibold">{{ __('Use case') }} <span class="text-sm font-normal">{{ __('(required)') }}</span></label>
                <p id="use-case-help" class="mt-1 text-sm" style="color: var(--muted);">{{ __('What you will build or study, and whether the calls are interactive or automated. 20–5,000 characters.') }}</p>
                <textarea id="use_case" name="use_case" rows="6" required minlength="20" maxlength="5000" aria-describedby="use-case-help" class="mt-2 w-full rounded-lg border p-3" style="border-color: var(--border); background: var(--bg-elevated); color: var(--text);">{{ old('use_case') }}</textarea>
                @error('use_case')<p class="mt-2 text-sm">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="expected_volume" class="block font-semibold">{{ __('Expected volume') }} <span class="text-sm font-normal">{{ __('(required)') }}</span></label>
                <select id="expected_volume" name="expected_volume" required class="mt-2 w-full rounded-lg border p-3" style="border-color: var(--border); background: var(--bg-elevated); color: var(--text);">
                    <option value="">{{ __('Choose a range') }}</option>
                    @foreach ($volumes as $value => $label)
                        <option value="{{ $value }}" @selected(old('expected_volume') === $value)>{{ __($label) }}</option>
                    @endforeach
                </select>
                @error('expected_volume')<p class="mt-2 text-sm">{{ $message }}</p>@enderror
            </div>
            <div hidden aria-hidden="true">
                <label for="website">{{ __('Leave this field empty') }}</label>
                <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
            </div>
            <p class="text-sm" style="color: var(--muted);">{{ __('We store this request so we can decide on a key. See') }} <a class="underline" href="{{ route('fair-use') }}">{{ __('fair use') }}</a> {{ __('and') }} <a class="underline" href="{{ route('privacy') }}#api-access">{{ __('how the form is handled') }}</a>.</p>
            <button type="submit" class="rounded-lg px-5 py-3 font-semibold" style="background-color: var(--accent-fill); color: var(--on-accent);">{{ __('Submit request') }}</button>
        </form>
    </div>
</x-layouts.app>

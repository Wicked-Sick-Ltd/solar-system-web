<x-layouts.app>
    <x-page-header :title="__('Create account')"
                   :lead="__('Create an account so we can email you when an object is up after dark from your location.')" />

    <section class="surface mx-auto max-w-lg p-6" aria-labelledby="register-heading">
        <h2 id="register-heading" class="font-serif text-2xl font-medium">{{ __('Get started') }}</h2>

        <form class="mt-5 space-y-4" method="POST" action="{{ route('register.store') }}">
            @csrf

            <div>
                <label for="name" class="block text-sm" style="color: var(--muted);">{{ __('Name') }}</label>
                <input id="name" name="name" type="text" required autocomplete="name" value="{{ old('name') }}"
                       class="mt-1 block w-full rounded-lg border px-3 py-2 text-sm"
                       style="border-color: var(--border); background-color: var(--bg-elevated); color: var(--text);">
                @error('name')
                    <p class="mt-2 text-xs" style="color: #ffb4b4;">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="email" class="block text-sm" style="color: var(--muted);">{{ __('Email') }}</label>
                <input id="email" name="email" type="email" required autocomplete="email" value="{{ old('email') }}"
                       class="mt-1 block w-full rounded-lg border px-3 py-2 text-sm"
                       style="border-color: var(--border); background-color: var(--bg-elevated); color: var(--text);">
                @error('email')
                    <p class="mt-2 text-xs" style="color: #ffb4b4;">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm" style="color: var(--muted);">{{ __('Password') }}</label>
                <input id="password" name="password" type="password" required autocomplete="new-password"
                       class="mt-1 block w-full rounded-lg border px-3 py-2 text-sm"
                       style="border-color: var(--border); background-color: var(--bg-elevated); color: var(--text);">
                @error('password')
                    <p class="mt-2 text-xs" style="color: #ffb4b4;">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm" style="color: var(--muted);">{{ __('Confirm password') }}</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                       class="mt-1 block w-full rounded-lg border px-3 py-2 text-sm"
                       style="border-color: var(--border); background-color: var(--bg-elevated); color: var(--text);">
            </div>

            <button type="submit" class="rounded-lg px-4 py-2 text-sm font-medium"
                    style="background-color: var(--accent); color: #07090f;">
                {{ __('Create account') }}
            </button>
        </form>

        <p class="mt-5 text-sm" style="color: var(--muted);">
            {{ __('Already have an account?') }} <a class="underline" style="color: var(--link);" href="{{ route('login') }}">{{ __('Sign in') }}</a>
        </p>
    </section>
</x-layouts.app>

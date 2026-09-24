<x-layouts.app>
    <x-page-header :title="__('Sign in')"
                   :lead="__('Sign in to manage your object visibility alerts.')" />

    <section class="surface mx-auto max-w-lg p-6" aria-labelledby="login-heading">
        <h2 id="login-heading" class="font-serif text-2xl font-medium">{{ __('Welcome back') }}</h2>

        <form class="mt-5 space-y-4" method="POST" action="{{ route('login.store') }}">
            @csrf

            <div>
                <label for="email" class="block text-sm" style="color: var(--muted);">{{ __('Email') }}</label>
                <input id="email" name="email" type="email" required autofocus autocomplete="email" value="{{ old('email') }}"
                       class="mt-1 block w-full rounded-lg border px-3 py-2 text-sm"
                       style="border-color: var(--border); background-color: var(--bg-elevated); color: var(--text);">
                @error('email')
                    <p class="mt-2 text-xs" style="color: #ffb4b4;">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm" style="color: var(--muted);">{{ __('Password') }}</label>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                       class="mt-1 block w-full rounded-lg border px-3 py-2 text-sm"
                       style="border-color: var(--border); background-color: var(--bg-elevated); color: var(--text);">
            </div>

            <label class="inline-flex items-center gap-2 text-sm" style="color: var(--muted);">
                <input type="checkbox" name="remember" value="1">
                <span>{{ __('Remember me') }}</span>
            </label>

            <button type="submit" class="rounded-lg px-4 py-2 text-sm font-medium"
                    style="background-color: var(--accent); color: #07090f;">
                {{ __('Sign in') }}
            </button>
        </form>

        <p class="mt-5 text-sm" style="color: var(--muted);">
            {{ __('New here?') }} <a class="underline" style="color: var(--link);" href="{{ route('register') }}">{{ __('Create an account') }}</a>
        </p>
    </section>
</x-layouts.app>

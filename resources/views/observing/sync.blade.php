<x-layouts.app>
    <div data-observing-sync data-account-scope="{{ $accountScope }}" data-endpoint="{{ route('observing.sync.show') }}" data-csrf="{{ csrf_token() }}" class="mx-auto max-w-3xl space-y-6">
        <x-page-header :title="__('Private observing backup')" :lead="__('Choose when to copy your equipment, sites, lists and journal between this browser and your account.')" />
        <p>{{ __('Signed in as') }} <strong>{{ $accountEmail }}</strong></p>
        <p>{{ __('Signing in never uploads your browser records. Account previews stay in memory on this page; leaving it clears them. Your browser’s guest records remain readable by anyone using this browser, including after signing out.') }}</p>
        <p>{{ __('The account copy is private to your account and encrypted in the service database. The service can decrypt it to return your backup. This is not end-to-end encryption.') }}</p>
        <p role="status" data-sync-status></p><p role="alert" data-sync-error></p>
        <noscript><p>{{ __('Synchronization requires JavaScript. Nothing is transferred automatically.') }}</p></noscript>
        <section class="surface space-y-4 p-5" aria-labelledby="sync-browser-heading">
            <h2 id="sync-browser-heading" class="text-2xl">{{ __('This browser') }}</h2>
            <p data-sync-guest>{{ __('No browser preview loaded.') }}</p>
            <button data-sync-preview type="button" disabled class="min-h-11 rounded border px-4">{{ __('Preview this browser’s data') }}</button>
            <p><a class="underline" href="{{ route('observatory') }}">{{ __('Equipment and site backups') }}</a> · <a class="underline" href="{{ route('observing.journal') }}">{{ __('Journal backups') }}</a></p>
        </section>
        <section class="surface space-y-4 p-5" aria-labelledby="sync-account-heading">
            <h2 id="sync-account-heading" class="text-2xl">{{ __('Your account copy') }}</h2>
            <p data-sync-account>{{ __('No account preview loaded.') }}</p>
            <button data-sync-check type="button" disabled class="min-h-11 rounded border px-4">{{ __('Check my account copy') }}</button>
            <p>{{ __('A revision identifies a saved account copy. If another device changes it, the transfer stops so you can check again and choose what to keep.') }}</p>
        </section>
        <section class="surface space-y-4 p-5" aria-labelledby="sync-upload-heading">
            <h2 id="sync-upload-heading" class="text-2xl">{{ __('Browser → account') }}</h2>
            <p>{{ __('Uploads replace the whole account copy with the browser preview, including private site names, coordinates and notes. Later edits are not uploaded automatically.') }}</p>
            <label class="flex min-h-11 items-start gap-3"><input data-sync-upload-consent type="checkbox" class="mt-1">{{ __('I want to upload this preview, including its private sites and notes.') }}</label>
            <button data-sync-upload type="button" disabled class="min-h-11 rounded border px-4">{{ __('Upload this preview to my account') }}</button>
        </section>
        <section class="surface space-y-4 p-5" aria-labelledby="sync-restore-heading">
            <h2 id="sync-restore-heading" class="text-2xl">{{ __('Account → browser') }}</h2>
            <p>{{ __('Export this browser’s equipment and journal first if you want to keep them. Replacing them copies the account preview into the shared guest records on this browser. It does not activate an observing site or change the account copy.') }}</p>
            <label class="flex min-h-11 items-start gap-3"><input data-sync-restore-consent type="checkbox" class="mt-1">{{ __('I want to replace this browser’s equipment, sites, lists and journal with the account preview.') }}</label>
            <button data-sync-restore type="button" disabled class="min-h-11 rounded border px-4">{{ __('Replace this browser with the account preview') }}</button>
        </section>
        <section class="surface space-y-4 p-5" aria-labelledby="sync-delete-heading">
            <h2 id="sync-delete-heading" class="text-2xl">{{ __('Delete the account copy') }}</h2>
            <p>{{ __('This removes the saved account payload. A revision marker remains to prevent older devices from silently recreating it. Browser guest records are unchanged. Export or restore a copy first if you need a backup.') }}</p>
            <label class="flex min-h-11 items-start gap-3"><input data-sync-delete-consent type="checkbox" class="mt-1">{{ __('I want to delete my account copy.') }}</label>
            <button data-sync-delete type="button" disabled class="min-h-11 rounded border px-4">{{ __('Delete account copy') }}</button>
        </section>
    </div>
    @vite('resources/js/observing/sync.js')
</x-layouts.app>

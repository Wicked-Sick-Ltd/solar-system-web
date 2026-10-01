<div data-observing-journal class="mx-auto max-w-4xl space-y-8">
    <x-page-header :title="__('Observing lists and journal')" :eyebrow="__('Your nights outside')" :lead="__('Plan what to look for and record what you actually observed. These records stay in this browser, without an account.')" />
    <p>{{ __('Signing in does not upload this journal. Anyone using this browser can read it. Export a private backup before clearing browser storage or moving to another domain.') }}</p>
    <p><a class="underline" href="{{ route('observing.sync.page') }}">{{ __('Optional private account backup') }}</a></p>
    <p role="status" data-journal-status></p><p role="alert" data-journal-error></p>
    <noscript><p>{{ __('This private journal needs JavaScript. Catalogue browsing and the night planner remain available without it.') }}</p></noscript>
    <div class="flex flex-wrap gap-3 print:hidden"><button type="button" data-journal-reload class="min-h-11 rounded border px-4">{{ __('Reload journal and equipment') }}</button><button type="button" data-journal-undo hidden class="min-h-11 rounded border px-4">{{ __('Undo last removal or import') }}</button></div>
    <section aria-labelledby="journal-lists-heading" class="space-y-4">
        <h2 id="journal-lists-heading" class="text-2xl">{{ __('Observing lists') }}</h2>
        <p>{{ __('Lists preserve exact catalogue identifiers even if a target becomes unavailable. A saved target or “observed” list status does not prove visibility or create a journal observation.') }}</p>
        <p data-journal-empty-lists hidden>{{ __('No lists saved yet.') }}</p><div data-journal-lists class="space-y-4"></div>
        <form data-journal-list-form class="surface p-5 print:hidden"><fieldset data-journal-controls disabled class="space-y-3"><legend>{{ __('Create a list') }}</legend>
            <label class="block">{{ __('List name') }}<input name="listName" maxlength="100" required class="mt-2 w-full rounded border p-3" style="background: var(--bg-elevated)"></label>
            <button class="min-h-11 rounded border px-4" type="submit">{{ __('Create list') }}</button>
        </fieldset></form>
        <form data-journal-target-form class="surface p-5 print:hidden"><fieldset data-journal-controls disabled class="space-y-4"><legend>{{ __('Add a target to a list') }}</legend>
            <label class="block">{{ __('Choose list') }}<select name="listId" required class="mt-2 w-full rounded border p-3" style="background: var(--bg-elevated)"></select></label>
            @include('observing.journal-target-fields')
            <button class="min-h-11 rounded border px-4" type="submit">{{ __('Add target') }}</button>
        </fieldset></form>
    </section>
    <section aria-labelledby="journal-entries-heading" class="space-y-4">
        <h2 id="journal-entries-heading" class="text-2xl">{{ __('Actual observations') }}</h2>
        <p data-journal-empty-entries hidden>{{ __('No observations recorded yet.') }}</p><div data-journal-entries class="space-y-4"></div>
        <form data-journal-entry-form class="surface p-5 print:hidden"><fieldset data-journal-controls disabled class="space-y-4"><legend>{{ __('Record an observation') }}</legend>
            @include('observing.journal-target-fields')
            <label class="block">{{ __('Actual observation time in UTC') }}<input name="observedAtUtc" maxlength="20" required placeholder="2026-10-01T22:30:00Z" pattern="[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z" class="mt-2 w-full rounded border p-3" style="background: var(--bg-elevated)"></label>
            <p class="text-sm">{{ __('Enter the time you observed, not the time a plan predicted. The final Z means UTC; this avoids ambiguous local clock-change times.') }}</p>
            <label class="block">{{ __('Observing timezone (IANA)') }}<input name="timezone" value="UTC" maxlength="100" required class="mt-2 w-full rounded border p-3" style="background: var(--bg-elevated)"></label>
            <label class="block">{{ __('Outcome') }}<select name="outcome" class="mt-2 w-full rounded border p-3" style="background: var(--bg-elevated)"><option value="seen">{{ __('Seen') }}</option><option value="not_seen">{{ __('Not seen') }}</option><option value="uncertain">{{ __('Uncertain') }}</option></select></label>
            <label class="block">{{ __('Notes') }}<textarea name="notes" rows="4" maxlength="4000" class="mt-2 w-full rounded border p-3" style="background: var(--bg-elevated)"></textarea></label>
            <fieldset><legend>{{ __('Equipment used (optional)') }}</legend><div data-journal-equipment class="flex flex-wrap gap-3"></div></fieldset>
            <label class="block">{{ __('Site snapshot (optional)') }}<select name="siteId" class="mt-2 w-full rounded border p-3" style="background: var(--bg-elevated)"><option value="">{{ __('No site snapshot') }}</option></select></label>
            <p class="text-sm">{{ __('Selected equipment and site specifications are copied into this observation. Later edits to the live profiles do not rewrite your observing history.') }} <a class="underline" href="{{ route('observatory') }}">{{ __('Manage equipment and sites') }}</a></p>
            <button class="min-h-11 rounded border px-4" type="submit">{{ __('Record observation') }}</button>
        </fieldset></form>
    </section>
    <section class="surface space-y-4 p-5 print:hidden" aria-labelledby="journal-backup-heading">
        <h2 id="journal-backup-heading" class="text-2xl">{{ __('Backup, export and import') }}</h2>
        <p>{{ __('JSON contains lists and observations and can be imported again. CSV contains observations for spreadsheets. Site names and coordinates are omitted by default; notes can still contain private information. Choose to include sites only when you want a complete private backup.') }}</p>
        <label class="flex min-h-11 items-center gap-2"><input data-journal-include-locations type="checkbox">{{ __('Include site names and coordinates in exports, and site names when printing') }}</label>
        <div class="flex flex-wrap gap-3"><button type="button" data-journal-json class="min-h-11 rounded border px-4">{{ __('Export journal JSON') }}</button><button type="button" data-journal-csv class="min-h-11 rounded border px-4">{{ __('Export observations CSV') }}</button><button type="button" data-journal-print class="min-h-11 rounded border px-4">{{ __('Print this journal') }}</button></div>
        <label class="block">{{ __('Import journal JSON (up to 1 MiB)') }}<input data-journal-import type="file" accept="application/json,.json" class="mt-2 block max-w-full"></label>
        <div data-journal-import-preview hidden class="space-y-3"><h3 class="text-xl">{{ __('Review replacement before applying') }}</h3><p data-journal-import-description></p><button type="button" data-journal-apply class="min-h-11 rounded border px-4">{{ __('Replace journal with this import') }}</button><button type="button" data-journal-cancel-import class="min-h-11 rounded border px-4">{{ __('Cancel import') }}</button></div>
        <p class="text-sm">{{ __('Files are read locally. This journal supports up to 20 lists, 200 targets per list (2,000 total), and 1,000 observations within 1 MiB. Browser storage can run out sooner; an unsuccessful save is reported.') }}</p>
    </section>
    @vite('resources/js/observing/journal.js')
</div>

<div data-observing-workspace class="mx-auto max-w-4xl">
    <x-page-header :title="__('Your observatory')" :eyebrow="__('Prepare for a night outside')"
        :lead="__('Keep your equipment and observing sites together. Everything here stays in this browser; an account is not needed.')" />

    <noscript><p class="surface mb-6 p-4">{{ __('This workspace needs JavaScript to save data in your browser. You can still explore the catalogue and read the observing guides.') }}</p></noscript>
    <p class="mb-2 text-sm" role="status" aria-live="polite" data-workspace-status></p>
    <p class="mb-6 text-sm" role="alert" id="workspace-error" style="color: var(--error);" data-workspace-error></p>
    <p class="mb-8 text-sm" style="color: var(--muted);">{{ __('Browser storage can be cleared by your browser or anyone using this device. Export a backup to keep your records. No equipment or named sites are uploaded, including when you are signed in.') }}</p>

    <section class="surface mb-8 p-5 sm:p-6" aria-labelledby="equipment-heading">
        <h2 id="equipment-heading" class="font-serif text-2xl">{{ __('Your equipment') }}</h2>
        <p class="mt-2 text-sm" style="color: var(--muted);">{{ __('Use the specifications printed on your equipment. Unknown optional measurements can stay blank. Saving equipment does not predict what you will be able to see.') }}</p>
        <p class="mt-4 text-sm" data-workspace-equipment-empty>{{ __('No equipment saved yet. Add a telescope, binoculars, eyepiece or optical accessory below.') }}</p>
        <ul class="mt-4 space-y-3" data-workspace-equipment-list aria-label="{{ __('Saved equipment') }}"></ul>
        <form data-workspace-equipment-form aria-describedby="workspace-error" class="mt-6">
            <fieldset disabled data-workspace-enabled>
                <legend class="mb-3 text-lg font-medium">{{ __('Add or edit equipment') }}</legend>
                <input type="hidden" name="entryId">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm">{{ __('Name') }}
                        <input name="name" required maxlength="100" autocomplete="off" class="mt-1 block min-h-11 w-full rounded-lg border px-3 py-2" style="background: var(--bg); border-color: var(--border); color: var(--text);" placeholder="{{ __('My backyard telescope') }}">
                    </label>
                    <label class="block text-sm">{{ __('Type') }}
                        <select name="kind" class="mt-1 block min-h-11 w-full rounded-lg border px-3 py-2" style="background: var(--bg); border-color: var(--border); color: var(--text);">
                            <option value="telescope">{{ __('Telescope') }}</option>
                            <option value="binocular">{{ __('Binoculars') }}</option>
                            <option value="eyepiece">{{ __('Eyepiece') }}</option>
                            <option value="barlow">{{ __('Barlow lens') }}</option>
                            <option value="reducer">{{ __('Focal reducer') }}</option>
                        </select>
                    </label>
                    @foreach ([
                        ['apertureMm', 'Aperture (mm)', 'telescope binocular', 1, 10000, true],
                        ['focalLengthMm', 'Focal length (mm)', 'telescope eyepiece', 0.1, 100000, true],
                        ['magnification', 'Magnification (×)', 'binocular', 0.1, 1000, true],
                        ['apparentFovDeg', 'Apparent field (degrees, optional)', 'eyepiece', 0.1, 180, false],
                        ['fieldStopMm', 'Field stop (mm, optional)', 'eyepiece', 0.1, 500, false],
                        ['factor', 'Optical factor (×)', 'barlow reducer', 0.01, 20, true],
                    ] as [$key, $label, $kinds, $min, $max, $required])
                        <label class="block text-sm" data-kinds="{{ $kinds }}">{{ __($label) }}
                            <input type="number" name="{{ $key }}" step="any" min="{{ $min }}" max="{{ $max }}" @required($required)
                                class="mt-1 block min-h-11 w-full rounded-lg border px-3 py-2" style="background: var(--bg); border-color: var(--border); color: var(--text);">
                        </label>
                    @endforeach
                </div>
                <p class="mt-3 text-xs" style="color: var(--muted);">{{ __('A Barlow factor is 1 or greater; a reducer factor is between 0.01 and 1. The actual factor can depend on spacing and the optical setup.') }}</p>
                <div class="mt-4 flex flex-wrap gap-3">
                    <button type="submit" class="min-h-11 rounded-lg px-4 py-2 text-sm font-medium" style="background: var(--accent); color: #07090f;">{{ __('Save equipment') }}</button>
                    <button type="button" data-workspace-action="cancel-equipment" class="min-h-11 rounded-lg border px-4 py-2 text-sm" style="border-color: var(--border);">{{ __('Cancel editing') }}</button>
                </div>
            </fieldset>
        </form>
    </section>

    <section class="surface mb-8 p-5 sm:p-6" aria-labelledby="sites-heading">
        <h2 id="sites-heading" class="font-serif text-2xl">{{ __('Your observing sites') }}</h2>
        <p class="mt-2 text-sm" style="color: var(--muted);">{{ __('Coordinates are rounded to two decimal places, about a kilometre. Choose Use on a saved site to make it the location used by sky calculations. Those calculations send approximate coordinates to our astronomy API and weather service as explained on the privacy page.') }}</p>
        <p class="mt-2 text-sm" style="color: var(--muted);">{{ __('Timezone and minimum altitude are saved preferences for future planning tools. Current sky calculations do not apply them. A minimum altitude is not a measured horizon or a guarantee of a clear view.') }}</p>
        <p class="mt-4 text-sm" data-workspace-sites-empty>{{ __('No observing sites saved yet.') }}</p>
        <ul class="mt-4 space-y-3" data-workspace-sites-list aria-label="{{ __('Saved observing sites') }}"></ul>
        <form data-workspace-site-form aria-describedby="workspace-error" class="mt-6">
            <fieldset disabled data-workspace-enabled>
                <legend class="mb-3 text-lg font-medium">{{ __('Add or edit a site') }}</legend>
                <input type="hidden" name="entryId">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm">{{ __('Site name') }}
                        <input name="name" required maxlength="100" autocomplete="off" class="mt-1 block min-h-11 w-full rounded-lg border px-3 py-2" style="background: var(--bg); border-color: var(--border); color: var(--text);" placeholder="{{ __('A favourite dark-sky spot') }}">
                    </label>
                    <label class="block text-sm">{{ __('IANA timezone') }}
                        <input name="timezone" required maxlength="100" value="UTC" autocomplete="off" aria-describedby="workspace-timezone-help" class="mt-1 block min-h-11 w-full rounded-lg border px-3 py-2" style="background: var(--bg); border-color: var(--border); color: var(--text);">
                    </label>
                    <label class="block text-sm">{{ __('Latitude (−90 to 90°)') }}
                        <input type="number" name="latitude" required step="any" min="-90" max="90" class="mt-1 block min-h-11 w-full rounded-lg border px-3 py-2" style="background: var(--bg); border-color: var(--border); color: var(--text);">
                    </label>
                    <label class="block text-sm">{{ __('Longitude (−180 to 180°)') }}
                        <input type="number" name="longitude" required step="any" min="-180" max="180" class="mt-1 block min-h-11 w-full rounded-lg border px-3 py-2" style="background: var(--bg); border-color: var(--border); color: var(--text);">
                    </label>
                    <label class="block text-sm">{{ __('Preferred minimum altitude (degrees)') }}
                        <input type="number" name="minAltitudeDeg" required step="any" min="0" max="90" value="20" class="mt-1 block min-h-11 w-full rounded-lg border px-3 py-2" style="background: var(--bg); border-color: var(--border); color: var(--text);">
                    </label>
                </div>
                <p class="mt-3 text-xs" id="workspace-timezone-help" style="color: var(--muted);">{{ __('Use a timezone such as Europe/London, America/New_York, Australia/Sydney or UTC. Site names and coordinates are private records: include them in a backup only if you are comfortable storing that file.') }}</p>
                <div class="mt-4 flex flex-wrap gap-3">
                    <button type="submit" class="min-h-11 rounded-lg px-4 py-2 text-sm font-medium" style="background: var(--accent); color: #07090f;">{{ __('Save site') }}</button>
                    <button type="button" data-workspace-action="cancel-site" class="min-h-11 rounded-lg border px-4 py-2 text-sm" style="border-color: var(--border);">{{ __('Cancel editing') }}</button>
                </div>
            </fieldset>
        </form>
    </section>

    <section class="surface p-5 sm:p-6" aria-labelledby="workspace-backup-heading">
        <h2 id="workspace-backup-heading" class="font-serif text-2xl">{{ __('Back up or move your workspace') }}</h2>
        <p class="mt-2 text-sm" style="color: var(--muted);">{{ __('Export a JSON file, then import it on another browser or after the domain move. Files are read on this device, never uploaded. Imports replace equipment and named sites only after your confirmation. Each workspace supports up to 100 equipment entries and 100 sites, within 128 KiB.') }}</p>
        <div class="mt-4 flex flex-wrap gap-3">
            <button type="button" disabled data-workspace-enabled data-workspace-action="export" class="min-h-11 rounded-lg border px-4 py-2 text-sm" style="border-color: var(--border);">{{ __('Export workspace') }}</button>
            <button type="button" disabled data-workspace-enabled data-workspace-action="reload" class="min-h-11 rounded-lg border px-4 py-2 text-sm" style="border-color: var(--border);">{{ __('Reload saved workspace') }}</button>
        </div>
        <label class="mt-5 block text-sm">{{ __('Import a workspace JSON file') }}
            <input type="file" accept="application/json,.json" disabled data-workspace-enabled data-workspace-import class="mt-2 block min-h-11 w-full max-w-full text-sm">
        </label>
        <div hidden data-workspace-preview class="mt-5 rounded-lg border p-4" style="border-color: var(--accent);" aria-labelledby="workspace-preview-heading">
            <h3 id="workspace-preview-heading" class="text-lg font-medium">{{ __('Review before replacing') }}</h3>
            <p data-workspace-preview-summary class="mt-2 text-sm"></p>
            <pre data-workspace-preview-details class="mt-3 whitespace-pre-wrap break-words font-sans text-sm"></pre>
            <div class="mt-4 flex flex-wrap gap-3">
                <button type="button" disabled data-workspace-enabled data-workspace-action="apply-import" class="min-h-11 rounded-lg px-4 py-2 text-sm font-medium" style="background: var(--accent); color: #07090f;">{{ __('Replace workspace with this file') }}</button>
                <button type="button" disabled data-workspace-enabled data-workspace-action="cancel-import" class="min-h-11 rounded-lg border px-4 py-2 text-sm" style="border-color: var(--border);">{{ __('Cancel import') }}</button>
            </div>
        </div>
        <details class="mt-6">
            <summary class="cursor-pointer py-3 text-sm">{{ __('Storage recovery and removal') }}</summary>
            <p class="mt-2 text-sm" style="color: var(--muted);">{{ __('If a saved workspace cannot be read, download its original data before resetting it. Removing or replacing this workspace keeps the independent observing location in Your settings. Use Forget my location there to remove it too.') }}</p>
            <div class="mt-3 flex flex-wrap gap-3">
                <button type="button" disabled data-workspace-enabled data-workspace-action="raw-backup" class="min-h-11 rounded-lg border px-4 py-2 text-sm" style="border-color: var(--border);">{{ __('Download original stored data') }}</button>
                <button type="button" disabled data-workspace-enabled data-workspace-action="reset" class="min-h-11 rounded-lg border px-4 py-2 text-sm" style="border-color: var(--error); color: var(--error);">{{ __('Remove this workspace') }}</button>
            </div>
        </details>
    </section>
</div>

@vite('resources/js/observing/workspace.js')

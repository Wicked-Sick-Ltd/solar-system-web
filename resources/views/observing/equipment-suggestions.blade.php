<section data-equipment-suggestions class="surface my-8 p-5 sm:p-6" aria-labelledby="equipment-suggestions-heading">
    <h2 id="equipment-suggestions-heading" class="font-serif text-2xl">{{ __('Compare eyepieces for this plan') }}</h2>
    <p class="mt-2 text-sm">{{ __('Compare magnification, exit pupil and estimated field width using your saved equipment or a temporary setup. These choices do not change your night plan. Temporary inputs stay on this page and are not saved or sent.') }}</p>
    <p class="mt-2 text-sm">{{ __('Start with lower magnification to locate a target, then compare other choices. Seeing, sky brightness, optical quality, your eye and equipment compatibility can limit what you see; a larger magnification is not automatically better.') }} <a class="underline" href="https://www.celestron.com/blogs/knowledgebase/what-is-magnification-power-as-it-pertains-to-telescopes">{{ __('Manufacturer guidance') }}</a></p>
    <noscript><p class="mt-3">{{ __('The local equipment comparison needs JavaScript. Your night-plan results and numeric tables remain available without it.') }}</p></noscript>
    <p role="status" data-suggestions-storage-error class="mt-3 text-sm"></p>
    <p role="status" data-suggestions-metadata-error class="mt-3 text-sm"></p>
    <fieldset disabled data-suggestions-controls class="mt-5 space-y-4">
        <legend class="sr-only">{{ __('Temporary equipment comparison controls') }}</legend>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm">{{ __('Equipment source') }}
                <select data-suggestions-mode class="mt-1 block min-h-11 w-full rounded-lg border px-3 py-2" style="background: var(--bg); color: var(--text); border-color: var(--border);">
                    <option value="saved">{{ __('Saved in this browser') }}</option><option value="temporary">{{ __('Temporary telescope, not saved') }}</option>
                </select>
            </label>
            <label class="block text-sm">{{ __('Saved telescope or binoculars') }}<select data-suggestions-instrument class="mt-1 block min-h-11 w-full rounded-lg border px-3 py-2" style="background: var(--bg); color: var(--text); border-color: var(--border);"></select></label>
            <label class="block text-sm">{{ __('Barlow or reducer (telescopes only)') }}<select data-suggestions-accessory class="mt-1 block min-h-11 w-full rounded-lg border px-3 py-2" style="background: var(--bg); color: var(--text); border-color: var(--border);"></select></label>
            <label class="block text-sm">{{ __('Order the comparison') }}<select data-suggestions-order class="mt-1 block min-h-11 w-full rounded-lg border px-3 py-2" style="background: var(--bg); color: var(--text); border-color: var(--border);">
                <option value="lowest-power">{{ __('Lowest magnification first') }}</option><option value="widest-field">{{ __('Widest known field first') }}</option><option value="highest-power">{{ __('Highest magnification first') }}</option>
            </select></label>
        </div>
        <button type="button" data-suggestions-reload class="min-h-11 rounded-lg border px-4 py-2">{{ __('Reload saved equipment') }}</button>
        <a href="{{ route('observatory') }}" class="inline-block min-h-11 content-center underline">{{ __('Manage equipment in your observatory') }}</a>
        <fieldset hidden disabled data-suggestions-temporary-inputs class="grid gap-4 sm:grid-cols-2">
            <legend class="mb-2 font-semibold">{{ __('Temporary telescope') }}</legend>
            @foreach (['aperture' => ['Aperture (mm)', 1, 10000], 'focal' => ['Focal length (mm)', 0.1, 100000]] as $key => [$label, $min, $max])
                <label class="block text-sm">{{ __($label) }}<input data-suggestions-{{ $key }} type="number" min="{{ $min }}" max="{{ $max }}" step="any" aria-describedby="equipment-suggestions-error" class="mt-1 block min-h-11 w-full rounded-lg border px-3 py-2" style="background: var(--bg); color: var(--text); border-color: var(--border);"></label>
            @endforeach
        </fieldset>
        <label hidden data-suggestions-binocular-input class="block text-sm">{{ __('Stated binocular true field (degrees, optional)') }}<input data-suggestions-binocular-field type="number" min="0.000001" max="180" step="any" aria-describedby="equipment-suggestions-error" class="mt-1 block min-h-11 w-full rounded-lg border px-3 py-2" style="background: var(--bg); color: var(--text); border-color: var(--border);"></label>
        <label class="flex min-h-11 items-center gap-3"><input type="checkbox" data-suggestions-add-eyepiece> {{ __('Include one temporary eyepiece, not saved') }}</label>
        <fieldset hidden disabled data-suggestions-eyepiece-inputs class="grid gap-4 sm:grid-cols-3">
            <legend class="mb-2 font-semibold">{{ __('Temporary eyepiece') }}</legend>
            @foreach (['eye-focal' => ['Focal length (mm)', 0.1, 100000], 'eye-afov' => ['Apparent field (degrees, optional)', 0.1, 180], 'eye-stop' => ['Effective field stop (mm, optional)', 0.1, 500]] as $key => [$label, $min, $max])
                <label class="block text-sm">{{ __($label) }}<input data-suggestions-{{ $key }} type="number" min="{{ $min }}" max="{{ $max }}" step="any" aria-describedby="equipment-suggestions-error" class="mt-1 block min-h-11 w-full rounded-lg border px-3 py-2" style="background: var(--bg); color: var(--text); border-color: var(--border);"></label>
            @endforeach
        </fieldset>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm">{{ __('Plan target for context') }}<select data-suggestions-target class="mt-1 block min-h-11 w-full rounded-lg border px-3 py-2" style="background: var(--bg); color: var(--text); border-color: var(--border);"></select></label>
            <label class="block text-sm">{{ __('Angular size source') }}<select data-suggestions-size-mode class="mt-1 block min-h-11 w-full rounded-lg border px-3 py-2" style="background: var(--bg); color: var(--text); border-color: var(--border);"><option value="catalogue">{{ __('Catalogue major axis, when reported') }}</option><option value="manual">{{ __('Enter a temporary angular diameter') }}</option></select></label>
        </div>
        <fieldset hidden disabled data-suggestions-size-inputs class="grid gap-4 sm:grid-cols-2">
            <legend class="mb-2 font-semibold">{{ __('User-entered size') }}</legend>
            <label class="block text-sm">{{ __('Angular diameter (arcminutes, optional)') }}<input data-suggestions-diameter type="number" min="0.000001" max="10800" step="any" aria-describedby="equipment-suggestions-error" class="mt-1 block min-h-11 w-full rounded-lg border px-3 py-2" style="background: var(--bg); color: var(--text); border-color: var(--border);"></label>
            <label class="block text-sm">{{ __('Source or measurement note (optional)') }}<input data-suggestions-size-source type="text" maxlength="300" class="mt-1 block min-h-11 w-full rounded-lg border px-3 py-2" style="background: var(--bg); color: var(--text); border-color: var(--border);"></label>
        </fieldset>
    </fieldset>
    <p role="alert" id="equipment-suggestions-error" data-suggestions-error class="mt-3 text-sm" style="color: var(--error);"></p>
    <div data-suggestions-context class="mt-4 space-y-2 text-sm" style="color: var(--muted);"></div>
    <p role="status" aria-atomic="true" data-suggestions-summary class="my-4 text-sm">{{ __('Choose equipment to compare after the local controls load.') }}</p>
    <ol data-suggestions-results class="grid gap-4 md:grid-cols-2"></ol>
    <p class="mt-4 text-xs" style="color: var(--muted);">{{ __('Field-stop estimates use the paraxial approximation. Apparent-field estimates can differ because of distortion. Accessory factors may vary with spacing; vignetting and the eye’s pupil are not modelled. A catalogue major axis is an approximate recorded extent, not an outline visible through the eyepiece. Camera framing remains available in the observatory calculator.') }}</p>
</section>

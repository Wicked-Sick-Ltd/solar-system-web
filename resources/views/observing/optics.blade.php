<section data-workspace-optics class="surface mb-8 p-5 sm:p-6" aria-labelledby="optics-heading">
    <h2 id="optics-heading" tabindex="-1" class="font-serif text-2xl">{{ __('Compare your optical setup') }}</h2>
    <p class="mt-2 text-sm" style="color: var(--muted);">{{ __('Choose equipment saved above. These geometric estimates help you compare magnification and field width; they do not predict brightness, detail or whether a target is observable. Changes here are temporary and stay in this browser.') }}</p>
    <fieldset disabled data-workspace-enabled class="mt-5">
        <legend class="sr-only">{{ __('Optical calculation inputs') }}</legend>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm">{{ __('Calculation mode') }}
                <select data-optics-mode class="mt-1 block min-h-11 w-full rounded-lg border px-3 py-2" style="background: var(--bg); border-color: var(--border); color: var(--text);">
                    <option value="visual">{{ __('Visual: eyepiece or binoculars') }}</option>
                    <option value="camera">{{ __('Camera: sensor projection') }}</option>
                </select>
            </label>
            @foreach (['instrument' => 'Telescope or binoculars', 'eyepiece' => 'Eyepiece (telescopes)', 'accessory' => 'Barlow or reducer (telescopes)'] as $key => $label)
                <label class="block text-sm">{{ __($label) }}
                    <select data-optics-{{ $key }} class="mt-1 block min-h-11 w-full rounded-lg border px-3 py-2" style="background: var(--bg); border-color: var(--border); color: var(--text);"></select>
                </label>
            @endforeach
            <label hidden data-optics-camera-input class="block text-sm">{{ __('Camera sensor') }}
                <select data-optics-camera class="mt-1 block min-h-11 w-full rounded-lg border px-3 py-2" style="background: var(--bg); border-color: var(--border); color: var(--text);"></select>
            </label>
            <label hidden data-optics-binocular-input class="block text-sm">{{ __('Stated binocular true field (degrees, optional)') }}
                <input data-optics-binocular-field type="number" min="0.000001" max="180" step="any" aria-describedby="optics-error" class="mt-1 block min-h-11 w-full rounded-lg border px-3 py-2" style="background: var(--bg); border-color: var(--border); color: var(--text);">
            </label>
            <label class="block text-sm">{{ __('Angular diameter to compare (arcminutes, optional)') }}
                <input data-optics-diameter type="number" min="0.000001" max="10800" step="any" aria-describedby="optics-size-help optics-error" class="mt-1 block min-h-11 w-full rounded-lg border px-3 py-2" style="background: var(--bg); border-color: var(--border); color: var(--text);">
            </label>
        </div>
    </fieldset>
    <p id="optics-size-help" class="mt-3 text-xs" style="color: var(--muted);">{{ __('Enter an angular size from a source or measurement of your choice. One degree is 60 arcminutes. No planet sizes or catalogue measurements are filled in automatically.') }}</p>
    <p role="alert" id="optics-error" data-optics-error class="mt-3 text-sm" style="color: var(--error);"></p>
    <div aria-live="polite" aria-atomic="true" class="mt-5">
        <dl class="grid gap-2 text-sm sm:grid-cols-[12rem_1fr]">
            <dt>{{ __('Effective focal length') }}</dt><dd data-optics-focal>{{ __('Not available') }}</dd>
            <dt>{{ __('Magnification') }}</dt><dd data-optics-magnification>{{ __('Unknown') }}</dd>
            <dt>{{ __('Exit pupil') }}</dt><dd data-optics-pupil>{{ __('Unknown') }}</dd>
            <dt>{{ __('True field of view') }}</dt><dd data-optics-field>{{ __('Unknown') }}</dd>
            <dt>{{ __('Central pixel angular width') }}</dt><dd data-optics-pixel>{{ __('Not applicable to visual mode') }}</dd>
        </dl>
        <p data-optics-method class="mt-3 text-sm" style="color: var(--muted);"></p>
        <p data-optics-comparison class="mt-4 text-sm">{{ __('Enter an angular diameter and a known field of view to compare their sizes.') }}</p>
    </div>
    <div hidden data-optics-diagram class="mx-auto mt-4 max-w-xs">
        <svg viewBox="-100 -100 200 200" role="img" aria-labelledby="optics-diagram-title optics-diagram-description" class="h-auto w-full">
            <title id="optics-diagram-title">{{ __('Relative angular diameters') }}</title>
            <desc id="optics-diagram-description" data-optics-diagram-description></desc>
            <circle data-optics-field-circle cx="0" cy="0" r="80" fill="none" stroke="currentColor" stroke-width="2" />
            <circle data-optics-target-circle cx="0" cy="0" r="0" fill="none" stroke="var(--accent)" stroke-width="2" stroke-dasharray="4 3" />
        </svg>
    </div>
    <div hidden data-optics-camera-diagram class="mx-auto mt-4 max-w-xs">
        <svg viewBox="-100 -100 200 200" role="img" aria-labelledby="camera-diagram-title camera-diagram-description" class="h-auto w-full">
            <title id="camera-diagram-title">{{ __('Camera field and centred angular target') }}</title>
            <desc id="camera-diagram-description" data-optics-camera-description></desc>
            <rect data-optics-sensor-rectangle x="-80" y="-60" width="160" height="120" fill="none" stroke="currentColor" stroke-width="2" />
            <circle data-optics-camera-target cx="0" cy="0" r="0" fill="none" stroke="var(--accent)" stroke-width="2" stroke-dasharray="4 3" />
        </svg>
    </div>
    <p class="mt-4 text-xs" style="color: var(--muted);">{{ __('Camera mode uses sensor dimensions and the exact arctangent expression for ideal rectilinear geometry. Central pixel angular width is the angle across one pixel centred on the optical axis, assuming the entered pixel size applies to both axes. It is not resolving power or an exposure recommendation, and no camera is controlled. Unknown pixel size stays unknown.') }}</p>
    <p class="mt-4 text-xs" style="color: var(--muted);">{{ __('Field-stop estimates use the paraxial approximation. Apparent-field estimates are less reliable when eyepiece distortion matters. Optical factors can vary with spacing; vignetting, optical compatibility and the eye’s pupil can further limit the usable field. The circles compare angles only, not an eyepiece image.') }}</p>
    <details class="mt-4 text-sm">
        <summary class="cursor-pointer py-2">{{ __('Calculation sources') }}</summary>
        <ul class="mt-2 list-disc space-y-2 pl-5">
            <li><a class="underline" href="https://www.edmundoptics.com/knowledge-center/application-notes/imaging/understanding-focal-length-and-field-of-view">{{ __('Edmund Optics: sensor dimensions and angular field') }}</a></li>
            <li><a class="underline" href="https://www.celestron.com/blogs/knowledgebase/what-is-magnification-power-as-it-pertains-to-telescopes">{{ __('Celestron: magnification and Barlow lenses') }}</a></li>
            <li><a class="underline" href="https://astronomics.com/cdn/shop/files/DeLite_9_pkg.pdf?v=17679851986320741285">{{ __('Tele Vue: field stops, magnification and exit pupil (manufacturer instructions, PDF hosted by Astronomics)') }}</a></li>
            <li><a class="underline" href="https://celestron-site-support-files.s3.amazonaws.com/support_files/SkyPortal%20130-90-70%205%20language%20manual.pdf">{{ __('Celestron: approximate true field (manual, PDF)') }}</a></li>
        </ul>
    </details>
</section>

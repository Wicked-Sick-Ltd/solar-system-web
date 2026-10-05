            <fieldset data-night-sites hidden class="space-y-3 rounded border p-4">
                <legend>{{ __('Use a site saved in this browser') }}</legend>
                <label class="block">{{ __('Saved site') }}<select data-night-site-choice class="mt-2 w-full rounded border p-3" style="background: var(--bg-elevated)"></select></label>
                <button data-night-site-apply type="button" class="min-h-11 rounded border px-4">{{ __('Copy this site into the form') }}</button>
                <p>{{ __('This copies its rounded coordinates, timezone, minimum altitude and any horizon profile. Review them before calculating. It does not activate the site or save form changes.') }}</p>
                <p data-night-site-status role="status"></p>
            </fieldset>
            <p data-night-site-error role="alert"></p>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach (['date' => ['Night starting', 'date', null, null], 'timezone' => ['IANA time zone (for example Europe/London)', 'text', null, null], 'lat' => ['Latitude, degrees north', 'number', -90, 90], 'lon' => ['Longitude, degrees east', 'number', -180, 180], 'min_altitude_deg' => ['Minimum altitude, degrees', 'number', 0, 90], 'min_moon_separation_deg' => ['Minimum Moon separation, degrees', 'number', 0, 180]] as $field => [$label, $type, $min, $max])
                    <div>
                        <label class="block text-sm" for="night-{{ $field }}">{{ __($label) }}</label>
                        <input class="mt-2 w-full rounded border p-3" style="background: var(--bg-elevated); border-color: var(--border);" id="night-{{ $field }}" name="{{ $field }}" type="{{ $type }}" value="{{ $input[$field] }}" required @if($type === 'number') step="0.01" min="{{ $min }}" max="{{ $max }}" @endif @if($field === 'timezone') maxlength="100" @endif aria-invalid="{{ isset($validation[$field]) ? 'true' : 'false' }}" @isset($validation[$field]) aria-describedby="night-error-{{ $field }}" @endisset>
                        @isset($validation[$field])<p id="night-error-{{ $field }}" class="mt-2 text-sm">{{ implode(' ', $validation[$field]) }}</p>@endisset
                    </div>
                @endforeach
                <div>
                    <label class="block text-sm" for="night-darkness">{{ __('Darkness threshold') }}</label>
                    <select id="night-darkness" name="sun_altitude_deg" @isset($validation['sun_altitude_deg']) aria-invalid="true" aria-describedby="night-error-darkness" @endisset class="mt-2 w-full rounded border p-3" style="background: var(--bg-elevated); border-color: var(--border);">
                        @foreach ([-6 => 'Civil twilight (Sun below −6°)', -12 => 'Nautical twilight (Sun below −12°)', -18 => 'Astronomical darkness (Sun below −18°)'] as $angle => $label)<option value="{{ $angle }}" @selected($input['sun_altitude_deg'] == $angle)>{{ __($label) }}</option>@endforeach
                    </select>
                    @isset($validation['sun_altitude_deg'])<p id="night-error-darkness" class="mt-2 text-sm">{{ implode(' ', $validation['sun_altitude_deg']) }}</p>@endisset
                </div>
            </div>
            <fieldset class="space-y-3 rounded border p-4">
                <legend>{{ __('Observing hours (optional)') }}</legend>
                <p>{{ __('Leave both times blank to consider the whole local night. Otherwise enter UTC start and end times within that night; UTC avoids ambiguous clock-change hours.') }}</p>
                @foreach (['window_start_utc' => 'Start in UTC', 'window_end_utc' => 'End in UTC'] as $field => $label)
                    <label class="block" for="night-{{ $field }}">{{ __($label) }}</label>
                    <input id="night-{{ $field }}" name="{{ $field }}" value="{{ $input[$field] }}" maxlength="20" placeholder="2026-10-01T20:00:00Z" class="w-full rounded border p-3" style="background: var(--bg-elevated)" aria-invalid="{{ isset($validation[$field]) ? 'true' : 'false' }}" @isset($validation[$field]) aria-describedby="night-error-{{ $field }}" @endisset>
                    @isset($validation[$field])<p id="night-error-{{ $field }}">{{ implode(' ', $validation[$field]) }}</p>@endisset
                @endforeach
            </fieldset>
            <div class="space-y-3">
                <label class="block" for="night-horizon">{{ __('Horizon profile (optional)') }}</label>
                <p>{{ __('Enter 2–72 lines, each with an azimuth and minimum altitude in degrees, separated by a space. North is 0°, east 90°, south 180°, west 270°. Heights interpolate between directions and across north; the larger of this profile and your minimum altitude applies. Blank means terrain is unknown.') }}</p>
                <textarea id="night-horizon" name="horizon" rows="4" maxlength="5000" placeholder="0 10&#10;90 25&#10;180 5&#10;270 15" class="w-full rounded border p-3" style="background: var(--bg-elevated)" aria-invalid="{{ isset($validation['horizon']) ? 'true' : 'false' }}" @isset($validation['horizon']) aria-describedby="night-error-horizon" @endisset>{{ $input['horizon'] }}</textarea>
                @isset($validation['horizon'])<p id="night-error-horizon">{{ implode(' ', $validation['horizon']) }}</p>@endisset
            </div>

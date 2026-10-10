@php
    use App\Support\Format;
    $view = $sky?->observer;
@endphp
<div x-data="skyObserver()">
    @if ($view)
        <p class="text-xs font-semibold uppercase tracking-[0.16em]" style="color: var(--muted);">{{ __('From your location') }}</p>
        <p class="mt-1 font-serif text-2xl font-medium" style="color: {{ $view->isUp && $view->isDark ? 'var(--accent)' : 'var(--text)' }};">{{ $view->status() }}</p>

        <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-3">
            <div>
                <dt class="text-xs uppercase tracking-wide" style="color: var(--muted);">{{ __('Altitude') }}</dt>
                <dd class="font-serif text-xl tabular-nums" style="color: var(--text);">{{ Format::degrees($view->altitudeDeg) ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide" style="color: var(--muted);">{{ __('Azimuth') }}</dt>
                <dd class="font-serif text-xl tabular-nums" style="color: var(--text);">
                    {{ Format::degrees($view->azimuthDeg) ?? '—' }}
                    @if ($view->azimuthDeg !== null)
                        <span class="text-sm" style="color: var(--muted);">{{ Format::bearing($view->azimuthDeg) }}</span>
                    @endif
                </dd>
            </div>
            @if ($view->circumpolar)
                <div class="col-span-2"><p class="text-sm" style="color: var(--text);">{{ __('Circumpolar from here: it never sets.') }}</p></div>
            @elseif (! $view->neverRises)
                <div>
                    <dt class="text-xs uppercase tracking-wide" style="color: var(--muted);">{{ __('Rises') }}</dt>
                    <dd class="font-serif text-xl tabular-nums" style="color: var(--text);"><span x-text="local('{{ $view->riseUtc }}')">{{ $view->riseUtc }}</span><span class="block text-xs" style="color: var(--muted);">{{ $view->riseUtc ?? '—' }}</span></dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide" style="color: var(--muted);">{{ __('Highest') }}</dt>
                    <dd class="font-serif text-xl tabular-nums" style="color: var(--text);"><span x-text="local('{{ $view->transitUtc }}')">{{ $view->transitUtc }}</span><span class="block text-xs" style="color: var(--muted);">{{ $view->transitUtc ?? '—' }}</span></dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide" style="color: var(--muted);">{{ __('Sets') }}</dt>
                    <dd class="font-serif text-xl tabular-nums" style="color: var(--text);"><span x-text="local('{{ $view->setUtc }}')">{{ $view->setUtc }}</span><span class="block text-xs" style="color: var(--muted);">{{ $view->setUtc ?? '—' }}</span></dd>
                </div>
            @endif
            <div>
                <dt class="text-xs uppercase tracking-wide" style="color: var(--muted);">{{ __('Sun') }}</dt>
                <dd class="text-sm" style="color: var(--text);">{{ $view->isDark ? __('Sun below −6° — past civil twilight') : __('Daylight or civil twilight') }}</dd>
            </div>
        </dl>

        @if ($weather)
            <div class="mt-4 rounded-lg border p-3" style="border-color: var(--border); background: color-mix(in srgb, var(--bg-elevated) 88%, transparent);">
                <p class="text-xs font-semibold uppercase tracking-[0.16em]" style="color: var(--muted);">{{ __('Hourly weather forecast') }}</p>
                <dl class="mt-2 grid gap-y-2 text-sm">
                    <div class="flex items-baseline justify-between gap-3">
                        <dt style="color: var(--muted);">{{ __('Forecast time') }}</dt>
                        <dd class="tabular-nums" style="color: var(--text);"><span x-text="local('{{ $weather->bestHourUtc }}')">{{ $weather->bestHourUtc }}</span><span class="block text-xs" style="color: var(--muted);">{{ $weather->bestHourUtc }} UTC</span></dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt style="color: var(--muted);">{{ __('Cloud cover') }}</dt>
                        <dd class="tabular-nums" style="color: var(--text);">{{ $weather->cloudCoverPercent }}% · {{ $weather->verdict }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt style="color: var(--muted);">{{ __('Dew risk') }}</dt>
                        <dd style="color: var(--text);">{{ $weather->dewRisk }}</dd>
                    </div>
                </dl>
                <p class="mt-3 text-xs leading-relaxed" style="color: var(--muted);">{{ __('Weather for the displayed forecast hour, using a modelled transit, rise or set time as a reference when available. Clear skies alone do not establish that this object is above the horizon, that it is dark, or that it can be observed.') }}</p>
            </div>
        @else
            <p class="mt-4 text-sm" style="color: var(--muted);">{{ __('Hourly weather forecast unavailable. The sky calculation does not include weather conditions.') }}</p>
        @endif

        <p class="mt-4 text-xs" style="color: var(--color-faint);">
            {{ __('For :lat, :lon. Times are shown in your local time zone when JavaScript is available; the fallback timestamps are UTC.', ['lat' => number_format((float) $view->lat, 2), 'lon' => number_format((float) $view->lon, 2)]) }}
            <button type="button" class="link-quiet underline" @click="forget()">{{ __('Forget my location') }}</button>
            <a class="link-quiet underline" href="{{ route('settings') }}">{{ __('Your settings') }}</a>
        </p>
        <div class="mt-3">
            @auth
                @if ($alertSaved)
                    <p class="text-sm" style="color: var(--text);">
                        {{ __('Email alert saved for this location.') }}
                        <button type="button" class="link-quiet underline" wire:click="removeAlert">{{ __('Remove alert') }}</button>
                    </p>
                @else
                    <button type="button" class="rounded-lg border px-3 py-1.5 text-sm"
                            style="border-color: var(--border); color: var(--text);"
                            wire:click="saveAlert">
                        {{ __('Tell me when it is up after civil twilight') }}
                    </button>
                @endif
            @else
                <p class="text-sm" style="color: var(--muted);">
                    <a class="link-quiet underline" href="{{ route('login') }}">{{ __('Sign in') }}</a>
                    {{ __('or') }}
                    <a class="link-quiet underline" href="{{ route('register') }}">{{ __('create an account') }}</a>
                    {{ __('to get email alerts for this object.') }}
                </p>
            @endauth
            @error('alert')
                <p class="mt-2 text-xs" style="color: var(--error);">{{ $message }}</p>
            @enderror
        </div>
    @else
        <p class="text-xs font-semibold uppercase tracking-[0.16em]" style="color: var(--muted);">{{ __('From your location') }}</p>
        <p class="mt-2 text-sm leading-relaxed" style="color: var(--text); max-width: 42ch;">
            {{ __('See how high it is right now, whether it is up after civil twilight, and when it rises and sets where you are.') }}
        </p>

        @if ($failed)
            <p class="mt-2 text-sm" style="color: var(--error);">{{ __('Sorry — we couldn\'t work that out just now. Please try again later.') }}</p>
        @endif

        @if ($invalidLocation)
            <p id="observer-location-error" role="alert" class="mt-3 text-sm" style="color: var(--error);">{{ __('Enter a latitude between −90 and 90 and a longitude between −180 and 180. No sky or weather request was made for this location.') }}</p>
        @endif
        <noscript><p class="mt-3 text-sm">{{ __('Your location calculation needs JavaScript. The object’s general sky coordinates remain available above.') }}</p></noscript>
        <div class="mt-4 flex flex-wrap items-center gap-3">
            <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium" style="background-color: var(--accent-fill); color: var(--on-accent);"
                    @click="locate()" :disabled="busy" x-bind:aria-busy="busy">
                <span x-show="!busy">{{ __('Calculate sky positions for my location') }}</span>
                <span x-show="busy" x-cloak>{{ __('Locating…') }}</span>
            </button>
            <button type="button" class="link-quiet text-sm underline" @click="manual = !manual" :aria-expanded="manual">{{ __('or enter a location') }}</button>
        </div>
        <p class="mt-2 text-xs" style="color: var(--color-faint);" x-show="geoError" x-text="geoError" x-cloak></p>

        <form class="mt-3" x-show="manual" x-cloak @submit.prevent="submitText()">
            <label class="block text-xs" style="color: var(--muted);" for="observer-location-text">{{ __('Paste a location') }}</label>
            <div class="mt-1 flex flex-wrap items-stretch gap-2">
                <input id="observer-location-text"
                       @if ($errors->hasAny(['text', 'lat', 'lon']) || $invalidLocation) aria-invalid="true"
                       aria-describedby="{{ $errors->has('text') ? 'observer-text-error ' : '' }}{{ $errors->has('lat') ? 'observer-lat-error ' : '' }}{{ $errors->has('lon') ? 'observer-lon-error ' : '' }}{{ $invalidLocation ? 'observer-location-error' : '' }}" @endif
                       type="text" x-model="text" required autocomplete="off" spellcheck="false"
                       placeholder="{{ $what3words ? __('51.51, -0.13  ·  a Google Maps link  ·  ///filled.count.soap') : __('51.51, -0.13  ·  or a Google Maps link') }}"
                       class="block w-full max-w-md rounded-lg border px-3 py-1.5 text-sm" style="background-color: var(--bg-elevated); border-color: var(--border); color: var(--text);">
                <button type="submit" class="rounded-lg border px-3 py-1.5 text-sm" style="border-color: var(--border); color: var(--text);">{{ __('Use this') }}</button>
            </div>
            @error('text') <p id="observer-text-error" class="mt-2 text-xs" style="color: var(--error);">{{ $message }}</p> @enderror
            @error('lat') <p id="observer-lat-error" class="mt-2 text-xs" style="color: var(--error);">{{ $message }}</p> @enderror
            @error('lon') <p id="observer-lon-error" class="mt-2 text-xs" style="color: var(--error);">{{ $message }}</p> @enderror

            <details class="mt-3 text-xs leading-relaxed" style="color: var(--color-faint); max-width: 52ch;">
                <summary class="cursor-pointer" style="color: var(--muted);">{{ __('How do I find my coordinates?') }}</summary>
                <p class="mt-2 font-medium" style="color: var(--muted);">{{ __('Google Maps on a computer') }}</p>
                <ol class="mt-1 list-decimal space-y-1 pl-5">
                    <li>{{ __('Right-click the spot where you\'ll be observing.') }}</li>
                    <li>{{ __('The first line of the menu is the coordinates, e.g. 51.50722, -0.12758 — click it and they\'re copied.') }}</li>
                    <li>{{ __('Paste them above.') }}</li>
                </ol>
                <p class="mt-2 font-medium" style="color: var(--muted);">{{ __('Google Maps on a phone') }}</p>
                <ol class="mt-1 list-decimal space-y-1 pl-5">
                    <li>{{ __('Press and hold the spot to drop a pin.') }}</li>
                    <li>{{ __('The coordinates appear in the search bar (or on the pin\'s card) — tap to copy, then paste above.') }}</li>
                </ol>
                <p class="mt-2">{{ __('A full Google Maps link works too, but the short maps.app.goo.gl share links don\'t carry coordinates — copy the numbers instead. We round to about a kilometre; that\'s all the sky calculation needs.') }}</p>
                @if ($what3words)
                    <p class="mt-2">{{ __('Know your what3words address? Paste it, e.g. ///filled.count.soap. This is optional — the three words are sent to what3words to convert them, and we don\'t store the address or the result.') }}</p>
                @endif
            </details>
        </form>

        <p class="mt-3 text-xs" style="color: var(--color-faint);">
            {{ __('Your location stays in your browser and is sent only for this calculation.') }}
            @if ($what3words)
                {{ __('Using a what3words address is optional; if you do, the three words go to what3words to be converted and we don\'t record them.') }}
            @endif
            {{ __('Set a location first, then you can save an email alert for this object.') }}
            @if (Route::has('privacy'))
                {{ __('See our') }} <a class="link-quiet underline" href="{{ route('privacy') }}">{{ __('privacy policy') }}</a>.
            @endif
        </p>
    @endif
</div>

@script
<script>
    Alpine.data('skyObserver', () => {
        // Keep pending requests outside Alpine's reactive state. A newer intent
        // invalidates callbacks; serialization makes Forget follow an in-flight RPC.
        let active = true, initialized = false, generation = 0, queue = Promise.resolve();
        let navigateHandler, pagehideHandler, pageshowHandler;
        return {
            busy: false, manual: false, geoError: '', text: '',
            KEY: 'observer_location',
            init() {
                if (initialized) return;
                initialized = true;
                navigateHandler = () => this.destroy();
                pagehideHandler = () => { active = false; generation++; this.busy = false; };
                pageshowHandler = event => { if (event.persisted) active = true; };
                document.addEventListener('livewire:navigating', navigateHandler);
                window.addEventListener('pagehide', pagehideHandler);
                window.addEventListener('pageshow', pageshowHandler);
                try {
                    const saved = this.coordinates(JSON.parse(localStorage.getItem(this.KEY) || 'null'));
                    if (saved && @js($lat === null)) {
                        this.request(++generation, () => $wire.setLocation(saved.lat, saved.lon));
                    }
                } catch (e) {}
            },
            destroy() {
                active = false;
                generation++;
                this.busy = false;
                document.removeEventListener('livewire:navigating', navigateHandler);
                window.removeEventListener('pagehide', pagehideHandler);
                window.removeEventListener('pageshow', pageshowHandler);
            },
            coordinates(value) {
                if (!value || typeof value.lat !== 'number' || typeof value.lon !== 'number'
                    || !Number.isFinite(value.lat) || !Number.isFinite(value.lon)
                    || Math.abs(value.lat) > 90 || Math.abs(value.lon) > 180) return null;
                return { lat: Math.round(value.lat * 100) / 100, lon: Math.round(value.lon * 100) / 100 };
            },
            current(intent) { return active && intent === generation; },
            request(intent, action, accepted = () => {}, required = false) {
                queue = queue.then(async () => {
                    if (!active || (!required && !this.current(intent))) return;
                    try {
                        const result = await action();
                        if (this.current(intent)) accepted(result);
                    } catch (e) {
                        if (this.current(intent)) this.geoError = @js(__('Location could not be updated. Please try again.'));
                    } finally {
                        if (this.current(intent)) this.busy = false;
                    }
                });
                return queue;
            },
            remember(value) {
                const coords = this.coordinates(value);
                if (!coords) return;
                try { localStorage.setItem(this.KEY, JSON.stringify(coords)); } catch (e) {}
            },
            locate() {
                if (!active) return;
                const intent = ++generation;
                this.geoError = '';
                this.busy = false;
                const unavailable = () => {
                    if (!this.current(intent)) return;
                    this.busy = false;
                    this.geoError = @js(__('Location not available — enter a location instead.'));
                    this.manual = true;
                };
                if (!navigator.geolocation) { this.geoError = @js(__('Your browser has no location support — enter a location instead.')); this.manual = true; return; }
                this.busy = true;
                try {
                    navigator.geolocation.getCurrentPosition(
                        (pos) => {
                            if (!this.current(intent)) return;
                            const coords = this.coordinates({ lat: pos?.coords?.latitude, lon: pos?.coords?.longitude });
                            if (!coords) { unavailable(); return; }
                            this.request(intent, () => $wire.setLocation(coords.lat, coords.lon), result => this.remember(result));
                        },
                        unavailable,
                        { timeout: 10000, maximumAge: 600000 }
                    );
                } catch (e) { unavailable(); }
            },
            submitText() {
                if (!active) return Promise.resolve();
                const intent = ++generation;
                const text = typeof this.text === 'string' ? this.text.trim() : '';
                this.geoError = '';
                this.busy = false;
                if (!text) return Promise.resolve();
                this.busy = true;
                return this.request(intent, () => $wire.setFromText(text), result => {
                    // Validation errors resolve with null. Never reuse previous wire
                    // coordinates or save a result after the input has been edited.
                    if (typeof this.text === 'string' && this.text.trim() === text) this.remember(result);
                });
            },
            forget() {
                if (!active) return Promise.resolve();
                const intent = ++generation;
                this.busy = false;
                this.geoError = '';
                this.text = '';
                try { localStorage.removeItem(this.KEY); } catch (e) {}
                return this.request(intent, () => $wire.forget(), () => {}, true);
            },
            local(iso) {
                if (!iso) return '—';
                var d = new Date(iso);
                if (isNaN(d)) return iso;
                // Honour the visitor's time-format preference from /settings (auto = device default).
                var opts = { hour: '2-digit', minute: '2-digit' };
                try {
                    var fmt = (JSON.parse(localStorage.getItem('preferences') || '{}') || {}).timeFormat;
                    if (fmt === '12') opts.hour12 = true;
                    if (fmt === '24') opts.hour12 = false;
                } catch (e) {}
                return d.toLocaleTimeString([], opts);
            }
        };
    });
</script>
@endscript

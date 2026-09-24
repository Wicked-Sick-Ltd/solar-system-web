@php
    use App\Support\Format;
    $view = $sky?->observer;
@endphp
<div x-data="skyObserver()" x-init="init()">
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
                    <dd class="font-serif text-xl tabular-nums" style="color: var(--text);"><span x-text="local('{{ $view->riseUtc }}')">{{ $view->riseUtc }}</span></dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide" style="color: var(--muted);">{{ __('Highest') }}</dt>
                    <dd class="font-serif text-xl tabular-nums" style="color: var(--text);"><span x-text="local('{{ $view->transitUtc }}')">{{ $view->transitUtc }}</span></dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide" style="color: var(--muted);">{{ __('Sets') }}</dt>
                    <dd class="font-serif text-xl tabular-nums" style="color: var(--text);"><span x-text="local('{{ $view->setUtc }}')">{{ $view->setUtc }}</span></dd>
                </div>
            @endif
            <div>
                <dt class="text-xs uppercase tracking-wide" style="color: var(--muted);">{{ __('Sun') }}</dt>
                <dd class="text-sm" style="color: var(--text);">{{ $view->isDark ? __('Below the horizon — dark') : __('Up — daylight or twilight') }}</dd>
            </div>
        </dl>

        @if ($weather)
            <div class="mt-4 rounded-lg border p-3" style="border-color: var(--border); background: color-mix(in srgb, var(--bg-elevated) 88%, transparent);">
                <p class="text-xs font-semibold uppercase tracking-[0.16em]" style="color: var(--muted);">{{ __('Tonight\'s outlook') }}</p>
                <dl class="mt-2 grid gap-y-2 text-sm">
                    <div class="flex items-baseline justify-between gap-3">
                        <dt style="color: var(--muted);">{{ __('Best hour') }}</dt>
                        <dd class="tabular-nums" style="color: var(--text);"><span x-text="local('{{ $weather->bestHourUtc }}')">{{ $weather->bestHourUtc }}</span></dd>
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
            </div>
        @endif

        @if ($kitReadyNudge)
            <p class="mt-3 text-sm leading-relaxed" style="color: var(--text);">
                <span class="font-medium">{{ __('Kit-ready nudge:') }}</span>
                <span x-text="localNudge(@js($kitReadyNudge))">{{ $kitReadyNudge }}</span>
            </p>
        @endif

        <p class="mt-4 text-xs" style="color: var(--color-faint);">
            {{ __('For :lat, :lon. Times in your local time zone.', ['lat' => number_format((float) $view->lat, 2), 'lon' => number_format((float) $view->lon, 2)]) }}
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
                        {{ __('Tell me when it is up after dark') }}
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
                <p class="mt-2 text-xs" style="color: #ffb4b4;">{{ $message }}</p>
            @enderror
        </div>
    @else
        <p class="text-xs font-semibold uppercase tracking-[0.16em]" style="color: var(--muted);">{{ __('From your location') }}</p>
        <p class="mt-2 text-sm leading-relaxed" style="color: var(--text); max-width: 42ch;">
            {{ __('See how high it is right now, whether it\'s up after dark, and when it rises and sets where you are.') }}
        </p>

        @if ($failed)
            <p class="mt-2 text-sm" style="color: #ffb4b4;">{{ __('Sorry — we couldn\'t work that out just now. Please try again later.') }}</p>
        @endif

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium" style="background-color: var(--accent); color: #07090f;"
                    @click="locate()" :disabled="busy" x-bind:aria-busy="busy">
                <span x-show="!busy">{{ __('Get precise data for my location') }}</span>
                <span x-show="busy" x-cloak>{{ __('Locating…') }}</span>
            </button>
            <button type="button" class="link-quiet text-sm underline" @click="manual = !manual" :aria-expanded="manual">{{ __('or enter a location') }}</button>
        </div>
        <p class="mt-2 text-xs" style="color: var(--color-faint);" x-show="geoError" x-text="geoError" x-cloak></p>

        <form class="mt-3" x-show="manual" x-cloak @submit.prevent="submitText()">
            <label class="block text-xs" style="color: var(--muted);" for="observer-location-text">{{ __('Paste a location') }}</label>
            <div class="mt-1 flex flex-wrap items-stretch gap-2">
                <input id="observer-location-text" type="text" x-model="text" required autocomplete="off" spellcheck="false"
                       placeholder="{{ $what3words ? __('51.51, -0.13  ·  a Google Maps link  ·  ///filled.count.soap') : __('51.51, -0.13  ·  or a Google Maps link') }}"
                       class="block w-full max-w-md rounded-lg border px-3 py-1.5 text-sm" style="background-color: var(--bg-elevated); border-color: var(--border); color: var(--text);">
                <button type="submit" class="rounded-lg border px-3 py-1.5 text-sm" style="border-color: var(--border); color: var(--text);">{{ __('Use this') }}</button>
            </div>
            @error('text') <p class="mt-2 text-xs" style="color: #ffb4b4;">{{ $message }}</p> @enderror
            @error('lat') <p class="mt-2 text-xs" style="color: #ffb4b4;">{{ $message }}</p> @enderror
            @error('lon') <p class="mt-2 text-xs" style="color: #ffb4b4;">{{ $message }}</p> @enderror

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
    Alpine.data('skyObserver', () => ({
        busy: false, manual: false, geoError: '', text: '',
        KEY: 'observer_location',
        init() {
            try {
                var saved = JSON.parse(localStorage.getItem(this.KEY) || 'null');
                if (saved && typeof saved.lat === 'number' && typeof saved.lon === 'number' && @js($lat === null)) {
                    $wire.setLocation(saved.lat, saved.lon);
                }
            } catch (e) {}
        },
        remember(lat, lon) {
            try { localStorage.setItem(this.KEY, JSON.stringify({ lat: lat, lon: lon })); } catch (e) {}
        },
        locate() {
            this.geoError = '';
            if (!navigator.geolocation) { this.geoError = @js(__('Your browser has no location support — enter a location instead.')); this.manual = true; return; }
            this.busy = true;
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    this.busy = false;
                    var lat = Math.round(pos.coords.latitude * 100) / 100, lon = Math.round(pos.coords.longitude * 100) / 100;
                    this.remember(lat, lon);
                    $wire.setLocation(lat, lon);
                },
                () => { this.busy = false; this.geoError = @js(__('Location not available — enter a location instead.')); this.manual = true; },
                { timeout: 10000, maximumAge: 600000 }
            );
        },
        async submitText() {
            var text = (this.text || '').trim();
            if (!text) return;
            await $wire.setFromText(text);
            // Remember only what the server accepted (already rounded to 2 dp).
            if (typeof $wire.lat === 'number' && typeof $wire.lon === 'number') {
                this.remember($wire.lat, $wire.lon);
            }
        },
        forget() {
            try { localStorage.removeItem(this.KEY); } catch (e) {}
            $wire.forget();
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
        },
        localNudge(text) {
            return String(text || '').replace(/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z/g, (iso) => this.local(iso));
        }
    }));
</script>
@endscript

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
        <p class="mt-4 text-xs" style="color: var(--color-faint);">
            {{ __('For :lat, :lon. Times in your local time zone.', ['lat' => number_format((float) $view->lat, 2), 'lon' => number_format((float) $view->lon, 2)]) }}
            <button type="button" class="link-quiet underline" @click="forget()">{{ __('Forget my location') }}</button>
        </p>
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
            <button type="button" class="link-quiet text-sm underline" @click="manual = !manual">{{ __('or type coordinates') }}</button>
        </div>
        <p class="mt-2 text-xs" style="color: var(--color-faint);" x-show="geoError" x-text="geoError" x-cloak></p>

        <form class="mt-3 flex flex-wrap items-end gap-2" x-show="manual" x-cloak @submit.prevent="submitManual()">
            <label class="text-xs" style="color: var(--muted);">{{ __('Latitude') }}
                <input type="number" step="0.01" min="-90" max="90" x-model.number="lat" required
                       class="mt-1 block w-28 rounded-lg border px-2 py-1.5 text-sm" style="background-color: var(--bg-elevated); border-color: var(--border); color: var(--text);">
            </label>
            <label class="text-xs" style="color: var(--muted);">{{ __('Longitude') }}
                <input type="number" step="0.01" min="-180" max="180" x-model.number="lon" required
                       class="mt-1 block w-28 rounded-lg border px-2 py-1.5 text-sm" style="background-color: var(--bg-elevated); border-color: var(--border); color: var(--text);">
            </label>
            <button type="submit" class="rounded-lg border px-3 py-1.5 text-sm" style="border-color: var(--border); color: var(--text);">{{ __('Use these') }}</button>
        </form>
        @error('lat') <p class="mt-2 text-xs" style="color: #ffb4b4;">{{ $message }}</p> @enderror
        @error('lon') <p class="mt-2 text-xs" style="color: #ffb4b4;">{{ $message }}</p> @enderror

        <p class="mt-3 text-xs" style="color: var(--color-faint);">
            {{ __('Your location stays in your browser and is sent only for this calculation.') }}
            @if (Route::has('privacy'))
                {{ __('See our') }} <a class="link-quiet underline" href="{{ route('privacy') }}">{{ __('privacy policy') }}</a>.
            @endif
        </p>
    @endif
</div>

@script
<script>
    Alpine.data('skyObserver', () => ({
        busy: false, manual: false, geoError: '', lat: null, lon: null,
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
            if (!navigator.geolocation) { this.geoError = @js(__('Your browser has no location support — type coordinates instead.')); this.manual = true; return; }
            this.busy = true;
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    this.busy = false;
                    var lat = Math.round(pos.coords.latitude * 100) / 100, lon = Math.round(pos.coords.longitude * 100) / 100;
                    this.remember(lat, lon);
                    $wire.setLocation(lat, lon);
                },
                () => { this.busy = false; this.geoError = @js(__('Location not available — type coordinates instead.')); this.manual = true; },
                { timeout: 10000, maximumAge: 600000 }
            );
        },
        submitManual() {
            if (typeof this.lat !== 'number' || typeof this.lon !== 'number') return;
            this.remember(this.lat, this.lon);
            $wire.setLocation(this.lat, this.lon);
        },
        forget() {
            try { localStorage.removeItem(this.KEY); } catch (e) {}
            $wire.forget();
        },
        local(iso) {
            if (!iso) return '—';
            var d = new Date(iso);
            return isNaN(d) ? iso : d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
    }));
</script>
@endscript

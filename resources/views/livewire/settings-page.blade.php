<div class="mx-auto" style="max-width: var(--container-prose);"
     x-data="settingsPage(@js(route('settings')))" x-init="load()"
     x-on:theme-changed.window="theme = $event.detail.theme">
    <x-page-header :title="__('Your settings')" :eyebrow="config('site.name')"
                   :lead="__('Everything this site remembers about you is listed here. All of it lives in your own browser — nothing is stored on our servers, and there is no account. Change or clear any of it; it takes effect immediately.')" />

    <section class="surface mb-8 p-6" aria-labelledby="import-heading" x-show="pendingImport && importData" x-cloak>
        <h2 id="import-heading" class="font-serif text-xl font-medium">{{ __('Apply settings from a link?') }}</h2>
        <p class="mt-1 text-sm" style="color: var(--muted);">{{ __('Someone (probably you, on another device) shared these settings. Nothing is applied until you say so.') }}</p>
        <dl class="mt-4 grid gap-y-2 text-sm sm:grid-cols-[10rem_1fr]">
            <template x-if="importData && importData.theme">
                <dt style="color: var(--muted);">{{ __('Theme') }}</dt>
            </template>
            <template x-if="importData && importData.theme">
                <dd data-import="theme" x-text="importData.theme.charAt(0).toUpperCase() + importData.theme.slice(1)"></dd>
            </template>
            <template x-if="importData && importData.location">
                <dt style="color: var(--muted);">{{ __('Observing location') }}</dt>
            </template>
            <template x-if="importData && importData.location">
                <dd data-import="location" class="tabular-nums" x-text="importData.location.lat.toFixed(2) + ', ' + importData.location.lon.toFixed(2)"></dd>
            </template>
            <template x-if="importData && importData.preferences && importData.preferences.timeFormat">
                <dt style="color: var(--muted);">{{ __('Time format') }}</dt>
            </template>
            <template x-if="importData && importData.preferences && importData.preferences.timeFormat">
                <dd data-import="timeFormat" x-text="timeFormatLabel(importData.preferences.timeFormat)"></dd>
            </template>
        </dl>
        <div class="mt-4 flex flex-wrap gap-3">
            <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium" style="background-color: var(--accent); color: #07090f;" @click="applyImport()">{{ __('Apply these settings') }}</button>
            <button type="button" class="rounded-lg border px-4 py-2 text-sm" style="border-color: var(--border); color: var(--text);" @click="dismissImport()">{{ __('Ignore') }}</button>
        </div>
    </section>
    <p class="surface mb-8 p-4 text-sm" style="color: var(--muted);" x-show="importFailed" x-cloak>{{ __('That settings link couldn\'t be read, so nothing was changed.') }}</p>

    <div class="space-y-6">
        {{-- Theme --}}
        <section class="surface p-6" aria-labelledby="theme-heading">
            <h2 id="theme-heading" class="font-serif text-xl font-medium">{{ __('Theme') }}</h2>
            <div class="mt-3 flex flex-wrap gap-2" role="radiogroup" aria-labelledby="theme-heading">
                @foreach ($themes as $t)
                    <button type="button" role="radio" :aria-checked="theme === '{{ $t }}'" @click="setTheme('{{ $t }}')"
                            class="rounded-lg border px-4 py-2 text-sm" :style="theme === '{{ $t }}' ? 'border-color: var(--accent); color: var(--text);' : 'border-color: var(--border); color: var(--muted);'">{{ ucfirst($t) }}</button>
                @endforeach
            </div>
            <p class="mt-3 text-xs" style="color: var(--color-faint);">{{ __('Stored as') }} <code class="font-mono">theme</code>.</p>
        </section>

        {{-- Location --}}
        <section class="surface p-6" aria-labelledby="location-heading">
            <h2 id="location-heading" class="font-serif text-xl font-medium">{{ __('Observing location') }}</h2>
            <template x-if="location">
                <div>
                    <p class="mt-2 text-sm" style="color: var(--text);">
                        {{ __('Remembered:') }} <span class="tabular-nums" x-text="location.lat.toFixed(2) + ', ' + location.lon.toFixed(2)"></span>
                        <span style="color: var(--muted);">{{ __('(rounded to about a kilometre)') }}</span>
                    </p>
                    <p class="mt-1 text-sm" style="color: var(--muted);">{{ __('Used on every object page to say where and when it\'s visible for you. It is sent to our API only for that calculation and never stored there.') }}</p>
                    <button type="button" class="mt-3 rounded-lg border px-4 py-2 text-sm" style="border-color: var(--border); color: var(--text);" @click="forgetLocation()">{{ __('Forget my location') }}</button>
                </div>
            </template>
            <template x-if="!location">
                <p class="mt-2 text-sm" style="color: var(--muted);">{{ __('No location remembered. Set one from the "In the sky" panel on any object page.') }}</p>
            </template>
            <p class="mt-3 text-xs" style="color: var(--color-faint);">{{ __('Stored as') }} <code class="font-mono">observer_location</code>.</p>
        </section>

        {{-- Preferences --}}
        <section class="surface p-6" aria-labelledby="prefs-heading">
            <h2 id="prefs-heading" class="font-serif text-xl font-medium">{{ __('Display') }}</h2>
            <p class="mt-3 text-sm" style="color: var(--muted);" id="timefmt-label">{{ __('Time format for rise, transit and set times') }}</p>
            <div class="mt-2 flex flex-wrap gap-2" role="radiogroup" aria-labelledby="timefmt-label">
                @foreach (['auto' => __('Match my device'), '12' => __('12-hour'), '24' => __('24-hour')] as $val => $label)
                    <button type="button" role="radio" :aria-checked="prefs.timeFormat === '{{ $val }}'" @click="setTimeFormat('{{ $val }}')"
                            class="rounded-lg border px-4 py-2 text-sm" :style="prefs.timeFormat === '{{ $val }}' ? 'border-color: var(--accent); color: var(--text);' : 'border-color: var(--border); color: var(--muted);'">{{ $label }}</button>
                @endforeach
            </div>
            <p class="mt-3 text-xs" style="color: var(--color-faint);">{{ __('Stored as') }} <code class="font-mono">preferences</code>.</p>
        </section>

        {{-- Share --}}
        <section class="surface p-6" aria-labelledby="share-heading">
            <h2 id="share-heading" class="font-serif text-xl font-medium">{{ __('Use these settings on another device') }}</h2>
            <p class="mt-2 text-sm" style="color: var(--muted);">{{ __('This link carries your settings after the # — your browser never sends that part to us, so it does not appear in our logs or in Referer. Nothing is applied until you confirm. Anyone with the link can still read the settings in it, so treat it like the location it contains.') }}</p>
            <div class="mt-3 flex flex-wrap items-stretch gap-2">
                <input type="text" readonly :value="shareUrl()" class="block w-full max-w-lg rounded-lg border px-3 py-1.5 font-mono text-xs" style="background-color: var(--bg-elevated); border-color: var(--border); color: var(--text);" @focus="$event.target.select()" aria-label="{{ __('Settings link') }}">
                <button type="button" class="rounded-lg border px-3 py-1.5 text-sm" style="border-color: var(--border); color: var(--text);" @click="copyShare()" x-text="copied ? @js(__('Copied')) : @js(__('Copy link'))"></button>
            </div>
        </section>

        {{-- Clear all --}}
        <section class="surface p-6" aria-labelledby="clear-heading">
            <h2 id="clear-heading" class="font-serif text-xl font-medium">{{ __('Clear everything') }}</h2>
            <p class="mt-2 text-sm" style="color: var(--muted);">{{ __('Removes the theme, location and preferences above from this browser. The cookie-consent choice is separate and is covered on the privacy page.') }}</p>
            <button type="button" class="mt-3 rounded-lg border px-4 py-2 text-sm" style="border-color: #ffb4b4; color: #ffb4b4;" @click="clearAll()">{{ __('Clear everything this site remembers') }}</button>
        </section>

        <p class="text-sm" style="color: var(--muted);">
            {{ __('How this fits with cookies and our privacy commitments:') }} <a class="link-quiet underline" href="{{ route('privacy') }}">{{ __('Privacy & cookies') }}</a>.
        </p>
    </div>
</div>

@script
<script>
    Alpine.data('settingsPage', (baseUrl) => ({
        theme: 'dark', location: null, prefs: { timeFormat: 'auto' }, copied: false,
        importData: null, pendingImport: false, importFailed: false,
        timeLabels: { auto: @js(__('Match my device')), '12': @js(__('12-hour')), '24': @js(__('24-hour')) },
        get(k) { try { return JSON.parse(localStorage.getItem(k) || 'null'); } catch (e) { return null; } },
        set(k, v) { try { v === null ? localStorage.removeItem(k) : localStorage.setItem(k, typeof v === 'string' ? v : JSON.stringify(v)); } catch (e) {} },
        // Keep in step with App\Support\SettingsPayload::clean / decode.
        cleanPayload(data) {
            if (!data || typeof data !== 'object' || Array.isArray(data)) return {};
            var out = {};
            if (data.theme === 'dark' || data.theme === 'light') out.theme = data.theme;
            var loc = data.location;
            if (loc && typeof loc === 'object' && isFinite(Number(loc.lat)) && isFinite(Number(loc.lon))) {
                var lat = Math.round(Number(loc.lat) * 100) / 100;
                var lon = Math.round(Number(loc.lon) * 100) / 100;
                if (lat >= -90 && lat <= 90 && lon >= -180 && lon <= 180) out.location = { lat: lat, lon: lon };
            }
            var prefs = data.preferences;
            if (prefs && typeof prefs === 'object') {
                var p = {};
                var fmt = prefs.timeFormat != null ? String(prefs.timeFormat) : '';
                if (['auto', '12', '24'].includes(fmt)) p.timeFormat = fmt;
                if (Object.keys(p).length) out.preferences = p;
            }
            return out;
        },
        decodeToken(token) {
            token = String(token || '').trim();
            if (!token || token.length > 400 || !/^[A-Za-z0-9_-]+$/.test(token)) return null;
            try {
                var b64 = token.replace(/-/g, '+').replace(/_/g, '/');
                while (b64.length % 4) b64 += '=';
                var json = decodeURIComponent(escape(atob(b64)));
                var cleaned = this.cleanPayload(JSON.parse(json));
                return Object.keys(cleaned).length ? cleaned : null;
            } catch (e) { return null; }
        },
        // Fragment first (#s=): never sent to the server. Query ?s= is only for
        // links minted before that change; we still honour them, then strip.
        shareTokenFromUrl() {
            try {
                var hash = window.location.hash || '';
                var fromHash = new URLSearchParams(hash.replace(/^#/, '')).get('s');
                if (fromHash) return fromHash;
                return new URL(window.location.href).searchParams.get('s') || '';
            } catch (e) { return ''; }
        },
        loadStored() {
            try { this.theme = localStorage.getItem('theme') || 'dark'; } catch (e) {}
            var loc = this.get('observer_location');
            this.location = (loc && typeof loc.lat === 'number' && typeof loc.lon === 'number') ? loc : null;
            var p = this.get('preferences') || {};
            this.prefs = { timeFormat: ['auto', '12', '24'].includes(p.timeFormat) ? p.timeFormat : 'auto' };
        },
        load() {
            var token = this.shareTokenFromUrl();
            this.importData = token ? this.decodeToken(token) : null;
            this.importFailed = token !== '' && !this.importData;
            this.pendingImport = !!this.importData;
            this.forgetShareToken();
            this.loadStored();
        },
        setTheme(t) { window.applyTheme(t); },
        forgetLocation() { this.location = null; this.set('observer_location', null); },
        setTimeFormat(v) { this.prefs.timeFormat = v; this.set('preferences', this.prefs); },
        timeFormatLabel(v) { return this.timeLabels[v] || v; },
        clearAll() {
            ['theme', 'observer_location', 'preferences'].forEach(k => this.set(k, null));
            window.applyTheme('dark', false);
            this.loadStored();
        },
        payload() {
            var o = { theme: this.theme, preferences: this.prefs };
            if (this.location) o.location = this.location;
            return o;
        },
        shareUrl() {
            var json = JSON.stringify(this.payload());
            var b64 = btoa(unescape(encodeURIComponent(json))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
            return baseUrl + '#s=' + b64;
        },
        async copyShare() {
            try { await navigator.clipboard.writeText(this.shareUrl()); this.copied = true; setTimeout(() => this.copied = false, 2000); } catch (e) {}
        },
        applyImport() {
            if (!this.importData) return;
            if (this.importData.theme) this.setTheme(this.importData.theme);
            if (this.importData.location) { this.location = this.importData.location; this.set('observer_location', this.importData.location); }
            if (this.importData.preferences && this.importData.preferences.timeFormat) this.setTimeFormat(this.importData.preferences.timeFormat);
            this.dismissImport();
        },
        dismissImport() {
            this.pendingImport = false;
            this.importFailed = false;
            this.importData = null;
            this.forgetShareToken();
        },
        // Drop query and fragment so a later Referer, copied URL or analytics
        // beacon cannot pick the token back up from the address bar.
        forgetShareToken() {
            try {
                var url = new URL(window.location.href);
                url.searchParams.delete('s');
                var hashParams = new URLSearchParams((url.hash || '').replace(/^#/, ''));
                if (hashParams.has('s')) url.hash = '';
                history.replaceState(null, '', url.pathname + (url.search || '') + url.hash);
            } catch (e) {
                history.replaceState(null, '', baseUrl);
            }
        }
    }));
</script>
@endscript

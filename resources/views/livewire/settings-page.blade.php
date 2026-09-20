<div class="mx-auto" style="max-width: var(--container-prose);"
     x-data="settingsPage(@js($import), @js(route('settings')))" x-init="load()">
    <x-page-header :title="__('Your settings')" :eyebrow="config('site.name')"
                   :lead="__('Everything this site remembers about you is listed here. All of it lives in your own browser — nothing is stored on our servers, and there is no account. Change or clear any of it; it takes effect immediately.')" />

    @if ($import)
        <section class="surface mb-8 p-6" aria-labelledby="import-heading" x-show="pendingImport" x-cloak>
            <h2 id="import-heading" class="font-serif text-xl font-medium">{{ __('Apply settings from a link?') }}</h2>
            <p class="mt-1 text-sm" style="color: var(--muted);">{{ __('Someone (probably you, on another device) shared these settings. Nothing is applied until you say so.') }}</p>
            <dl class="mt-4 grid gap-y-2 text-sm sm:grid-cols-[10rem_1fr]">
                @isset ($import['theme'])
                    <dt style="color: var(--muted);">{{ __('Theme') }}</dt><dd data-import="theme">{{ ucfirst($import['theme']) }}</dd>
                @endisset
                @isset ($import['location'])
                    <dt style="color: var(--muted);">{{ __('Observing location') }}</dt>
                    <dd data-import="location" class="tabular-nums">{{ number_format($import['location']['lat'], 2) }}, {{ number_format($import['location']['lon'], 2) }}</dd>
                @endisset
                @isset ($import['preferences']['timeFormat'])
                    <dt style="color: var(--muted);">{{ __('Time format') }}</dt>
                    <dd data-import="timeFormat">{{ ['auto' => __('Match my device'), '12' => __('12-hour'), '24' => __('24-hour')][$import['preferences']['timeFormat']] }}</dd>
                @endisset
            </dl>
            <div class="mt-4 flex flex-wrap gap-3">
                <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium" style="background-color: var(--accent); color: #07090f;" @click="applyImport()">{{ __('Apply these settings') }}</button>
                <button type="button" class="rounded-lg border px-4 py-2 text-sm" style="border-color: var(--border); color: var(--text);" @click="dismissImport()">{{ __('Ignore') }}</button>
            </div>
        </section>
    @elseif ($importFailed)
        <p class="surface mb-8 p-4 text-sm" style="color: var(--muted);">{{ __('That settings link couldn\'t be read, so nothing was changed.') }}</p>
    @endif

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
            <p class="mt-2 text-sm" style="color: var(--muted);">{{ __('This link carries your settings inside it — no account, nothing sent to us until you open it there, and nothing applied until you confirm. Anyone with the link can see the settings in it, so treat it like the location it contains.') }}</p>
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
    Alpine.data('settingsPage', (importData, baseUrl) => ({
        theme: 'dark', location: null, prefs: { timeFormat: 'auto' }, copied: false,
        pendingImport: !!importData,
        get(k) { try { return JSON.parse(localStorage.getItem(k) || 'null'); } catch (e) { return null; } },
        set(k, v) { try { v === null ? localStorage.removeItem(k) : localStorage.setItem(k, typeof v === 'string' ? v : JSON.stringify(v)); } catch (e) {} },
        load() {
            try { this.theme = localStorage.getItem('theme') || 'dark'; } catch (e) {}
            var loc = this.get('observer_location');
            this.location = (loc && typeof loc.lat === 'number' && typeof loc.lon === 'number') ? loc : null;
            var p = this.get('preferences') || {};
            this.prefs = { timeFormat: ['auto', '12', '24'].includes(p.timeFormat) ? p.timeFormat : 'auto' };
        },
        setTheme(t) { this.theme = t; document.documentElement.setAttribute('data-theme', t); this.set('theme', t); },
        forgetLocation() { this.location = null; this.set('observer_location', null); },
        setTimeFormat(v) { this.prefs.timeFormat = v; this.set('preferences', this.prefs); },
        clearAll() {
            ['theme', 'observer_location', 'preferences'].forEach(k => this.set(k, null));
            document.documentElement.setAttribute('data-theme', 'dark');
            this.load();
        },
        payload() {
            var o = { theme: this.theme, preferences: this.prefs };
            if (this.location) o.location = this.location;
            return o;
        },
        shareUrl() {
            var json = JSON.stringify(this.payload());
            var b64 = btoa(unescape(encodeURIComponent(json))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
            return baseUrl + '?s=' + b64;
        },
        async copyShare() {
            try { await navigator.clipboard.writeText(this.shareUrl()); this.copied = true; setTimeout(() => this.copied = false, 2000); } catch (e) {}
        },
        applyImport() {
            if (!importData) return;
            if (importData.theme) this.setTheme(importData.theme);
            if (importData.location) { this.location = importData.location; this.set('observer_location', importData.location); }
            if (importData.preferences && importData.preferences.timeFormat) this.setTimeFormat(importData.preferences.timeFormat);
            this.dismissImport();
        },
        dismissImport() {
            this.pendingImport = false;
            history.replaceState(null, '', baseUrl);
        }
    }));
</script>
@endscript

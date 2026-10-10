<section class="surface mt-12 p-5 sm:p-6" aria-labelledby="next-pass-heading" @if ($approach) data-pass-at="{{ $approach->cdIso }}" @endif>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h2 id="next-pass-heading" class="text-sm font-semibold uppercase tracking-[0.16em]" style="color: var(--accent);">
                {{ __('Next close approach') }}
            </h2>
            <p class="mt-2 text-sm leading-relaxed" style="color: var(--muted);">
                {{ __('The next catalogued asteroid or comet passing within :ld lunar distances of Earth.', ['ld' => \App\Support\UpcomingCloseApproach::MAX_LUNAR_DISTANCES]) }}
            </p>
        </div>
        <a href="{{ route('close-approaches') }}"
           class="inline-flex min-h-11 shrink-0 items-center underline decoration-1 underline-offset-4"
           style="color: var(--link);">
            {{ __('All close approaches') }}
        </a>
    </div>

    @if ($apiDown)
        <div class="mt-4">
            <x-api-down :section="__('The next close approach')" />
        </div>
    @elseif (! $approach)
        <div class="mt-4" role="status">
            <p class="font-medium" style="color: var(--text);">{{ __('No close approach in this window') }}</p>
            <p class="mt-1 text-sm leading-relaxed" style="color: var(--muted);">
                {{ __('No catalogued Earth encounter within 10 lunar distances falls in the next :days days. Coverage may be incomplete, and this does not establish that no encounters occur.', ['days' => $days]) }}
            </p>
        </div>
    @else
        <div class="mt-5 min-w-0">
            <h3 class="break-words font-serif text-3xl font-medium leading-tight" style="color: var(--text);">
                @if ($objectUrl)
                    <a href="{{ $objectUrl }}" class="underline decoration-1 underline-offset-4" style="color: var(--link);">{{ $approach->name }}</a>
                @else
                    {{ $approach->name }}
                @endif
            </h3>
            @if ($objectState === 'missing')
                <p class="mt-1 text-sm" style="color: var(--muted);">{{ __('No object page is published for this record.') }}</p>
            @elseif ($objectState === 'unknown')
                <p class="mt-1 text-sm" style="color: var(--muted);">{{ __('The object page could not be checked just now.') }}</p>
            @endif

            <p class="mt-3 font-serif text-2xl tabular-nums leading-tight sm:text-3xl" style="color: var(--text);"
               role="status" aria-live="polite" aria-atomic="true" data-pass-countdown>
                {{ $countdown }}
            </p>

            <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase tracking-wide" style="color: var(--muted);">{{ __('Time (UTC)') }}</dt>
                    <dd class="mt-1 tabular-nums" style="color: var(--text);">
                        <time datetime="{{ $approach->cdIso }}">{{ $utcLabel }}</time>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide" style="color: var(--muted);">{{ __('Local time') }}</dt>
                    <dd class="mt-1 text-sm leading-relaxed sm:text-base" style="color: var(--text);" data-pass-local>
                        {{ __('Shown from this device’s time zone when the browser can read it.') }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide" style="color: var(--muted);">{{ __('Miss distance') }}</dt>
                    <dd class="mt-1 tabular-nums" style="color: var(--text);">
                        <span class="block">{{ $lunarDistance }}</span>
                        <span class="mt-1 block">{{ $distanceKm }}</span>
                    </dd>
                </div>
                @if ($velocity)
                    <div>
                        <dt class="text-xs uppercase tracking-wide" style="color: var(--muted);">{{ __('Relative speed') }}</dt>
                        <dd class="mt-1 tabular-nums" style="color: var(--text);">{{ $velocity }}</dd>
                    </div>
                @endif
                @if ($size)
                    <div>
                        <dt class="text-xs uppercase tracking-wide" style="color: var(--muted);">
                            {{ $size['approximate'] ? __('Approximate size') : __('Size') }}
                        </dt>
                        <dd class="mt-1 tabular-nums" style="color: var(--text);" @if ($size['note']) aria-describedby="pass-size-note" @endif>
                            {{ $size['text'] }}
                        </dd>
                    </div>
                @endif
            </dl>

            @if ($capped)
                <p class="mt-4 text-sm leading-relaxed" style="color: var(--muted);">
                    {{ __('The catalogue returned its closest :limit encounters in this window, so an earlier, more distant pass may not be shown.', ['limit' => $limit]) }}
                </p>
            @endif

            <p class="mt-4 text-xs leading-relaxed" style="color: var(--muted);">
                {{ __('1 lunar distance = 384,400 km. Nominal distance; uncertainties vary by object and orbit solution. Source: JPL close-approach data in the catalogue snapshot.') }}
                @if ($size['note'] ?? null)
                    <span id="pass-size-note" class="mt-1 block">{{ $size['note'] }}</span>
                @endif
            </p>
        </div>

        @script
        <script>
        (() => {
            const upcomingPassClock = { minute: 60 * 1000, reduced: 5 * 60 * 1000 };
            const root = $wire.$el;
            const iso = root.getAttribute('data-pass-at');
            if (!iso || root.dataset.upcomingPassBound === '1') return;
            root.dataset.upcomingPassBound = '1';

            const phrases = @js($phrases);
            const countdown = root.querySelector('[data-pass-countdown]');
            const local = root.querySelector('[data-pass-local]');
            const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
            let timer = null;

            const unit = (count, one, many) => (count === 1 ? one : many).replace(':count', String(count));

            const phrase = () => {
                const target = Date.parse(iso);
                if (Number.isNaN(target)) return countdown ? countdown.textContent : '';
                const seconds = Math.floor((target - Date.now()) / 1000);
                if (seconds <= 0) return phrases.passed;
                if (seconds < 60) return phrases.soon;
                const minutes = Math.floor(seconds / 60);
                const days = Math.floor(minutes / 1440);
                const hours = Math.floor((minutes % 1440) / 60);
                const mins = minutes % 60;
                const parts = [];
                if (days > 0) parts.push(unit(days, phrases.day, phrases.days));
                if (hours > 0) parts.push(unit(hours, phrases.hour, phrases.hours));
                if (days === 0 && mins > 0) parts.push(unit(mins, phrases.minute, phrases.minutes));
                return phrases.template.replace(':when', parts.join(' '));
            };

            const render = () => {
                if (!countdown) return;
                const next = phrase();
                if (countdown.textContent !== next) countdown.textContent = next;
            };

            const formatLocal = () => {
                if (!local) return;
                const date = new Date(iso);
                if (Number.isNaN(date.getTime())) return;
                local.textContent = new Intl.DateTimeFormat(undefined, {
                    weekday: 'short',
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                    timeZoneName: 'short',
                }).format(date);
            };

            const arm = () => {
                clearTimeout(timer);
                timer = setTimeout(() => {
                    if (!document.hidden) render();
                    arm();
                }, motion.matches ? upcomingPassClock.reduced : upcomingPassClock.minute);
            };

            const onVisible = () => {
                if (!document.hidden) {
                    render();
                    arm();
                }
            };
            const onMotion = () => arm();

            formatLocal();
            render();
            arm();
            document.addEventListener('visibilitychange', onVisible);
            if (typeof motion.addEventListener === 'function') motion.addEventListener('change', onMotion);

            const stop = () => {
                clearTimeout(timer);
                document.removeEventListener('visibilitychange', onVisible);
                if (typeof motion.removeEventListener === 'function') motion.removeEventListener('change', onMotion);
                delete root.dataset.upcomingPassBound;
            };
            document.addEventListener('livewire:navigating', stop, { once: true });
        })();
        </script>
        @endscript
    @endif
</section>

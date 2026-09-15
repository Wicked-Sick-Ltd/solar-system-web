@props(['distance'])

@php
    use App\Support\Format;
@endphp

{{-- Dedication panel: the Insomnia pub-quiz tie-breaker, immortalised. --}}
<aside id="proto" class="surface mt-8 p-6" aria-labelledby="proto-heading"
       style="border-color: color-mix(in srgb, var(--accent) 45%, transparent); scroll-margin-top: 5rem;">
    <p class="text-xs font-semibold uppercase tracking-[0.16em]" style="color: var(--accent);">{{ __('Insomnia pub quiz · tie-breaker') }}</p>
    <h2 id="proto-heading" class="mt-1 font-serif text-2xl font-medium">{{ __('For Proto, so he never forgets') }}</h2>
    <p class="mt-2 text-base" style="color: var(--muted);">{{ __('Q: How far is Pluto from the Sun, in AU?') }}</p>

    <dl class="mt-5 grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-4">
        <div class="col-span-2 sm:col-span-1">
            <dt class="text-xs uppercase tracking-wide" style="color: var(--muted);">{{ __('On average') }}</dt>
            <dd class="font-serif text-4xl tabular-nums" style="color: var(--accent);">{{ Format::au($distance->meanAu, 2) }}</dd>
        </div>
        <div>
            <dt class="text-xs uppercase tracking-wide" style="color: var(--muted);">{{ __('Closest (perihelion)') }}</dt>
            <dd class="font-serif text-xl tabular-nums" style="color: var(--text);">{{ Format::au($distance->perihelionAu, 2) }}</dd>
        </div>
        <div>
            <dt class="text-xs uppercase tracking-wide" style="color: var(--muted);">{{ __('Farthest (aphelion)') }}</dt>
            <dd class="font-serif text-xl tabular-nums" style="color: var(--text);">{{ Format::au($distance->aphelionAu, 2) }}</dd>
        </div>
        @if ($distance->nowAu !== null)
            <div>
                <dt class="text-xs uppercase tracking-wide" style="color: var(--muted);">{{ __('Right now') }}</dt>
                <dd class="font-serif text-xl tabular-nums" style="color: var(--text);">{{ Format::au($distance->nowAu, 2) }}</dd>
            </div>
        @endif
    </dl>

    <p class="mt-5 text-sm leading-relaxed" style="color: var(--text); max-width: 65ch;">
        {{ __('One astronomical unit is the average distance from the Earth to the Sun, roughly 150 million km. Pluto\'s orbit is stretched enough that any answer between about 30 and 49 would have earned a generous nod from the quizmaster.') }}
    </p>
    <p class="mt-3 text-sm leading-relaxed" style="color: var(--text); max-width: 65ch;">
        {{ __('This panel exists because the question above was asked on stage, as a tie-breaker, at the Insomnia pub quiz. It was answered by a man with a PhD in astrophysics. It was answered wrong. We are not saying by how much. We are just leaving this here.') }}
    </p>
    <p class="mt-4 text-xs" style="color: var(--color-faint);">
        {{ __('Reference values from the NASA Planetary Fact Sheet.') }}
        @if ($distance->isLive())
            {{ __('Figures shown are live from the catalogue.') }}
        @endif
        {{ __('Permanent link:') }} <a class="link-quiet underline" href="{{ route('objects.show', 'dwarf-pluto') }}#proto">{{ __('/objects/dwarf-pluto#proto') }}</a>
    </p>
</aside>
